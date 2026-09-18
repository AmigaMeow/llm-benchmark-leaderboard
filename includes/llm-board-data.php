<?php

function llm_board_models(array $models): array
{
    $clean = [];
    foreach ($models as $model) {
        if (!is_array($model) || !is_string($model['id'] ?? null) || $model['id'] === '') continue;
        // arena_ci_low/high 是 LMArena 给的评分置信区间（同步脚本从 rating_lower/rating_upper 取），
        // 只在展示层用（列内 ± 徽标），不参与排序/筛选；口径与其它数值字段一致（非数字/负数 → null）。
        foreach (['arena_score', 'arena_ci_low', 'arena_ci_high', 'aa_intelligence', 'aa_coding', 'aa_speed', 'aa_ttft', 'price_in', 'price_out', 'listed_at'] as $field) {
            $value = $model[$field] ?? null;
            $model[$field] = is_numeric($value) && is_finite((float)$value) && (float)$value >= 0 ? (float)$value : null;
        }
        $clean[] = $model;
    }
    return $clean;
}

function llm_board_state(array $query): array
{
    $view = is_string($query['view'] ?? null) ? $query['view'] : 'all';
    $weights = $query['weights'] ?? ($view === 'open' ? 'open' : 'all');
    $sort = $query['sort'] ?? ($view === 'cheap' ? 'price' : ($view === 'new' ? 'new' : 'intel'));
    return [
        // 图表视图有两个（pareto=价格轴 / speed=速度轴）。这里只放行图表视图，其余都归 'all'，
        // 于是导航高亮、筛选表单里的隐藏 view 字段都跟着走同一个判定。
        'view' => in_array($view, ['pareto', 'speed'], true) ? $view : 'all',
        'weights' => in_array($weights, ['all', 'open', 'closed', 'unknown'], true) ? $weights : 'all',
        // score/price 是旧口径（view 默认值与老链接仍在用），arena/pout 是它们的列头别名；
        // speed/ttft/pin/value 由列头点击产生，方向固定，见 llm_board_column_sort_compare
        'sort' => in_array($sort, ['intel', 'score', 'arena', 'speed', 'ttft', 'pin', 'pout', 'price', 'value', 'new'], true) ? $sort : 'intel',
        'q' => is_string($query['q'] ?? null) ? mb_substr(trim($query['q']), 0, 120) : '',
        // group：表的显示口径。model = 一个模型一行（默认，见 llm_board_group_models）——
        // 同一模型不再连着占好几行；tier = 每个推理档位各占一行（与 AA 同构），
        // 作为「想看档位明细」的次要视图保留（group=tier 属于筛选口径，不进索引）。
        'group' => in_array($query['group'] ?? null, ['tier', 'model'], true) ? (string)$query['group'] : 'model',
    ];
}

/** 列头排序键 → 列别名（老口径 score/price 与列头 arena/pout 同义，下拉框与表头高亮用列名）。 */
function llm_board_sort_alias(string $key): string
{
    return ['score' => 'arena', 'price' => 'pout'][$key] ?? $key;
}

/** 列头排序方向：分数/速度越高越前，延迟/价格越低越前，上架越新越前（与表头箭头一致）。 */
function llm_board_sort_direction(string $key): string
{
    return ['ttft' => 'asc', 'pin' => 'asc', 'pout' => 'asc', 'price' => 'asc'][$key] ?? 'desc';
}

function llm_board_score_compare(array $a, array $b): int
{
    return ((float)$b['arena_score'] <=> (float)$a['arena_score']) ?: strcmp((string)$a['id'], (string)$b['id']);
}

function llm_board_intel_compare(array $a, array $b): int
{
    $ai = $a['aa_intelligence'] ?? null;
    $bi = $b['aa_intelligence'] ?? null;
    if ($ai === null && $bi === null) {
        // 无智能指数时回落到 Arena，保证老快照/测试仍有确定顺序
        return llm_board_score_compare($a, $b);
    }
    if ($ai === null) return 1;
    if ($bi === null) return -1;
    if ((float)$bi !== (float)$ai) return (float)$bi <=> (float)$ai;
    $as = $a['arena_score'] ?? null;
    $bs = $b['arena_score'] ?? null;
    if ($as === null && $bs === null) return strcmp((string)$a['id'], (string)$b['id']);
    if ($as === null) return 1;
    if ($bs === null) return -1;
    return ((float)$bs <=> (float)$as) ?: strcmp((string)$a['id'], (string)$b['id']);
}

/** 评分/价格比 = Arena 分 ÷ 输出价（$/1M），与页面 $valueScore 同一口径：免费或缺失算不出比值。 */
function llm_board_value_score(array $row): ?float
{
    $score = $row['arena_score'] ?? null;
    $price = $row['price_out'] ?? null;
    if (!is_numeric($score) || !is_numeric($price)) return null;
    $score = (float)$score;
    $price = (float)$price;
    if (!is_finite($score) || !is_finite($price) || $price <= 0) return null;
    return $score / $price;
}

