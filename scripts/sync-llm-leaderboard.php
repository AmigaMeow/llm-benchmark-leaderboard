<?php
/**
 * 大模型对照榜 · 每日同步脚本。
 *
 * 拉取段（步骤 2）：三源 → cache/llm/raw-*.json；
 * 合并段（步骤 4）：raw + config/llm_model_aliases.php → cache/llm/llm-leaderboard.json；
 *   未匹配的上游名追加到 logs/llm-unmatched.log，供人工定期补别名表。
 *
 * 数据源：
 *   - LMArena：Hugging Face datasets-server（lmarena-ai/leaderboard-dataset，config=text，split=latest），翻页拉全
 *   - OpenRouter：GET https://openrouter.ai/api/v1/models
 *   - Artificial Analysis：仅当 show_aa=true 且 aa_api_key 非空才请求；
 *     否则写 skipped 形态的 raw-aa.json，不打扰上游
 *
 * 规则：
 *   - 某源 HTTP 非 2xx 或 JSON 解码失败：保留该源旧 raw，不覆盖
 *   - 写文件一律临时文件 + rename，读取方不会看到半截 JSON
 *   - 以站点进程用户运行（如 sudo -u www-data）；挂每日 cron 即可
 *
 * 用法：
 *   sudo -u www-data php scripts/sync-llm-leaderboard.php             # 完整流程：拉取 + 合并写快照
 *   sudo -u www-data php scripts/sync-llm-leaderboard.php --pull-only # 只拉取 raw
 *   sudo -u www-data php scripts/sync-llm-leaderboard.php --dry-run   # 不拉取、不写文件，只演练合并并打印计数
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

const LLM_SYNC_UA = 'LLM-Leaderboard-Sync/1.0';
const LLM_SYNC_TIMEOUT = 45;
const LLM_SYNC_CACHE_DIR = __DIR__ . '/../cache/llm';
const LLM_SYNC_SNAPSHOT_FILE = 'llm-leaderboard.json';
const LLM_SYNC_HISTORY_DIR = LLM_SYNC_CACHE_DIR . '/history';
const LLM_SYNC_HISTORY_KEEP_DAYS = 60;
const LLM_SYNC_UNMATCHED_LOG = __DIR__ . '/../logs/llm-unmatched.log';
const LLM_SYNC_UNMATCHED_SEEN = 'unmatched-seen.json'; // 相对 cache/llm：上一轮未匹配集合，用于算「新出现」
const LLM_SYNC_ALERT_LOG = __DIR__ . '/../logs/llm-alerts.log'; // P3：高排名未匹配模型告警
const LLM_SYNC_ALERT_RANK = 20; // LMArena rank ≤ 此值的未匹配模型触发 WARNING

$config = require __DIR__ . '/../config/llm_sources.php';
if (!is_array($config)) {
    fwrite(STDERR, "[llm-sync] config/llm_sources.php 未返回数组\n");
    exit(1);
}

$pullOnly = in_array('--pull-only', $argv, true);
$dryRun = in_array('--dry-run', $argv, true);

/** 统一输出（cron 会重定向到 logs/llm-sync.log） */
function llm_sync_log(string $message): void
{
    echo date('Y-m-d H:i:s') . ' ' . $message . "\n";
}

/**
 * 发一次 GET（不重试）。
 * @return array{status:int, body:string, error:string} curl 层失败时 error 非空
 */
function llm_sync_http_get_once(string $url, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => LLM_SYNC_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_USERAGENT      => LLM_SYNC_UA,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = $body === false ? (string)curl_error($ch) : '';
    curl_close($ch);

    return [
        'status' => $status,
        'body'   => is_string($body) ? $body : '',
        'error'  => $error,
    ];
}

/**
 * GET 带重试：curl 失败 / 429 / 5xx 视为瞬时错误，退避 30 秒重试，共最多 3 次。
 * HF datasets-server 匿名档限流较严，每日 cron 也需要这层保护。
 * @return array{status:int, body:string, error:string}
 */
function llm_sync_http_get(string $url, array $headers = []): array
{
    $maxAttempts = 3;
    $res = llm_sync_http_get_once($url, $headers);
    for ($attempt = 1; $attempt < $maxAttempts; $attempt++) {
        $transient = $res['error'] !== ''
            || $res['status'] === 429
            || ($res['status'] >= 500 && $res['status'] <= 599);
        if (!$transient) {
            break;
        }
        $reason = $res['error'] !== '' ? $res['error'] : ('http ' . $res['status']);
        llm_sync_log('retry ' . $attempt . '/' . ($maxAttempts - 1) . ' after ' . $reason . '，sleep 30s: ' . $url);
        sleep(30);
        $res = llm_sync_http_get_once($url, $headers);
    }
    return $res;
}

/** 原子写 JSON：临时文件 + rename */
function llm_sync_write_json(string $filename, array $payload): bool
{
    $target = LLM_SYNC_CACHE_DIR . '/' . $filename;
    $tmp = $target . '.tmp-' . getmypid();
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        fwrite(STDERR, '[llm-sync] json_encode 失败: ' . $filename . "\n");
        return false;
    }
    if (file_put_contents($tmp, $json) === false || !rename($tmp, $target)) {
        @unlink($tmp);
        fwrite(STDERR, '[llm-sync] 写文件失败: ' . $filename . "\n");
        return false;
    }
    return true;
}

/**
 * LMArena：HF datasets-server 翻页拉全 latest split。
 * @return array{ok:bool, rows:array, http_status:?int, error:string}
 */
function llm_sync_fetch_lmarena(array $config): array
{
    $headers = [];
    $token = (string)($config['hf_token'] ?? '');
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $rows = [];
    $lastStatus = null;
    $offset = 0;
    $length = 100;
    $maxPages = 100; // 硬上限，防御无限翻页

    for ($page = 0; $page < $maxPages; $page++) {
        $url = 'https://datasets-server.huggingface.co/rows'
            . '?dataset=' . rawurlencode('lmarena-ai/leaderboard-dataset')
            . '&config=text&split=latest'
            . '&offset=' . $offset . '&length=' . $length;

        $res = llm_sync_http_get($url, $headers);
        $lastStatus = $res['status'];

        if ($res['error'] !== '' || $res['status'] < 200 || $res['status'] >= 300) {
            return [
                'ok' => false, 'rows' => [], 'http_status' => $lastStatus,
                'error' => $res['error'] !== '' ? $res['error'] : ('http ' . $res['status']),
            ];
        }

        $json = json_decode($res['body'], true);
        if (!is_array($json) || !isset($json['rows']) || !is_array($json['rows'])) {
            return ['ok' => false, 'rows' => [], 'http_status' => $lastStatus, 'error' => 'bad json or missing rows'];
        }

        $pageRows = [];
        foreach ($json['rows'] as $entry) {
            if (is_array($entry) && isset($entry['row']) && is_array($entry['row'])) {
                $pageRows[] = $entry['row'];
            }
        }
        if ($pageRows === []) {
            break; // 空页 = 拉完
        }

        // latest split 把多 category（overall/chinese/spanish/...）混在同一文件里，
        // datasets-server 的 filter 参数实测不生效，只能客户端过滤：只留 category=overall。
        // overall 区段在文件头部连续，出现无 overall 的页即视为离开该区段，停止翻页
        // （避免为 1 万行全量吃满匿名限流）。
        $pageOverall = 0;
        foreach ($pageRows as $row) {
            $category = strtolower((string)($row['category'] ?? ''));
            if ($category === 'overall' || $category === '' || $category === 'text') {
                $rows[] = $row;
                $pageOverall++;
            }
        }

        $offset += count($pageRows);
        if ($pageOverall === 0 && $rows !== []) {
            break; // 已离开 overall 区段
        }
        $total = isset($json['num_rows_total']) ? (int)$json['num_rows_total'] : null;
        if ($total !== null && $offset >= $total) {
            break;
        }
        if (count($pageRows) < $length) {
            break; // 最后一页不满
        }
    }

    if ($rows === []) {
        return ['ok' => false, 'rows' => [], 'http_status' => $lastStatus, 'error' => 'zero rows'];
    }
    return ['ok' => true, 'rows' => $rows, 'http_status' => $lastStatus, 'error' => ''];
}

/**
 * OpenRouter：GET /api/v1/models。
 * @return array{ok:bool, data:array, http_status:?int, error:string}
 */
function llm_sync_fetch_openrouter(array $config): array
{
    $headers = [];
    $key = (string)($config['openrouter_key'] ?? '');
    if ($key !== '') {
        $headers[] = 'Authorization: Bearer ' . $key;
    }

    $res = llm_sync_http_get('https://openrouter.ai/api/v1/models', $headers);
    if ($res['error'] !== '' || $res['status'] < 200 || $res['status'] >= 300) {
        return [
            'ok' => false, 'data' => [], 'http_status' => $res['status'],
            'error' => $res['error'] !== '' ? $res['error'] : ('http ' . $res['status']),
        ];
    }

    $json = json_decode($res['body'], true);
    if (!is_array($json) || !isset($json['data']) || !is_array($json['data']) || $json['data'] === []) {
        return ['ok' => false, 'data' => [], 'http_status' => $res['status'], 'error' => 'bad json or empty data'];
    }
    return ['ok' => true, 'data' => $json['data'], 'http_status' => $res['status'], 'error' => ''];
}

/**
 * Artificial Analysis：仅 show_aa=true 且 aa_api_key 非空才真正请求。
 * 未启用时返回 skipped 形态，由主流程写入 raw-aa.json。
 * @return array{ok:bool, skipped:bool, data:array, http_status:?int, error:string}
 */
function llm_sync_fetch_aa(array $config): array
{
    $key = (string)($config['aa_api_key'] ?? '');
    $enabled = !empty($config['show_aa']) && $key !== '';
    if (!$enabled) {
        return ['ok' => true, 'skipped' => true, 'data' => [], 'http_status' => null, 'error' => ''];
    }

    // 走到这里说明人已拍板 show_aa=true：主端点优先，404/410 再试 fallback
    $headers = ['x-api-key: ' . $key];
    $endpoints = array_values(array_filter([
        (string)($config['aa_endpoint'] ?? ''),
        (string)($config['aa_endpoint_fallback'] ?? ''),
    ], static fn ($v) => $v !== ''));

    $lastStatus = null;
    $lastError = 'no endpoint configured';
    foreach ($endpoints as $endpoint) {
        $res = llm_sync_http_get($endpoint, $headers);
        $lastStatus = $res['status'];
        if ($res['error'] !== '' || $res['status'] < 200 || $res['status'] >= 300) {
            $lastError = $res['error'] !== '' ? $res['error'] : ('http ' . $res['status']);
            // 仅 404/410 换 fallback；限流/鉴权失败不重复打
            if ($res['status'] !== 404 && $res['status'] !== 410) {
                break;
            }
            continue;
        }

        $json = json_decode($res['body'], true);
        if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
            $lastError = 'bad json or missing data';
            continue;
        }

        $data = $json['data'];
        // 防御式翻页：仅当响应带 pagination.next（整数页码）时继续，硬上限 10 页
        for ($p = 0; $p < 10; $p++) {
            $next = $json['pagination']['next'] ?? null;
            if (!is_int($next)) {
                break;
            }
            $pagedUrl = preg_replace('/([?&])page=\d+/', '$1', $endpoint);
            $pagedUrl = rtrim((string)$pagedUrl, '?&');
            $pagedUrl .= (strpos($pagedUrl, '?') === false ? '?' : '&') . 'page=' . $next;
            $res2 = llm_sync_http_get($pagedUrl, $headers);
            if ($res2['error'] !== '' || $res2['status'] < 200 || $res2['status'] >= 300) {
                $lastError = 'pagination failed at page ' . $next . ': ' . ($res2['error'] !== '' ? $res2['error'] : ('http ' . $res2['status']));
                $lastStatus = $res2['status'];
                $data = null;
                break;
            }
            $json = json_decode($res2['body'], true);
            if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
                $lastError = 'pagination bad json at page ' . $next;
                $lastStatus = $res2['status'];
                $data = null;
                break;
            }
            if ($json['data'] === []) {
                break; // 合法的末页：上游宣告了 next 但返回空页
            }
            foreach ($json['data'] as $item) {
                $data[] = $item;
            }
        }
        if ($data === null) {
            continue; // 中途翻页失败：视为本端点失败，可换 fallback
        }

        return ['ok' => true, 'skipped' => false, 'data' => $data, 'http_status' => $res['status'], 'error' => ''];
    }

    return ['ok' => false, 'skipped' => false, 'data' => [], 'http_status' => $lastStatus, 'error' => $lastError];
}