/** 列排序取值：value_score 现算，其余读字段；非数字/负数按缺失处理（与 llm_board_models 同口径）。 */
function llm_board_sort_value(array $row, string $field): ?float
{
    if ($field === 'value_score') return llm_board_value_score($row);
    $value = $row[$field] ?? null;
    return is_numeric($value) && is_finite((float)$value) && (float)$value >= 0 ? (float)$value : null;
}

/** 单列比较：缺失一律沉底；同值或两边都缺失返回 0，把顺序交回调用方的兜底比较器。 */
function llm_board_column_compare(array $a, array $b, string $field, string $direction): int
{
    $av = llm_board_sort_value($a, $field);
    $bv = llm_board_sort_value($b, $field);
    if ($av === null && $bv === null) return 0;
    if ($av === null) return 1;
    if ($bv === null) return -1;
    return $direction === 'asc' ? ($av <=> $bv) : ($bv <=> $av);
}

/** 列头排序分派：只有速度/延迟/输入价/评分价格比走这里，返回 0 表示用调用方的兜底顺序。 */
function llm_board_column_sort_compare(array $a, array $b, string $key): int
{
    $columns = [
        'speed' => ['aa_speed', 'desc'],
        'ttft'  => ['aa_ttft', 'asc'],
        'pin'   => ['price_in', 'asc'],
        'value' => ['value_score', 'desc'],
    ];
    if (!isset($columns[$key])) return 0;
    return llm_board_column_compare($a, $b, $columns[$key][0], $columns[$key][1]);
}

function llm_board_filter(array $models, array $state): array
{
    $models = llm_board_models($models);
    $models = array_values(array_filter($models, static function ($m) use ($state) {
        if (($m['arena_score'] ?? null) === null && ($m['aa_intelligence'] ?? null) === null) return false;
        $weights = $m['open_weights'] ?? null;
        if ($state['weights'] === 'open' && $weights !== true) return false;
        if ($state['weights'] === 'closed' && $weights !== false) return false;
        if ($state['weights'] === 'unknown' && $weights !== null) return false;
        $text = ($m['display_name'] ?? $m['id']) . ' ' . ($m['org'] ?? '');
        return $state['q'] === '' || mb_stripos($text, $state['q']) !== false;
    }));
    usort($models, static function ($a, $b) use ($state) {
        if ($state['sort'] === 'score' || $state['sort'] === 'arena') {
            return llm_board_score_compare($a, $b);
        }
        if ($state['sort'] === 'price' || $state['sort'] === 'pout') {
            $pa = $a['price_out'] ?? INF;
            $pb = $b['price_out'] ?? INF;
            if ($pa != $pb) return $pa <=> $pb;
        }
        if ($state['sort'] === 'new') {
            $delta = (int)($b['listed_at'] ?? 0) <=> (int)($a['listed_at'] ?? 0);
            if ($delta) return $delta;
        }
        // 列头排序：同值（或都缺失）时返回 0，继续走下面的智能指数兜底，保持顺序确定
        $column = llm_board_column_sort_compare($a, $b, $state['sort']);
        if ($column !== 0) return $column;
        return llm_board_intel_compare($a, $b);
    });
    return $models;
}

/** 推理强度档位白名单（与 scripts/sync-llm-leaderboard.php 的 effortSuffixes 一致）。 */
function llm_board_effort_labels(): array
{
    return ['max', 'xhigh', 'high', 'medium', 'low', 'non-reasoning', 'reasoning', 'adaptive', 'thinking'];
}

/**
 * 档位徽标文案：沿用 Artificial Analysis 自己的英文档位名（Max / XHigh / High …）。
 *
 * 这些是上游的档位专名，译成中文反而和 AA 页面对不上；i18n 缺键时也回落到这里，
 * 徽标不会露出中文。seed 里的 llm.effort_* 必须与这里逐字一致
 * （tests/llm-board-data.php 会校验）。
 */
function llm_board_effort_label_en(string $label): string
{
    $map = ['xhigh' => 'XHigh', 'non-reasoning' => 'Non-reasoning'];
    return $map[$label] ?? ucfirst($label);
}

/**
 * 「模型 × 推理强度档位」展开：AA 有多个档位实测值的模型，每档各占一行（与 AA 榜单同构）。
 *
 * - 只有 0/1 个档位时不标 effort：base slug 会被上游记成 max，单档模型标出来是误导。
 * - row_key 唯一（id 或 id#label），供 SSR/JS 稳定排序与 DOM 定位。
 * - effort_current 保留模型本体命中的档位（旧表口径），升降箭头只画在这一行。
 * - 行内 aa_* 全部换成该档位自己的实测值；价格/Arena 是模型级数据，同行共享。
 */