// ---------------------------------------------------------------------------
// 合并段（步骤 4）
// ---------------------------------------------------------------------------

/** 读一份 raw；不存在或 JSON 坏 → null */
function llm_sync_read_raw(string $filename): ?array
{
    $path = LLM_SYNC_CACHE_DIR . '/' . $filename;
    if (!is_file($path)) {
        return null;
    }
    $j = json_decode((string)file_get_contents($path), true);
    return is_array($j) ? $j : null;
}

/**
 * OpenRouter pricing 是 USD/token 字符串 → $/1M tokens。
 * 免费是 0（保留 0.0），缺失是 null（文档 6.1 原文口径）。
 */
function llm_price_per_million($raw) {
    if ($raw === null || $raw === '') return null;
    $n = (float)$raw;
    if ($n <= 0) return 0.0; // 免费模型允许 0
    return round($n * 1000000, 4);
}

/** 仅接受明确的 Hugging Face org/repo 标识，不从模型名猜测。 */
function llm_valid_hf_repo($value): ?string
{
    $value = trim((string)$value);
    return preg_match('/^[\w.-]+\/[\w.-]+$/', $value) === 1 ? $value : null;
}

/**
 * 合并：三源 raw + config/llm_model_aliases.php → cache/llm/llm-leaderboard.json
 *
 * @param array $pullState 各源本轮状态 ok|fail|skipped（dry-run 时反映磁盘 raw 可用性）
 * @return array{ok:bool,error:string,models:int,new_models:int,unmatched_counts:array,sources:array}
 */