function llm_board_variant_rows(array $models): array
{
    $labels = llm_board_effort_labels();
    $rows = [];
    foreach (llm_board_models($models) as $model) {
        $id = (string)$model['id'];
        $current = (isset($model['aa_variant']) && is_string($model['aa_variant']) && $model['aa_variant'] !== '') ? $model['aa_variant'] : null;
        $variants = [];
        foreach ((array)($model['aa_variants'] ?? []) as $variant) {
            if (!is_array($variant)) continue;
            $label = isset($variant['label']) && is_string($variant['label']) ? $variant['label'] : '';
            if ($label === '' || !in_array($label, $labels, true)) continue;
            $metrics = ['intel' => null, 'coding' => null, 'speed' => null, 'ttft' => null];
            foreach (['intel', 'coding', 'speed', 'ttft'] as $key) {
                $value = $variant[$key] ?? null;
                $metrics[$key] = is_numeric($value) && is_finite((float)$value) && (float)$value >= 0 ? (float)$value : null;
            }
            if ($metrics['intel'] === null && $metrics['coding'] === null) continue;
            $variants[] = ['label' => $label] + $metrics;
        }
        if ($variants === []) {
            $row = $model;
            $row['effort'] = null;
            $row['effort_current'] = $current;
            $row['row_key'] = $id;
            $rows[] = $row;
            continue;
        }
        // 只有一档时不标强度：上游把 base slug 记成 max，单档模型标出来是误导（与 AA 一致）
        $labeled = count($variants) > 1;
        foreach ($variants as $variant) {
            $row = $model;
            foreach (['intel' => 'aa_intelligence', 'coding' => 'aa_coding', 'speed' => 'aa_speed', 'ttft' => 'aa_ttft'] as $src => $target) {
                // 多档位行只认本档位的实测值：没测就是空，不能继承 max 档的分数
                if ($labeled || $variant[$src] !== null) $row[$target] = $variant[$src];
            }
            $row['aa_variant'] = $labeled ? $variant['label'] : ($current ?? $variant['label']);
            $row['effort'] = $labeled ? $variant['label'] : null;
            $row['effort_current'] = $current;
            $row['row_key'] = $labeled ? $id . '#' . $variant['label'] : $id;
            $rows[] = $row;
        }
    }
    return $rows;
}

/** 行级排序键：指标降序，缺失沉底，同值时用智能指数、再 row_key 兜底（保证确定性）。 */
function llm_board_row_metric_compare(array $a, array $b, string $metric): int
{
    $av = $a[$metric] ?? null;
    $bv = $b[$metric] ?? null;
    if ($av === null && $bv === null) return llm_board_row_key_compare($a, $b);
    if ($av === null) return 1;
    if ($bv === null) return -1;
    if ((float)$av !== (float)$bv) return (float)$bv <=> (float)$av;
    if ($metric !== 'aa_intelligence') {
        $ai = $a['aa_intelligence'] ?? null;
        $bi = $b['aa_intelligence'] ?? null;
        if ($ai === null && $bi !== null) return 1;
        if ($bi === null && $ai !== null) return -1;
        if ($ai !== null && $bi !== null && (float)$ai !== (float)$bi) return (float)$bi <=> (float)$ai;
    }
    return llm_board_row_key_compare($a, $b);
}

function llm_board_row_key_compare(array $a, array $b): int
{
    return strcmp((string)($a['row_key'] ?? $a['id']), (string)($b['row_key'] ?? $b['id']));
}

/** 旧表口径的兜底排序：智能指数降序（两边都没有则回落 Arena），再 Arena，最后 row_key。 */
function llm_board_row_intel_compare(array $a, array $b): int
{
    $ai = $a['aa_intelligence'] ?? null;
    $bi = $b['aa_intelligence'] ?? null;
    if ($ai === null && $bi === null) return llm_board_row_metric_compare($a, $b, 'arena_score');
    if ($ai === null) return 1;
    if ($bi === null) return -1;
    if ((float)$ai !== (float)$bi) return (float)$bi <=> (float)$ai;
    return llm_board_row_metric_compare($a, $b, 'arena_score');
}

/** 与 llm_board_filter 同规则，但作用在「模型 × 档位」行上（榜表专用）。 */
function llm_board_filter_rows(array $models, array $state): array
{
    $rows = array_values(array_filter(llm_board_variant_rows($models), static function ($row) use ($state) {
        if (($row['arena_score'] ?? null) === null && ($row['aa_intelligence'] ?? null) === null) return false;
        $weights = $row['open_weights'] ?? null;
        if ($state['weights'] === 'open' && $weights !== true) return false;
        if ($state['weights'] === 'closed' && $weights !== false) return false;
        if ($state['weights'] === 'unknown' && $weights !== null) return false;
        $text = ($row['display_name'] ?? $row['id']) . ' ' . ($row['org'] ?? '') . ' ' . (string)($row['effort'] ?? '');
        return $state['q'] === '' || mb_stripos($text, $state['q']) !== false;
    }));
    usort($rows, static function ($a, $b) use ($state) {
        if ($state['sort'] === 'score' || $state['sort'] === 'arena') {
            return llm_board_row_metric_compare($a, $b, 'arena_score');
        }
        if ($state['sort'] === 'price' || $state['sort'] === 'pout') {
            $pa = $a['price_out'] ?? INF;
            $pb = $b['price_out'] ?? INF;
            if ($pa != $pb) return $pa <=> $pb;
        }
        if ($state['sort'] === 'new') {
            $delta = (int)($b['listed_at'] ?? 0) <=> (int)($a['listed_at'] ?? 0);
            if ($delta) return $delta;
        }
        // 与模型级同一套列头排序：评分/价格比是模型级字段，同模型各档位行数值相同
        $column = llm_board_column_sort_compare($a, $b, $state['sort']);
        if ($column !== 0) return $column;
        return llm_board_row_intel_compare($a, $b);
    });
    return $rows;
}

function llm_board_primary_metric(array $models): string
{
    foreach ($models as $model) {
        if (is_array($model) && isset($model['aa_intelligence']) && is_numeric($model['aa_intelligence']) && is_finite((float)$model['aa_intelligence'])) {
            return 'aa_intelligence';
        }
    }
    return 'arena_score';
}

function llm_board_metric_compare(array $a, array $b, string $metric): int
{
    $av = $a[$metric] ?? null;
    $bv = $b[$metric] ?? null;
    if ($av === null && $bv === null) return strcmp((string)$a['id'], (string)$b['id']);
    if ($av === null) return 1;
    if ($bv === null) return -1;
    return ((float)$bv <=> (float)$av) ?: strcmp((string)$a['id'], (string)$b['id']);
}

function llm_board_priced(array $models): array
{
    $primary = llm_board_primary_metric($models);
    return array_values(array_filter(llm_board_models($models), static function ($m) use ($primary) {
        return isset($m[$primary], $m['price_out'])
            && is_numeric($m[$primary]) && is_numeric($m['price_out'])
            && is_finite((float)$m[$primary]) && is_finite((float)$m['price_out'])
            && (float)$m['price_out'] > 0;
    }));
}

/**
 * 图表上可画的模型，按横轴口径取。
 * - price：横轴是输出价，需要 price_out > 0（贵贱对比没有 $0 的位置）
 * - speed：横轴是输出速度，需要 aa_speed > 0（没测过速的模型放不上速度轴）
 * 两个口径共用同一套「纵轴 = 主指标」判定，免得两边各自漏掉一种缺失。
 */
function llm_board_plottable(array $models, string $axis = 'price'): array
{
    if ($axis !== 'speed') return llm_board_priced($models);
    $primary = llm_board_primary_metric($models);
    return array_values(array_filter(llm_board_models($models), static function ($m) use ($primary) {
        return isset($m[$primary], $m['aa_speed'])
            && is_numeric($m[$primary]) && is_numeric($m['aa_speed'])
            && is_finite((float)$m[$primary]) && is_finite((float)$m['aa_speed'])
            && (float)$m['aa_speed'] > 0;
    }));
}

/**
 * 一个模型的推理档位分数：取所有带实测智能指数的档位，按分数升序。
 * 只用于价格视图 —— 在价格轴上这些点共享同一个横坐标（单价与推理档位无关），
 * 连出来是一段竖线，读法是「在这个价位上，换一档努力程度能拿到哪个分数区间」。
 *
 * 速度视图刻意不画档位：实测下来档位间的速度差中位数只占图宽 4%，也就是说除了
 * 极少数模型（glm-5.2 / gemini-flash 这类），连线几乎是竖的、并不比竖线多说什么，
 * 却在气泡间多出一堆说不清的细线。档位是价格视图的叙事，不是速度视图的。
 *
 * JS 侧的 tierScores() 必须与此完全一致（含排序）—— SSR 与 JS 重绘同形。
 */
function llm_board_tier_scores(array $model): array
{
    $rows = $model['aa_variants'] ?? null;
    if (!is_array($rows)) return [];
    $scores = [];
    foreach ($rows as $variant) {
        if (!is_array($variant) || !is_numeric($variant['intel'] ?? null)) continue;
        $scores[] = (float)$variant['intel'];
    }
    sort($scores);
    return $scores;
}

/** 档位竖线画不画得出来：至少两个档位分，且分数有跨度（同分连出来是零长度线段）。 */
function llm_board_tier_drawable(array $scores): bool
{
    return count($scores) >= 2 && max($scores) > min($scores);
}