function llm_sync_merge(array $config, array $pullState, bool $dryRun): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error, 'models' => 0, 'new_models' => 0,
                'unmatched_counts' => ['lmarena' => 0, 'openrouter' => 0, 'aa' => 0], 'alerts' => [], 'sources' => []];
    };

    $aliasesFile = __DIR__ . '/../config/llm_model_aliases.php';
    $aliases = is_file($aliasesFile) ? require $aliasesFile : null;
    if (!is_array($aliases) || $aliases === []) {
        return $fail('config/llm_model_aliases.php 缺失或为空');
    }

    // 本轮失败的源用上一份成功 raw（拉取段只在成功时才覆盖写，所以磁盘上的就是最近一次成功的）
    $lmRaw = llm_sync_read_raw('raw-lmarena.json');
    $orRaw = llm_sync_read_raw('raw-openrouter.json');
    $aaRaw = llm_sync_read_raw('raw-aa.json');

    $lmRows = ($lmRaw !== null && is_array($lmRaw['rows'] ?? null)) ? $lmRaw['rows'] : [];
    $orData = ($orRaw !== null && is_array($orRaw['data'] ?? null)) ? $orRaw['data'] : [];
    $aaSkipped = ($aaRaw === null) || !empty($aaRaw['skipped']);
    $aaData = (!$aaSkipped && is_array($aaRaw['data'] ?? null)) ? $aaRaw['data'] : [];

    // AA 分仅当 show_aa=true 且 raw 非 skipped 才取值；按别名里的 AA id 精确匹配，禁止模糊匹配名称
    $aaEnabled = !empty($config['show_aa']);
    $aaUsable = $aaEnabled && !$aaSkipped && $aaData !== [];

    // 反查表：上游字符串 → canonical（步骤 3 自检已保证同一字符串只属于一个 canonical）
    $lmRev = [];
    $orRev = [];
    $aaRev = [];
    foreach ($aliases as $canonical => $entry) {
        foreach ((array)($entry['lmarena'] ?? []) as $s) { $lmRev[$s] = $canonical; }
        foreach ((array)($entry['openrouter'] ?? []) as $s) { $orRev[$s] = $canonical; }
        foreach ((array)($entry['aa'] ?? []) as $s) { $aaRev[$s] = $canonical; }
    }

    // 上游索引 + unmatched 收集（无别名命中的上游行不进 models，只进 logs/llm-unmatched.log）
    $lmByName = [];
    $lmPublishDate = null;
    $unmatchedCounts = ['lmarena' => 0, 'openrouter' => 0, 'aa' => 0];
    $unmatchedLines = [];
    foreach ($lmRows as $r) {
        if (!is_array($r)) continue;
        $name = (string)($r['model_name'] ?? '');
        if ($name === '') continue;
        $lmByName[$name] = $r;
        $d = (string)($r['leaderboard_publish_date'] ?? '');
        if ($d !== '' && ($lmPublishDate === null || strcmp($d, $lmPublishDate) > 0)) {
            $lmPublishDate = $d;
        }
        if (!isset($lmRev[$name])) {
            $unmatchedCounts['lmarena']++;
            $unmatchedLines[] = "lmarena\t" . $name;
        }
    }
    // P3：高排名未匹配告警——新模型上榜当天就必须被看见，而不是等人翻 unmatched 日志
    $lmAlerts = [];
    foreach ($lmRows as $r) {
        if (!is_array($r)) continue;
        $name = (string)($r['model_name'] ?? '');
        if ($name === '' || isset($lmRev[$name])) continue;
        $rank = (int)($r['rank'] ?? 0);
        if ($rank > 0 && $rank <= LLM_SYNC_ALERT_RANK) {
            $lmAlerts[] = ['name' => $name, 'rank' => $rank, 'rating' => $r['rating'] ?? null, 'date' => (string)($r['leaderboard_publish_date'] ?? '')];
        }
    }
    usort($lmAlerts, static function (array $a, array $b): int { return $a['rank'] <=> $b['rank']; });
    $orById = [];
    foreach ($orData as $m) {
        if (!is_array($m)) continue;
        $id = (string)($m['id'] ?? '');
        if ($id === '') continue;
        $orById[$id] = $m;
        if (!isset($orRev[$id])) {
            $unmatchedCounts['openrouter']++;
            $unmatchedLines[] = "openrouter\t" . $id;
        }
    }
    $aaById = [];
    if ($aaUsable) {
        foreach ($aaData as $m) {
            if (!is_array($m)) continue;
            $id = (string)($m['id'] ?? '');
            $slug = (string)($m['slug'] ?? '');
            if ($id === '' && $slug === '') continue;
            // 别名表填人可读的 AA slug（UUID 也兼容）：两个键都进索引
            if ($id !== '') $aaById[$id] = $m;
            if ($slug !== '') $aaById[$slug] = $m;
            if (!isset($aaRev[$id]) && !isset($aaRev[$slug])) {
                $unmatchedCounts['aa']++;
                $unmatchedLines[] = "aa\t" . ($slug !== '' ? $slug : $id);
            }
        }
    }

    // 逐 canonical 构建 models 行（字段优先级按文档 6.1）
    $models = [];
    foreach ($aliases as $canonical => $entry) {
        $row = [
            'id' => $canonical,
            'display_name' => (string)($entry['display_name'] ?? $canonical),
            'org' => (string)($entry['org'] ?? ''),
            'license' => null,
            'open_weights' => null,
            'hf_repo' => null,
            'arena_score' => null,
            'arena_ci_low' => null,
            'arena_ci_high' => null,
            'arena_votes' => null,
            'arena_rank' => null,
            'aa_intelligence' => null,
            'aa_coding' => null,
            'aa_variant' => null,   // 当前行取的 AA 推理档位（如 max/xhigh）
            'aa_variants' => [],    // 全部档位的指标，详情页变体对比用
            'aa_speed' => null,
            'aa_ttft' => null,
            'price_in' => null,
            'price_out' => null,
            'context_length' => null,
            'listed_at' => null,
            'listed_at_iso' => null,
            'sources_hit' => [],
        ];

        // LMArena：raw 拉取段已只留 category=overall，此处不再按 category 过滤；
        // 多个别名命中（-max/-high 等推理预算变体）时取 rank 最小的一行
        $lmBest = null;
        foreach ((array)($entry['lmarena'] ?? []) as $name) {
            if (!isset($lmByName[$name])) continue;
            $cand = $lmByName[$name];
            if ($lmBest === null
                || (float)($cand['rank'] ?? 999999) < (float)($lmBest['rank'] ?? 999999)) {
                $lmBest = $cand;
            }
        }
        if ($lmBest !== null) {
            $row['sources_hit'][] = 'lmarena';
            $row['arena_score'] = isset($lmBest['rating']) ? round((float)$lmBest['rating'], 1) : null;
            $row['arena_ci_low'] = isset($lmBest['rating_lower']) ? round((float)$lmBest['rating_lower'], 1) : null;
            $row['arena_ci_high'] = isset($lmBest['rating_upper']) ? round((float)$lmBest['rating_upper'], 1) : null;
            $row['arena_votes'] = isset($lmBest['vote_count']) ? (int)$lmBest['vote_count'] : null;
            $row['arena_rank'] = isset($lmBest['rank']) ? (int)$lmBest['rank'] : null;
            $license = trim((string)($lmBest['license'] ?? ''));
            $row['license'] = $license !== '' ? $license : null;
            // 开源权重口径（文档 §1）：含 proprietary/closed 或为空 → 否；其余 → 是
            $row['open_weights'] = ($license === '' || preg_match('/proprietary|closed/i', $license)) ? false : true;
        }

        // OpenRouter：价格/上下文取第一个命中的别名（别名表把稳定基座 id 放前面）；
        // listed_at 取全部命中里最新的 created（带日期的快照变体视为同产品的版本更新上架）
        $orFirst = null;
        $orHfRepo = null;
        $listedMax = 0;
        foreach ((array)($entry['openrouter'] ?? []) as $id) {
            if (!isset($orById[$id])) continue;
            $m = $orById[$id];
            if ($orFirst === null) {
                $orFirst = $m;
            }
            if ($orHfRepo === null) {
                $orHfRepo = llm_valid_hf_repo($m['huggingFaceId'] ?? ($m['hugging_face_id'] ?? null));
            }
            $created = (int)($m['created'] ?? 0);
            if ($created > $listedMax) {
                $listedMax = $created;
            }
        }

        // 开源权重兜底：新模型常常还没进 LMArena，上面的 license 分支整段不执行，
        // $open_weights 会停在默认的 null —— 于是它在「开放权重」筛选下凭空消失
        // （V4.1 Flash / V3.2 / Kimi-K2.5 / Kimi-K2.7-Code / GLM-4.6 共 5 个）。
        // OpenRouter 给出厂商自有 org 下的公开 HF 仓库时，即视为开放权重。
        // 只补 null（未知），不覆盖 LMArena 已给出的判断。
        if ($row['open_weights'] === null && is_string($orHfRepo) && $orHfRepo !== '') {
            $row['open_weights'] = true;
        }
        if ($orFirst !== null) {
            $row['sources_hit'][] = 'openrouter';
            $pricing = is_array($orFirst['pricing'] ?? null) ? $orFirst['pricing'] : [];
            $row['price_in'] = llm_price_per_million($pricing['prompt'] ?? null);
            $row['price_out'] = llm_price_per_million($pricing['completion'] ?? null);
            $row['context_length'] = isset($orFirst['context_length']) ? (int)$orFirst['context_length'] : null;
            if ($listedMax > 0) {
                $row['listed_at'] = $listedMax;
                $row['listed_at_iso'] = gmdate('Y-m-d', $listedMax);
            }
        }

        // 别名表明确配置优先，否则采用 OpenRouter raw；两者都必须通过格式校验。
        $row['hf_repo'] = llm_valid_hf_repo($entry['hf_repo'] ?? null) ?? $orHfRepo;

        // AA：步骤 3 别名全空 → 本轮全部保持 null；将来补了 aa id 后按 id 精确取值
        if ($aaUsable) {
            foreach ((array)($entry['aa'] ?? []) as $aaId) {
                if (!isset($aaById[$aaId])) continue;
                $m = $aaById[$aaId];
                $evals = is_array($m['evaluations'] ?? null) ? $m['evaluations'] : [];
                $intel = $evals['artificial_analysis_intelligence_index'] ?? ($m['intelligence_index'] ?? null);
                $coding = $evals['artificial_analysis_coding_index'] ?? ($m['coding_index'] ?? null);
                if (is_numeric($intel)) {
                    $row['aa_intelligence'] = round((float)$intel, 1);
                }
                if (is_numeric($coding)) {
                    $row['aa_coding'] = round((float)$coding, 1);
                }
                // 性能实测：输出速度 tokens/s、首 token 延迟秒（AA 未实测的模型保持 null）
                $speed = $m['median_output_tokens_per_second'] ?? null;
                $ttft = $m['median_time_to_first_token_seconds'] ?? null;
                if (is_numeric($speed) && (float)$speed > 0) { // AA 对未实测模型返回 0 而非 null，0 没有展示意义
                    $row['aa_speed'] = round((float)$speed, 1);
                }
                if (is_numeric($ttft) && (float)$ttft > 0) {
                    $row['aa_ttft'] = round((float)$ttft, 2);
                }
                // 方案A：按 slug 后缀收集同模型其它推理档位变体（白名单，防止 flash-lite 之类误匹配）
                $effortSuffixes = ['max', 'xhigh', 'high', 'medium', 'low', 'non-reasoning', 'reasoning', 'adaptive', 'thinking'];
                $variants = [];
                $base = (string)$aaId;
                foreach ($aaById as $slug => $am) {
                    $vs = (string)$slug;
                    if ($vs === $base) {
                        $suffix = 'max';
                    } else {
                        if (strpos($vs, $base . '-') !== 0) continue;
                        $suffix = substr($vs, strlen($base) + 1);
                        if (!in_array($suffix, $effortSuffixes, true)) continue;
                    }
                    $ve = is_array($am['evaluations'] ?? null) ? $am['evaluations'] : [];
                    $vIntel = $ve['artificial_analysis_intelligence_index'] ?? ($am['intelligence_index'] ?? null);
                    $vCoding = $ve['artificial_analysis_coding_index'] ?? ($am['coding_index'] ?? null);
                    if (!is_numeric($vIntel) && !is_numeric($vCoding)) continue;
                    $vSpeed = $am['median_output_tokens_per_second'] ?? null;
                    $vTtft = $am['median_time_to_first_token_seconds'] ?? null;
                    $variants[] = [
                        'label' => $suffix,
                        'intel' => is_numeric($vIntel) ? round((float)$vIntel, 1) : null,
                        'coding' => is_numeric($vCoding) ? round((float)$vCoding, 1) : null,
                        'speed' => is_numeric($vSpeed) && (float)$vSpeed > 0 ? round((float)$vSpeed, 1) : null,
                        'ttft' => is_numeric($vTtft) && (float)$vTtft > 0 ? round((float)$vTtft, 2) : null,
                    ];
                }
                if ($variants !== []) {
                    usort($variants, static function ($a, $b) {
                        return ((float)($b['intel'] ?? -1) <=> (float)($a['intel'] ?? -1))
                            ?: strcmp((string)$a['label'], (string)$b['label']);
                    });
                    $row['aa_variants'] = $variants;
                    // 当前行命中 slug 的档位：base = max，其余按后缀
                    $row['aa_variant'] = $base === (string)$aaId ? 'max' : substr((string)$aaId, strlen($base) + 1);
                }
                $row['sources_hit'][] = 'aa';
                break; // 一个 canonical 只取第一个命中的 AA id；变体已在上方全量收集
            }
        }

        // 有一侧命中就保留（不过滤字段全空的行）；两侧全空才不进 models
        if ($row['sources_hit'] === []) {
            continue;
        }
        // 详情入口仅开放权重模型可用；闭源快照不得泄漏 HF 仓库值。
        if ($row['open_weights'] !== true) {
            $row['hf_repo'] = null;
        }
        $models[] = $row;
    }

    // 排序：arena_rank 升序（空分沉底），再按 id 字典序稳定
    usort($models, static function (array $a, array $b): int {
        $ra = $a['arena_rank'] ?? PHP_INT_MAX;
        $rb = $b['arena_rank'] ?? PHP_INT_MAX;
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }
        return strcmp($a['id'], $b['id']);
    });

    // 近 14 天新模型时间线：models 子集，listed_at 降序，最多 20 条
    $cutoff = time() - 14 * 86400;
    $newModels = array_values(array_filter($models, static function (array $m) use ($cutoff): bool {
        return ($m['listed_at'] ?? 0) >= $cutoff;
    }));
    usort($newModels, static function (array $a, array $b): int {
        return ($b['listed_at'] ?? 0) <=> ($a['listed_at'] ?? 0);
    });

    $newModels = array_slice($newModels, 0, 20);

    if ($models === []) {
        return $fail('合并结果为空（别名表与 raw 无交集），拒绝覆盖快照');
    }

    // sources 状态块：ok = 本轮拉取成功且磁盘 raw 可用；数据可能来自上一份成功 raw
    $sources = [
        'lmarena' => [
            'ok' => ($pullState['lmarena'] ?? 'fail') === 'ok' && $lmRows !== [],
            'fetched_at' => $lmRaw['fetched_at'] ?? null,
            'http_status' => $lmRaw['http_status'] ?? null,
            'publish_date' => $lmPublishDate,
            'rows' => count($lmRows),
        ],
        'openrouter' => [
            'ok' => ($pullState['openrouter'] ?? 'fail') === 'ok' && $orData !== [],
            'fetched_at' => $orRaw['fetched_at'] ?? null,
            'http_status' => $orRaw['http_status'] ?? null,
            'rows' => count($orData),
        ],
        'aa' => [
            'ok' => ($pullState['aa'] ?? 'fail') === 'ok' && $aaUsable,
            'enabled' => $aaEnabled,
            'fetched_at' => $aaRaw['fetched_at'] ?? null,
            'http_status' => $aaRaw['http_status'] ?? null,
            'rows' => $aaUsable ? count($aaData) : 0,
            'error' => !$aaEnabled ? 'disabled'
                    : ($aaSkipped ? 'disabled_or_no_key'
                    : ((($pullState['aa'] ?? '') === 'ok') ? '' : 'fetch_failed')),
        ],
    ];

    // 三源本轮都失败（aa skipped 不算成功）：禁止覆盖已有 llm-leaderboard.json
    $anyOk = in_array('ok', [
        $pullState['lmarena'] ?? '',
        $pullState['openrouter'] ?? '',
        $pullState['aa'] ?? '',
    ], true);
    if (!$dryRun && !$anyOk) {
        return $fail('三源本轮都失败，禁止覆盖已有 llm-leaderboard.json');
    }

    // 未匹配清单写成「排序去重的快照」，不再每次追加快照。
    // 旧写法把整份清单每天重复追加一次，34k 行里多出 2 行根本看不见——
    // DeepSeek V4.1 Flash 就是这样躲过 09-11、09-12 两次同步的。
    $unmatchedSet = array_values(array_unique($unmatchedLines));
    sort($unmatchedSet, SORT_STRING);
    $seenPath = LLM_SYNC_CACHE_DIR . '/' . LLM_SYNC_UNMATCHED_SEEN;
    $seenRaw = is_file($seenPath) ? @file_get_contents($seenPath) : false;
    $prevSeen = $seenRaw === false ? null : json_decode((string)$seenRaw, true);
    // $prevSeen === null = 首次运行：只建基线不告警，否则第一天会报上千条
    $newUnmatched = is_array($prevSeen) ? array_values(array_diff($unmatchedSet, $prevSeen)) : [];

    $snapshot = [
        'generated_at' => gmdate('c'),
        'schema_version' => 1,
        'sources' => $sources,
        'models' => $models,
        'new_models' => $newModels,
        'unmatched_counts' => $unmatchedCounts,
    ];

    if (!$dryRun) {
        // 未匹配清单写「快照」（排序去重，供人工定期补别名表）；写失败不阻塞快照
        if ($unmatchedSet !== []) {
            $fh = @fopen(LLM_SYNC_UNMATCHED_LOG, 'wb');
            if ($fh !== false) {
                fwrite($fh, '# ' . date('Y-m-d H:i:s') . ' snapshot ' . count($unmatchedSet) . " 条（排序去重）\n");
                foreach ($unmatchedSet as $line) {
                    fwrite($fh, $line . "\n");
                }
                fclose($fh);
            } else {
                llm_sync_log('merge: 无法写入 logs/llm-unmatched.log（不阻塞快照）');
            }
            // 记录本轮集合，供下一轮算「新出现」
            llm_sync_write_json(LLM_SYNC_UNMATCHED_SEEN, $unmatchedSet);
        }
        // temp + rename 原子写快照
        if (!llm_sync_write_json(LLM_SYNC_SNAPSHOT_FILE, $snapshot)) {
            return $fail('快照写入失败');
        }
        // 每日归档：供排行榜页算排名升降。失败只记日志，不影响快照
        $hist = llm_sync_archive_history($models, $dryRun);
        llm_sync_log('history: ' . ($hist['ok'] ? 'OK' : 'FAIL')
            . ' 归档 ' . $hist['count'] . ' 个已评分模型 -> cache/llm/history/' . $hist['file']);
    }

    return [
        'ok' => true,
        'error' => '',
        'models' => count($models),
        'new_models' => count($newModels),
        'unmatched_counts' => $unmatchedCounts,
        'alerts' => $lmAlerts,
        'new_unmatched' => $newUnmatched,
        'unmatched_seeded' => $prevSeen === null,
        'sources' => $sources,
    ];
}