/**
 * 图表气泡半径：按「面积」映射（半径取平方根，线性映射会把大值夸大成数倍面积），范围 [$rMin, $rMax]。
 * 两个视图都让「气泡越大 = 越大越好」，所以尺寸量 $unit 决定方向：
 * - 'speed'（价格视图）：越大越快
 * - 'price'（速度视图）：越大越便宜 —— 速度已经在横轴上，再拿它当尺寸就是重复编码
 *
 * $unit 同时决定「0」算不算有效值，这两件事必须分开判，否则会把图例说反：
 * - speed = 0 / 缺失 = 没测过 → 不参与映射，落到下界（不假装它快）
 * - price = 0 = 免费，是真实值 → 参与映射并拿到最大半径（最便宜的必须画成最大的点）
 *
 * 返回 ['r' => [id => 半径], 'lo' => 最小尺寸量, 'hi' => 最大尺寸量, 'count' => 参与映射的模型数]。
 * count 用来决定图例那颗范围提示渲不渲染 —— 一个数都没有时不该显示「14–22/s」。
 */
/* 气泡半径范围。2026-09-13 由 14–22 收到 10.5–16.5（×0.75）。
   起因：用户视口宽度（1157px，viewBox 677×399）下实测 30 个气泡有 44/435 对重叠，
   其中三对几乎完全重合（mimo-v2.5-pro ↔ kimi-k2.7-code 中心距 1.9，半径和 39.7）。
   这几对在数据上也几乎相同（速度差 1.1 tok/s、评分差 0.1），
   所以调坐标轴没有用（实测收紧 y 轴 0% 改善）——半径是唯一有效的杠杆：
   ×0.75 把重叠对从 44 降到 27。「越大越好」的相对编码不变，只是绝对尺度变小。 */
function llm_board_bubble_radii(array $points, string $unit, float $rMin = 10.5, float $rMax = 16.5): array
{
    $values = [];
    foreach ($points as $pm) {
        $raw = $unit === 'speed' ? ($pm['aa_speed'] ?? null) : ($pm['price_out'] ?? null);
        if (!is_numeric($raw) || !is_finite((float)$raw)) continue;
        $value = (float)$raw;
        if ($unit === 'speed' ? $value <= 0 : $value < 0) continue;
        $values[(string)$pm['id']] = $value;
    }
    /* 尺寸量跨数量级时按对数映射。价格跨 2.5 个数量级（$0.13–$50），线性映射会把
       绝大多数模型挤到「最便宜」那一端：实测 29 个气泡里 25 个顶到最大半径，
       尺寸编码等于没编码，而且每个图标都被撑满，整张图显得又大又糊。
       换成对数后顶格的降到 9 个。速度是一档可比量级（37–339/s），线性本就合适，不动。 */
    $useLog = $unit === 'price';
    $scaled = [];
    foreach ($values as $id => $value) {
        $scaled[$id] = $useLog ? log10(max($value, 1e-9)) : $value;
    }
    $lo = $scaled === [] ? 0.0 : (float)min($scaled);
    $hi = $scaled === [] ? 0.0 : (float)max($scaled);
    $radii = [];
    foreach ($points as $pm) {
        $id = (string)$pm['id'];
        if (!isset($scaled[$id]) || $hi <= $lo) { $radii[$id] = $rMin; continue; }
        $ratio = ($scaled[$id] - $lo) / ($hi - $lo);
        if ($unit === 'price') $ratio = 1.0 - $ratio;
        $radii[$id] = round($rMin + ($rMax - $rMin) * sqrt(max(0.0, min(1.0, $ratio))), 2);
    }
    /* 图例要显示的是原始量（$0.13–$50），不是取完对数的值 */
    return [
        'r' => $radii,
        'lo' => $values === [] ? 0.0 : (float)min($values),
        'hi' => $values === [] ? 0.0 : (float)max($values),
        'count' => count($values),
        /* 一并返回实际用到的上下界：页面与测试都从这里取，避免再抄一遍数字而漂移 */
        'rMin' => $rMin,
        'rMax' => $rMax,
    ];
}

function llm_board_frontier(array $models, string $axis = 'price'): array
{
    $primary = llm_board_primary_metric($models);
    $isSpeed = $axis === 'speed';
    // 前沿 = 「没有替代品同时更好」。两个轴同构，只是"更好"的横轴方向相反：
    //   price 轴 → 最小化输出价、最大化分数 → 按价格升序扫，取分数创新高处
    //   speed 轴 → 最大化速度、最大化分数 → 按速度降序扫，同样取分数创新高处
    // 两侧（本函数与 assets/js/llm-leaderboard.js 的 frontier）必须同口径，
    // 否则 SSR 画一条线、JS 重绘成另一条。
    $xKey = $isSpeed ? 'aa_speed' : 'price_out';
    $models = $isSpeed ? llm_board_plottable($models, 'speed') : llm_board_priced($models);
    usort($models, static function ($a, $b) use ($primary, $xKey, $isSpeed) {
        $cmp = ((float)$a[$xKey] <=> (float)$b[$xKey]);
        return ($isSpeed ? -$cmp : $cmp) ?: llm_board_metric_compare($a, $b, $primary);
    });
    $frontier = [];
    $best = -INF;
    $lastX = null;
    foreach ($models as $model) {
        $score = (float)$model[$primary];
        $x = (float)$model[$xKey];
        // 同坐标共享前沿；同价（同速）但更低分不算。
        if ($score > $best || ($score === $best && $x === $lastX)) {
            $frontier[] = $model;
            $best = $score;
            $lastX = $x;
        }
    }
    return $frontier;
}

/** 同族标色用的稳定哈希：与 assets/js/llm-leaderboard.js 的 familyHash 逐位一致（djb2，32 位回绕）。 */
function llm_board_family_hash(string $id): int
{
    $hash = 5381;
    $length = strlen($id);
    for ($i = 0; $i < $length; $i++) {
        $hash = (($hash * 33) ^ ord($id[$i])) & 0xFFFFFFFF;
    }
    return $hash;
}

/** 每族（同一模型）的行数：折叠视图要沿用展开时的档位数，族徽才写得对。 */
function llm_board_family_sizes(array $rows): array
{
    $sizes = [];
    foreach ($rows as $row) {
        $id = (string)($row['id'] ?? '');
        $sizes[$id] = ($sizes[$id] ?? 0) + 1;
    }
    return $sizes;
}

/**
 * 按模型折叠：同一个模型只留一行（默认不启用，见 llm_board_state 的 group）。
 *
 * 传入的已经是当前排序后的行，所以「该模型第一次出现的那一行」正好是当前列最优的那一档：
 * 列里的缺失值一律沉底，同值再由 row_intel_compare 把更强的一档排前面；Arena / 价格 / 上架
 * 是模型级字段，各档相同，于是代表档位也稳定落在更强的那档。代表的是哪一档由行上的档位
 * 徽标写明（徽标的悬浮说明还会点出档位名），折叠不会偷偷换一套数字还不说。
 */
function llm_board_group_models(array $rows): array
{
    $seen = [];
    $grouped = [];
    foreach ($rows as $row) {
        $id = (string)($row['id'] ?? '');
        if (isset($seen[$id])) continue;
        $seen[$id] = true;
        $grouped[] = $row;
    }
    return $grouped;
}

/**
 * 「上架」列的相对时间：≤0 天 → 今天；1 天 → 昨天；2–6 天 → {n} 天前；7–27 天 → {n} 周前；
 * 28–364 天 → {n} 个月前；≥365 天 → {n} 年前。
 *
 * $copy 是已翻译的 9 个键（today / yesterday / days / weeks / week / months / month / years /
 * year，见 scripts/i18n/llm-leaderboard/14-listed.php）；天档不会出现 1（1 天归「昨天」），
 * 所以只有周/月/年需要单数键（英语 1 week / 1 month / 1 year ago）。
 *
 * 传 $now 便于测试固定基准；与 assets/js/llm-leaderboard.js 的 listedText() 逐档一致
 * （tests/llm-board-data.cjs 用同一批时间戳对拍）。
 */
function llm_board_listed_text($ts, array $copy, ?int $now = null): string
{
    if (!is_numeric($ts) || (float)$ts <= 0) return '';
    $days = (int)floor((($now ?? time()) - (int)$ts) / 86400);
    if ($days <= 0) return (string)($copy['today'] ?? '');
    if ($days === 1) return (string)($copy['yesterday'] ?? '');
    if ($days < 7) {
        $unit = 'days';
        $count = $days;
    } elseif ($days < 28) {
        $unit = 'weeks';
        $count = (int)round($days / 7);
    } elseif ($days < 365) {
        $unit = 'months';
        $count = (int)round($days / 30);
    } else {
        $unit = 'years';
        $count = (int)round($days / 365);
    }
    $count = max(1, $count);
    $key = ($count === 1 && in_array($unit, ['weeks', 'months', 'years'], true)) ? substr($unit, 0, -1) : $unit;
    return str_replace('{n}', (string)$count, (string)($copy[$key] ?? ''));
}

/**
 * 同族标记：给「模型 × 推理档位」行补上族内坐标与族色，供 SSR/JS 渲染同一套标记。
 *
 * - 只在同一模型的行数 > 1 时成立：单档模型没有族（family_size = 1、family_color = 0），
 *   与 llm_board_variant_rows「单档不标 effort」同一口径。
 * - 族内名次按传入顺序数：排序变了名次跟着变，与表上名次一致，不另存一份快照。
 * - 族色按模型 id 稳定（同一模型永远是同一色），但相邻的两个多档族一定不同色 ——
 *   表里靠色条把散落在不同名次的同族行连起来，撞色就等于没标。6 色循环，
 *   33 个多档族里必然有非相邻的族同色，这是刻意的取舍。
 * - $sizes 可覆盖「每族几行」：按模型折叠后每族只剩一行，但族徽仍要写展开时的档位数。
 * - 纯函数：不改输入、不重排、不丢行（调用方能直接拿返回值渲染）。
 */