/**
 * 每日归档：把本轮「已评分模型」的最小集写入 cache/llm/history/YYYY-MM-DD.json，
 * 供排行榜页算排名升降箭头。同一天重复执行覆盖；按 LLM_SYNC_HISTORY_KEEP_DAYS 保留。
 * 只存 id + arena_score + arena_rank，不存价格等大字段。
 * 归档失败仅记日志，不允许影响快照（快照此时已写完）。
 *
 * @return array{ok:bool, count:int, file:string}
 */
function llm_sync_archive_history(array $models, bool $dryRun): array
{
    $ranked = [];
    foreach ($models as $m) {
        if (!is_array($m) || ($m['arena_score'] ?? null) === null) continue;
        $ranked[] = [
            'id' => (string)($m['id'] ?? ''),
            'arena_score' => (float)$m['arena_score'],
            'arena_rank' => ($m['arena_rank'] ?? null) === null ? null : (int)$m['arena_rank'],
        ];
    }
    $date = date('Y-m-d');
    $file = $date . '.json';
    $payload = [
        'date' => $date,
        'generated_at' => time(),
        'models' => $ranked,
    ];
    if ($dryRun) {
        return ['ok' => true, 'count' => count($ranked), 'file' => '(dry-run 未写)'];
    }
    if (!is_dir(LLM_SYNC_HISTORY_DIR) && !mkdir(LLM_SYNC_HISTORY_DIR, 0775, true)) {
        llm_sync_log('history: 无法创建目录 ' . LLM_SYNC_HISTORY_DIR);
        return ['ok' => false, 'count' => count($ranked), 'file' => ''];
    }
    $target = LLM_SYNC_HISTORY_DIR . '/' . $file;
    $tmp = $target . '.tmp-' . getmypid();
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($tmp, $json) === false || !rename($tmp, $target)) {
        @unlink($tmp);
        llm_sync_log('history: 写 ' . $file . ' 失败');
        return ['ok' => false, 'count' => count($ranked), 'file' => ''];
    }
    // 清理过期归档（按文件名里的日期判断，不依赖 mtime）
    $cutoff = time() - LLM_SYNC_HISTORY_KEEP_DAYS * 86400;
    foreach (glob(LLM_SYNC_HISTORY_DIR . '/*.json') ?: [] as $f) {
        if (preg_match('#/(\d{4}-\d{2}-\d{2})\.json$#', $f, $mm)) {
            $ts = strtotime($mm[1] . ' 00:00:00');
            if ($ts !== false && $ts < $cutoff) @unlink($f);
        }
    }
    return ['ok' => true, 'count' => count($ranked), 'file' => $file];
}
// ---------------------------------------------------------------------------
// 主流程
// ---------------------------------------------------------------------------