function llm_board_family_marks(array $rows, ?array $sizes = null): array
{
    $sizes = $sizes ?? llm_board_family_sizes($rows);
    // 相邻族邻接表：按行序去掉连续重复，得到「族序列」，相邻两项就是会挨着显示的两个族。
    // 只有多档族参与 —— 单档族不画色条，也就没有错色问题。
    $runs = [];
    $neighbors = [];
    foreach ($rows as $row) {
        $id = (string)($row['id'] ?? '');
        if ($sizes[$id] < 2) continue;
        if ($runs !== [] && end($runs) === $id) continue;
        $runs[] = $id;
    }
    foreach ($runs as $index => $id) {
        if ($index > 0) $neighbors[$id][$runs[$index - 1]] = true;
        if (isset($runs[$index + 1])) $neighbors[$id][$runs[$index + 1]] = true;
    }
    $colors = [];
    foreach ($rows as $row) {
        $id = (string)($row['id'] ?? '');
        if (isset($colors[$id])) continue;
        // 起点按模型 id 稳定（换个排序尽量不变色），再按首次出现顺序躲开「已定色的相邻族」：
        // 后出现的族一定躲得开先出现的，于是整个序列上相邻族不同色（6 色够用；真被占满则退回起点色）。
        $taken = [];
        foreach (array_keys($neighbors[$id] ?? []) as $other) {
            if (isset($colors[$other])) $taken[$colors[$other]] = true;
        }
        $color = (llm_board_family_hash($id) % 6) + 1;
        for ($step = 0; $step < 6 && isset($taken[$color]); $step++) $color = $color % 6 + 1;
        $colors[$id] = $color;
    }
    $marked = [];
    $seen = [];
    foreach ($rows as $row) {
        $id = (string)($row['id'] ?? '');
        $size = $sizes[$id];
        $seen[$id] = ($seen[$id] ?? 0) + 1;
        $row['family_size'] = $size;
        $row['family_rank'] = $seen[$id];
        $row['family_color'] = $size > 1 ? $colors[$id] : 0;
        $marked[] = $row;
    }
    return $marked;
}

/**
 * Arena 置信区间的半宽：max(high - score, score - low)。
 *
 * 两侧不对称时取更宽的一侧（区间只要有一边宽，名次就同样不可靠）。
 * 三项（score / low / high）齐全且半宽 > 0 才算数：缺 CI 的模型返回 null，
 * 由调用方决定「留空」，绝不能回落成 ±0 —— 0 会被读成「区间为零、名次很准」。
 * 与 assets/js/llm-leaderboard.js 的 ciHalf() 同一口径（tests/llm-board-data.cjs 对拍）。
 */
function llm_board_ci_half(array $row): ?float
{
    $score = $row['arena_score'] ?? null;
    $low = $row['arena_ci_low'] ?? null;
    $high = $row['arena_ci_high'] ?? null;
    if ($score === null || $low === null || $high === null) return null;
    $half = max((float)$high - (float)$score, (float)$score - (float)$low);
    return is_finite($half) && $half > 0 ? $half : null;
}

/**
 * 中位数（偶数个取中间两个的平均）。空集返回 null，调用方据此决定「不说话」。
 */
function llm_board_median(array $values): ?float
{
    $clean = [];
    foreach ($values as $value) {
        if (is_numeric($value) && is_finite((float)$value)) $clean[] = (float)$value;
    }
    if ($clean === []) return null;
    sort($clean, SORT_NUMERIC);
    $count = count($clean);
    $mid = intdiv($count, 2);
    if ($count % 2 === 1) return $clean[$mid];
    return ($clean[$mid - 1] + $clean[$mid]) / 2;
}

/**
 * 榜级分辨率：这一屏的 Arena 分到底能分辨多大差距。
 *
 * 不看单个名次，只回答一个问题：「差几分才算真差」。带 CI 的模型太少（< 5）或
 * 相邻对凑不满 3 组时返回 null —— 样本不够就什么都不说，而不是给一个假结论。
 *
 * - 同一模型的多个档位行只算一次：档位行的分数与区间是模型级复制，重复行会让
 *   「相邻间隔」的中位数压向 0，把结论说反（按档位视图一模型多行）。
 * - 相邻 = 按 Arena 分降序排列后的前后两行（与表内名次排序无关，换列排序不改这个事实）。
 *   同分保持传入顺序（PHP 8 与本项目支持的 JS 引擎排序都稳定）。
 * - 「可比」要求两侧都有区间；{overlap} 只统计可比对，缺 CI 的对既不加重也不减轻结论。
 * - 返回的 half/gap 是原始浮点，格式化交给调用方（SSR 用 number_format，JS 用 toLocaleString）。
 */
function llm_board_ci_resolution(array $rows): ?array
{
    $halves = [];
    $scored = [];
    $seen = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $id = (string)($row['id'] ?? $row['row_key'] ?? '');
        if ($id === '' || isset($seen[$id])) continue;
        $seen[$id] = true;
        $half = llm_board_ci_half($row);
        if ($half !== null) $halves[] = $half;
        $score = $row['arena_score'] ?? null;
        if ($score === null || !is_numeric($score)) continue;
        $scored[] = ['score' => (float)$score, 'row' => $row];
    }
    if (count($halves) < 5) return null;
    usort($scored, static fn($a, $b) => $b['score'] <=> $a['score']);
    $pairs = 0;
    $overlap = 0;
    $gaps = [];
    $count = count($scored);
    for ($i = 1; $i < $count; $i++) {
        $prev = $scored[$i - 1];
        $cur = $scored[$i];
        $gaps[] = $prev['score'] - $cur['score'];
        if (llm_board_ci_half($prev['row']) === null || llm_board_ci_half($cur['row']) === null) continue;
        $pairs++;
        if ((float)$prev['row']['arena_ci_low'] <= (float)$cur['row']['arena_ci_high']
            && (float)$cur['row']['arena_ci_low'] <= (float)$prev['row']['arena_ci_high']) {
            $overlap++;
        }
    }
    if ($pairs < 3) return null;
    return [
        'models' => count($halves),
        'pairs' => $pairs,
        'overlap' => $overlap,
        'half' => (float)llm_board_median($halves),
        'gap' => (float)llm_board_median($gaps),
    ];
}

/**
 * 「低样本」提醒的阈值：Arena 票数低于它，名次对照只当参考。
 * 定义在这里而不是散在渲染里 —— Arena 格的悬浮说明、行内明细、JS 都用这一条。
 */
function llm_board_low_votes_threshold(): int
{
    return 20000;
}

/**
 * 行内明细（窄屏/触屏按需展开的那块）里该出现哪些事实。
 *
 * 只判断「有什么」，不做格式化也不做翻译：日期/区间/票数原样给渲染层，句子由各自用
 * 本地化数字与自己的文案拼（SSR 用 number_format，JS 用 toLocaleString）。这样 SSR 与 JS
 * 不会出现「这行有明细、那行没有」的分歧 —— 那才是真正会露到页面上的漂移。
 *
 * - 区间：三项齐全且半宽 > 0 才给（缺 CI 不给一行空区间）
 * - 票数：有就给；低于阈值再加一条「票数较少」的提醒（与 Arena 悬浮说明同一句）
 * - 名次：只有真的升/降才给（新上榜与无变化都不给 —— 排名数字下面多一行碎字没意义）
 * - 档位：只在「按档位」视图里、且这行确实带档位时给一句档位说明
 * 返回 null = 这行没有可展开的内容：不画按钮，也不画空的明细行。
 */
function llm_board_row_facts(array $row, ?array $delta = null, bool $effortRow = false): ?array
{
    $votes = $row['arena_votes'] ?? null;
    $votes = is_numeric($votes) && is_finite((float)$votes) && (float)$votes >= 0 ? (int)round((float)$votes) : null;
    $change = 0;
    if ($delta !== null && empty($delta['isNew']) && (int)($delta['d'] ?? 0) !== 0) $change = (int)$delta['d'];
    $facts = [
        'date' => ((string)($row['listed_at_iso'] ?? '')) !== '' ? (string)$row['listed_at_iso'] : null,
        'ci_low' => null,
        'ci_high' => null,
        'votes' => $votes,
        'low_votes' => $votes !== null && $votes < llm_board_low_votes_threshold(),
        'rank_delta' => $change,
        // 基线日期与名次变动一起给：渲染层拼成「2026-09-04 ↑3」这种不依赖语言的写法，
        // 免得 SSR（llm.rank_up_tip）与 JS（llm.js.rank_up_tip）用两句措辞不同的译文
        'rank_date' => $change === 0 ? null : (string)($delta['date'] ?? ''),
        'effort' => $effortRow,
    ];
    if (llm_board_ci_half($row) !== null) {
        $facts['ci_low'] = (float)$row['arena_ci_low'];
        $facts['ci_high'] = (float)$row['arena_ci_high'];
    }
    if ($facts['date'] === null && $facts['ci_low'] === null && $facts['votes'] === null && $change === 0 && !$effortRow) return null;
    return $facts;
}