if (!is_dir(LLM_SYNC_CACHE_DIR)) {
    if (!mkdir(LLM_SYNC_CACHE_DIR, 0775, true)) {
        fwrite(STDERR, "[llm-sync] 无法创建缓存目录 cache/llm/\n");
        exit(1);
    }
}

// dry-run：不拉取、不写任何文件；pullState 按磁盘 raw 可用性推定，演练合并并打印计数后退出
if ($dryRun) {
    llm_sync_log('dry-run: 跳过拉取，仅基于磁盘 raw 演练合并');
    $dryPullState = [
        'lmarena' => (is_file(LLM_SYNC_CACHE_DIR . '/raw-lmarena.json')) ? 'ok' : 'fail',
        'openrouter' => (is_file(LLM_SYNC_CACHE_DIR . '/raw-openrouter.json')) ? 'ok' : 'fail',
        'aa' => 'skipped',
    ];
    $dryMerge = llm_sync_merge($config, $dryPullState, true);
    if (!$dryMerge['ok']) {
        llm_sync_log('merge(dry-run): FAIL (' . $dryMerge['error'] . ')');
        exit(1);
    }
    $dum = $dryMerge['unmatched_counts'];
    $drySrc = [];
    foreach ($dryMerge['sources'] as $name => $s) {
        $drySrc[] = $name . '=' . ($s['ok'] ? 'ok' : 'fail');
    }
    llm_sync_log(
        'merge(dry-run): models=' . $dryMerge['models']
        . ' new_models=' . $dryMerge['new_models']
        . ' unmatched lmarena=' . $dum['lmarena'] . ' openrouter=' . $dum['openrouter'] . ' aa=' . $dum['aa']
        . ' sources ' . implode(' ', $drySrc)
        . '（未写快照）'
    );
    exit(0);
}

$fetchedAt = gmdate('c'); // UTC ISO8601，如 2026-08-31T01:00:00+00:00
$successCount = 0;
$sourceCount = 3;
// 本轮各源状态：ok / fail / skipped（skipped 不算成功也不算失败）——合并段的失败守卫与 sources 块要用
$pullState = ['lmarena' => 'fail', 'openrouter' => 'fail', 'aa' => 'fail'];

// 1) LMArena
$r = llm_sync_fetch_lmarena($config);
$pullState['lmarena'] = 'fail';
if ($r['ok']) {
    if (llm_sync_write_json('raw-lmarena.json', [
        'fetched_at' => $fetchedAt,
        'http_status' => $r['http_status'],
        'config' => 'text',
        'split' => 'latest',
        'rows' => $r['rows'],
    ])) {
        $pullState['lmarena'] = 'ok';
        $successCount++;
        llm_sync_log('lmarena: OK rows=' . count($r['rows']) . ' (http ' . $r['http_status'] . ') -> cache/llm/raw-lmarena.json');
    } else {
        llm_sync_log('lmarena: 拉取成功但 raw 写入失败，本轮按失败处理');
    }
} else {
    llm_sync_log('lmarena: FAIL (' . $r['error'] . ')，保留旧 raw 不覆盖');
}

// 2) OpenRouter
$r = llm_sync_fetch_openrouter($config);
$pullState['openrouter'] = 'fail';
if ($r['ok']) {
    if (llm_sync_write_json('raw-openrouter.json', [
        'fetched_at' => $fetchedAt,
        'http_status' => $r['http_status'],
        'data' => $r['data'],
    ])) {
        $pullState['openrouter'] = 'ok';
        $successCount++;
        llm_sync_log('openrouter: OK models=' . count($r['data']) . ' (http ' . $r['http_status'] . ') -> cache/llm/raw-openrouter.json');
    } else {
        llm_sync_log('openrouter: 拉取成功但 raw 写入失败，本轮按失败处理');
    }
} else {
    llm_sync_log('openrouter: FAIL (' . $r['error'] . ')，保留旧 raw 不覆盖');
}

// 3) Artificial Analysis
$r = llm_sync_fetch_aa($config);
$pullState['aa'] = 'fail';
if ($r['skipped']) {
    $pullState['aa'] = 'skipped';
    // show_aa=false 或无 key：写 skipped 形态，不请求上游
    if (llm_sync_write_json('raw-aa.json', [
        'fetched_at' => null,
        'http_status' => null,
        'skipped' => true,
        'reason' => 'disabled_or_no_key',
        'data' => [],
    ])) {
        $successCount++;
        llm_sync_log('aa: SKIPPED (disabled_or_no_key) -> cache/llm/raw-aa.json');
    }
} elseif ($r['ok']) {
    if (llm_sync_write_json('raw-aa.json', [
        'fetched_at' => $fetchedAt,
        'http_status' => $r['http_status'],
        'skipped' => false,
        'data' => $r['data'],
    ])) {
        $pullState['aa'] = 'ok';
        $successCount++;
        llm_sync_log('aa: OK models=' . count($r['data']) . ' (http ' . $r['http_status'] . ') -> cache/llm/raw-aa.json');
    } else {
        llm_sync_log('aa: 拉取成功但 raw 写入失败，本轮按失败处理');
    }
} else {
    llm_sync_log('aa: FAIL (' . $r['error'] . ')，保留旧 raw 不覆盖');
}

llm_sync_log('pull done: ' . $successCount . '/' . $sourceCount . ' sources ok');

// 拉取段退出码：全部源失败（skipped 不算失败）→ 1，方便 cron 监控；否则 0
$exitCode = $successCount === 0 ? 1 : 0;

if ($pullOnly) {
    exit($exitCode);
}

// 合并段（步骤 4）——dry-run 已在主流程开头短路退出，此处 $dryRun 恒为 false
$merge = llm_sync_merge($config, $pullState, $dryRun);
if (!$merge['ok']) {
    llm_sync_log('merge: FAIL (' . $merge['error'] . ')');
    exit(1);
}
$um = $merge['unmatched_counts'];
$srcOk = [];
foreach ($merge['sources'] as $name => $s) {
    $srcOk[] = $name . '=' . ($s['ok'] ? 'ok' : 'fail');
}
// P3：高排名未匹配模型告警（同步日志 WARNING + 独立 logs/llm-alerts.log）
if (!empty($merge['alerts'])) {
    foreach ($merge['alerts'] as $al) {
        llm_sync_log('WARNING: 高排名模型未匹配别名 lmarena rank=' . $al['rank']
            . ' rating=' . ($al['rating'] !== null ? round((float)$al['rating'], 1) : '?')
            . ' ' . $al['name'] . ' (' . $al['date'] . ') — 请尽快补 config/llm_model_aliases.php');
    }
    if (!$dryRun) {
        $fh = @fopen(LLM_SYNC_ALERT_LOG, 'ab');
        if ($fh !== false) {
            fwrite($fh, '# ' . date('Y-m-d H:i:s') . ' run' . "
");
            foreach ($merge['alerts'] as $al) {
                fwrite($fh, 'rank=' . $al['rank'] . "	" . $al['name'] . "	" . round((float)$al['rating'], 1) . "	" . $al['date'] . "
");
            }
            fclose($fh);
        }
    }
}
// 新增未匹配模型告警：AA/OpenRouter 的行没有智能分，卡不了排名阈值，
// 但「这轮新出现」不需要任何分数——V4.1 Flash 那次这样在 09-11 就会当场报出来。
if (!empty($merge['new_unmatched'])) {
    foreach ($merge['new_unmatched'] as $line) {
        $parts = array_pad(explode("\t", $line, 2), 2, '');
        llm_sync_log('WARNING: 新出现且无别名的上游模型 ' . $parts[0] . ' ' . $parts[1]
            . ' — 请补 config/llm_model_aliases.php');
    }
    if (!$dryRun) {
        $fh = @fopen(LLM_SYNC_ALERT_LOG, 'ab');
        if ($fh !== false) {
            fwrite($fh, '# ' . date('Y-m-d H:i:s') . " run (new-unmatched)\n");
            foreach ($merge['new_unmatched'] as $line) {
                fwrite($fh, "new\t" . $line . "\n");
            }
            fclose($fh);
        }
    }
} elseif (!empty($merge['unmatched_seeded'])) {
    llm_sync_log('unmatched: 首次运行，已建立基线（本轮不告警）');
}
llm_sync_log(
    ($dryRun ? 'merge(dry-run): ' : 'merge: ')
    . 'models=' . $merge['models']
    . ' new_models=' . $merge['new_models']
    . ' unmatched lmarena=' . $um['lmarena'] . ' openrouter=' . $um['openrouter'] . ' aa=' . $um['aa']
    . ' sources ' . implode(' ', $srcOk)
    . ($dryRun ? '（未写快照）' : ' -> cache/llm/' . LLM_SYNC_SNAPSHOT_FILE)
);

/* og:image 是唯一数据驱动的一张（卡片上写着「综合第一 X（N 分）」），快照一更新它就过期，
   所以在这里重新生成。但**不让它影响同步结果**：og 图生成失败不该让整次同步算失败，
   最坏只是分享卡片停在旧数据上。
   放在最末尾：前面的数据都已落盘，这一步纯粹是派生品。（dry-run 在前面就短路退出了。） */
if ($exitCode === 0) {
    $ogTool = __DIR__ . '/../tools/generate-og-image.php';
    if (is_file($ogTool)) {
        $ogOut = [];
        $ogRc = 0;
        /* 两张数据驱动的都要重画：榜单总卡（文案里写着「综合第一 X」）和每模型卡
           （写着各自的名次与分数）。静态的那几张不在参数里，不重画。 */
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ogTool) . ' llm-leaderboard models 2>&1', $ogOut, $ogRc);
        llm_sync_log('og: llm-leaderboard + models ' . ($ogRc === 0
            ? 'regenerated'
            : 'FAILED rc=' . $ogRc . ' — ' . implode(' / ', $ogOut)));
    }
}

exit($exitCode);
