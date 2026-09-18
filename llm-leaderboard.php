<?php
/**
 * 大模型排行榜
 *
 * 数据：cache/llm/llm-leaderboard.json（scripts/sync-llm-leaderboard.php 每日生成）
 * 扁平语言文件 i18n；site_url/site_name 由 config/site.php 驱动。
 */

// 加载i18n系统
require_once(__DIR__ . '/includes/i18n.php');
require_once(__DIR__ . '/includes/settings.php');
$siteUrl = rtrim((string)SiteSettings::getSiteUrl(), '/');
$siteName = SiteSettings::getSiteName();
require_once __DIR__ . '/includes/llm-board-data.php';
$i18n = I18n::getInstance();

if (!defined('LLM_ICON_BASE')) {
    define('LLM_ICON_BASE', '/assets/llm/icons/'); // P1: 自托管 vendor 图标（原 unpkg 第三方 CDN）
}
// CSS/JS 缓存版本号：两处必须同一常量（此前只改其一，导致 JS 停在旧 URL 被 Cloudflare 长期缓存）
if (!defined('LLM_ASSET_VER')) {
    define('LLM_ASSET_VER', '2026091140');
}
$llmAliases = require __DIR__ . '/config/llm_model_aliases.php';

// SEO / 多语言
$currentLang = $i18n->getCurrentLanguage();
// 动态年份：title/H1/description 里的年份随自然年自动切换（2026→2027，不发版）
$seoYear = (int) date('Y');
// 基础 URL（og/schema 用）
$llmBaseUrl = $siteUrl . '/llm-leaderboard.php';

// ---------------------------------------------------------------------------
// 视图（8d 筛选可收录）：
// - 合法可收录 view = all（默认，可省略）| open | code | cheap | new，各自自指 canonical（含 view）
// - intel 保留可点/可改 URL，但 noindex,follow，canonical 指回无 view 主页
// - 其余未知查询参数维持全站 noindex 规则
// ---------------------------------------------------------------------------
$llmViews = ['all', 'open', 'code', 'cheap', 'new', 'intel', 'pareto', 'speed'];
$rawView = is_string($_GET['view'] ?? null) ? $_GET['view'] : '';
$view = in_array($rawView, $llmViews, true) ? $rawView : 'all';
$indexableViews = ['all', 'open', 'code', 'cheap', 'new', 'pareto', 'speed'];
$extraQuery = array_diff(array_keys($_GET), ['lang', 'view']);
$llmNoindex = ($rawView !== '' && !in_array($rawView, $indexableViews, true)) || !empty($extraQuery);

// 自指 canonical：合法 view 带 view；intel/非法 view/额外参数 → 无 view 主页
$canonicalUrl = $llmBaseUrl;
if (!$llmNoindex && in_array($view, ['open', 'code', 'cheap', 'new', 'pareto', 'speed'], true)) {
    $canonicalUrl = $llmBaseUrl . '?view=' . $view;
}
// P0: zh-CN（默认语言）canonical 用裸 URL，与 hreflang zh-CN / x-default 保持一致
$curLang = i18n_lang();
$selfCanonicalUrl = ($curLang === '' || $curLang === 'zh-CN') ? $canonicalUrl : i18n_localized_url($canonicalUrl);

// hreflang 带当前 view：zh-CN 用裸 ?view=open，其余语言由 helper 追加 &lang=
$hreflangBase = $llmBaseUrl;
if (in_array($view, ['open', 'code', 'cheap', 'new', 'intel', 'pareto', 'speed'], true)) {
    $hreflangBase = $llmBaseUrl . '?view=' . $view;
}

// 文案回退：避免占位翻译进 SERP（抄 raid-calculator.php:14-20 的 $raid_seo 模式）
// $params 透传给 __()；fallback 与译文里的 {year} 占位统一替换为当前年，不用发版
$llm_seo = function ($key, $fallback, $params = []) use ($seoYear, $siteName) {
    $t = __($key, $params);
    if ($t === $key || $t === '' || preg_match('/^(常见问题|功能特性|FAQ |Feature |OG |SEO|Schema |페이지|제목)/u', $t)) {
        // 回落到内联文案时，占位参数也要替换，否则会原样输出 {count} 之类到页面上
        foreach ($params as $k => $v) {
            $fallback = str_replace('{' . $k . '}', (string) $v, $fallback);
        }
        $t = $fallback;
    }
    return str_replace(['{year}', '17NAS'], [(string) $seoYear, $siteName], $t);
};

// 每个 view 独立 title/H1/description/keywords（步骤 7：全部走 llm.* i18n 键 + 中文回落）
// 口径：title/H1 不带年份前缀；description 里的 {year} 由 $llm_seo 传入 date('Y')
$llmViewSeo = [
    'all' => [
        'title' => ['llm.seo_title_all', '大模型排行榜 - 开源、编程与价格对比 | 17NAS'],
        'h1'    => ['llm.h1_all', '大模型排行榜'],
        'desc'  => ['llm.seo_desc_all', '大模型排行榜与测评对照，覆盖全球与国产模型、开源权重、编程向筛选和 API 价格。每日更新（{year}），不自造总分。'],
    ],
    'open' => [
        'title' => ['llm.seo_title_open', '开源大模型排行榜 | 17NAS'],
        'h1'    => ['llm.h1_open', '开源大模型排行榜'],
        'desc'  => ['llm.seo_desc_open', '开源大模型排行榜，对照全球开源权重模型的排名与价格，含 DeepSeek、Qwen、Kimi、GLM 等，可本机或私有部署。每日更新（{year}）。'],
    ],
    'code' => [
        'title' => ['llm.seo_title_code', '编程大模型排行榜 | 17NAS'],
        'h1'    => ['llm.h1_code', '编程大模型排行榜'],
        'desc'  => ['llm.seo_desc_code', '编程大模型排行榜。本表暂无独立代码基准，编程视图按综合排名排序。每日更新（{year}）。'],
    ],
    'cheap' => [
        'title' => ['llm.seo_title_cheap', '大模型性价比排行榜 | 17NAS'],
        'h1'    => ['llm.h1_cheap', '大模型性价比排行榜'],
        'desc'  => ['llm.seo_desc_cheap', '大模型性价比排行榜，按输出价从低到高对照排名与是否开源。价格为 OpenRouter 平台报价。每日更新（{year}）。'],
    ],
    'pareto' => [
        'title' => ['llm.seo_title_pareto', '大模型性价比图：哪个模型最值 | 17NAS'],
        'h1'    => ['llm.h1_pareto', '大模型性价比图'],
        'desc'  => ['llm.seo_desc_pareto', '大模型性价比前沿图：横轴为 OpenRouter 输出价（对数刻度），纵轴为 LMArena Arena 评分。绿线上的模型在同等价格下没有更强的替代，是一分钱一分货的最优解。每日更新（{year}）。'],
    ],
    'new' => [
        'title' => ['llm.seo_title_new', '最新大模型发布与价格 | 17NAS'],
        'h1'    => ['llm.h1_new', '最新大模型'],
        'desc'  => ['llm.seo_desc_new', '近期上架的大模型与价格，按上架时间排序。每日更新（{year}）。'],
    ],
    /* 速度视图的自指 canonical/描述都写「速度」，不写「价格」——两个视图共用一张 SVG 结构，
       但横轴是两件完全不同的事，描述串混用会让 SERP 摘要与实际页面不符。 */
    'speed' => [
        'title' => ['llm.seo_title_speed', '大模型速度榜：评分与输出速度对照 | 17NAS'],
        'h1'    => ['llm.h1_speed', '大模型速度图'],
        'desc'  => ['llm.seo_desc_speed', '大模型速度图：横轴为实测输出速度（tokens/秒，线性刻度），纵轴为智能指数。越靠右上代表「又快又强」，气泡越大代表输出价越低。每日更新（{year}）。'],
    ],
];
// intel 不在可收录 view 里（noindex），用 all 的键
$vs = $llmViewSeo[$view] ?? $llmViewSeo['all'];
$vsH1 = $llm_seo($vs['h1'][0], $vs['h1'][1]);

$yearParams = ['year' => $seoYear];
$pageTitle = $llm_seo($vs['title'][0], $vs['title'][1], $yearParams);
// keywords 全视图共用一个键（无 {year} 占位）
$seoKeywords = $llm_seo('llm.seo_keywords', '大模型排行榜,AI大模型排行榜,大模型测评,大模型天梯,开源大模型排行榜,编程大模型,大模型性价比,国产大模型排名,哪个大模型好用,LLM排行榜,开源模型盘点,全球大模型排名');
$siteDescription = $llm_seo($vs['desc'][0], $vs['desc'][1], $yearParams);
$ogTitle = $pageTitle;
$ogDescription = $siteDescription;
$twitterTitle = $ogTitle;
$twitterDescription = $ogDescription;
/* og:image 必须是绝对地址。版本号取图片自己的 mtime 而不是全站 ASSET_VERSION ——
   换一次 CSS 就让各家抓取器重抓一遍大图没有意义。
   图片由 tools/generate-og-image.php 生成：它是唯一数据驱动的一张，句子随快照变化，
   所以每次同步后重新生成（见 scripts/sync-llm-leaderboard.php 末尾）。 */
require_once __DIR__ . '/includes/og-image.php';
$ogImage = og_image_url('llm-leaderboard');
$pageTitle = seo_normalize_title($pageTitle, $vs['title'][1], $currentLang);

// JSON-LD 安全编码
$j = function ($v) {
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};

// FAQ（步骤 7：走 llm.* i18n 键；已删「标题年份」一问——title 不再以年份开头）
// {link} 可见区替换为内链，JSON-LD 替换为纯文本链接标签
$faqItems = [
    [
        'q' => ['llm.faq_q1', '现在哪个大模型最强？'],
        'a' => ['llm.faq_a1', '以本表综合排名为准，接近时再看价格和是否开源，没有单一最强。'],
    ],
    [
        'q' => ['llm.faq_q2', '开源大模型和闭源有什么区别？'],
        'a' => ['llm.faq_a2', '开源权重可本机或私有部署；闭源一般走 API。开源列表见 {link}。'],
        'link' => ['llm.faq_link_open', '开源大模型排行榜', '/llm-leaderboard.php?view=open'],
    ],
    [
        'q' => ['llm.faq_q3', '写代码该看哪个大模型？'],
        'a' => ['llm.faq_a3', '专业代码榜和本表不是同一套题。编程向筛选见 {link}，当前按综合分排序。'],
        'link' => ['llm.faq_link_code', '编程大模型排行榜', '/llm-leaderboard.php?view=code'],
    ],
    [
        'q' => ['llm.faq_q4', '现在国产最强的大模型是谁？'],
        'a' => ['llm.faq_a4', '没有单一最强。国产开源可看 {link}，接近时再看价格和能否本机部署。'],
        'link' => ['llm.faq_link_open', '开源大模型排行榜', '/llm-leaderboard.php?view=open'],
    ],
    [
        'q' => ['llm.faq_q5', '价格为什么和官网不同？'],
        'a' => ['llm.faq_a5', '表内是 OpenRouter 美元/百万 token，可能与官方 API 或国内中转不同。'],
    ],
];
$faqResolved = [];
foreach ($faqItems as $item) {
    $q = $llm_seo($item['q'][0], $item['q'][1]);
    $a = $llm_seo($item['a'][0], $item['a'][1]);
    if ($q === '' || $a === '') continue;
    if (isset($item['link'])) {
        $label = $llm_seo($item['link'][0], $item['link'][1]);
        $aHtml = str_replace('{link}', '<a href="' . htmlspecialchars($item['link'][2], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>', $a);
        $aText = str_replace('{link}', $label, $a);
    } else {
        $aHtml = $a;
        $aText = $a;
    }
    $faqResolved[] = ['q' => $q, 'a' => $aText, 'a_html' => $aHtml];
}
$faqItems = $faqResolved;

$ogLocaleMap = [
    'zh-CN' => 'zh_CN', 'zh-TW' => 'zh_TW', 'en-US' => 'en_US',
    'ja-JP' => 'ja_JP', 'ko-KR' => 'ko_KR',
];
$ogLocale = $ogLocaleMap[$currentLang] ?? 'zh_CN';

// ---------------------------------------------------------------------------
// 快照读取：不可读或 JSON 坏 → $snapshot = null，表格区输出失败文案，不空表装成功
// ---------------------------------------------------------------------------
$snapshot = null;
$snapshotFile = __DIR__ . '/cache/llm/llm-leaderboard.json';
if (is_readable($snapshotFile)) {
    $decoded = json_decode((string)file_get_contents($snapshotFile), true);
    if (is_array($decoded) && isset($decoded['models']) && is_array($decoded['models'])) {
        $decoded['models'] = llm_board_models($decoded['models']);
        $snapshot = $decoded;
    }
}

// 已评分 / 未评分拆分：
// 排行榜表格只放有 Arena 评分的模型（有名次才配叫排名）；LMArena 尚未收录的新模型
// 单列到表格下方的「尚未进入 Arena 排名」区块，按上架时间倒序，收录后自动升入表内。
$rankedModels = [];
$unrankedModels = [];
if ($snapshot !== null) {
    foreach ($snapshot['models'] as $m) {
        if (!is_array($m)) continue;
        if (($m['arena_score'] ?? null) === null && ($m['aa_intelligence'] ?? null) === null) $unrankedModels[] = $m; // 两者皆无才沉底；仅有智能指数的模型留在表内
        else $rankedModels[] = $m;
    }
    // 未评分区块按上架时间倒序（最新的在前）
    usort($unrankedModels, static function ($a, $b) {
        $ta = (int)($a['listed_at'] ?? 0);
        $tb = (int)($b['listed_at'] ?? 0);
        if ($ta === $tb) return strcmp((string)$a['id'], (string)$b['id']);
        return $tb <=> $ta;
    });
}

$cmpArenaDesc = 'llm_board_score_compare';
$boardState = llm_board_state($_GET);
$ssrModels = llm_board_filter($rankedModels, $boardState);
// 榜表按「模型 × 推理强度档位」逐行渲染（AA 同构）；图表/分享图/摘要仍走模型级 $ssrModels
$ssrRowSource = llm_board_filter_rows($rankedModels, $boardState);
// 族徽上的档位数按「展开后」的行数算：按模型折叠时每族只剩一行，但徽标仍要写 ×N 档位
$ssrFamilySizes = llm_board_family_sizes($ssrRowSource);
if ($boardState['group'] === 'model') $ssrRowSource = llm_board_group_models($ssrRowSource);
// 同族标记：同一模型的多个档位行共用一个族色 + 族内名次（只加渲染字段，不参与排序）
$ssrRows = llm_board_family_marks($ssrRowSource, $ssrFamilySizes);
// 榜级分辨率：这一屏的 Arena 分能分辨多大差距（中位半宽 / 相邻中位分差 / 相邻对重叠比）。
// 刻意不做逐行「≈」标记 —— 真实快照上按 Arena 排序时 40/41 组相邻对全部落在彼此区间内，
// 逐行标会把 41 行（76%）挂满标记并连成一条巨带，那是当年「低样本」旗标一样的噪音。
// 一屏说一次才有信息量；算的是「本屏」（当前筛选后的行集合），换筛选/口径后 JS 用同一份
// 模板与同一套口径（llm_board_ci_resolution ↔ data.ciResolution）重算这一句。
$ciResolutionTemplate = $llm_seo('llm.ci_resolution', '本屏 Arena 分中位浮动 ±{half} 分，相邻模型中位只差 {gap} 分（{pairs} 组相邻里 {overlap} 组落在彼此区间内）：差距小于 {half} 分时，名次先后说明不了什么。');
$ciResolution = llm_board_ci_resolution($ssrRows);
$ciResolutionText = '';
if ($ciResolution !== null) {
    $ciResolutionText = str_replace(
        ['{half}', '{gap}', '{overlap}', '{pairs}'],
        [number_format($ciResolution['half'], 1), number_format($ciResolution['gap'], 1), (string)$ciResolution['overlap'], (string)$ciResolution['pairs']],
        $ciResolutionTemplate
    );
}
$orgIcons = [
    'openai' => 'openai', 'anthropic' => 'anthropic', 'google' => 'gemini',
    'deepseek' => 'deepseek', 'alibaba' => 'qwen', 'qwen' => 'qwen',
    'moonshot ai' => 'moonshot', 'moonshot' => 'moonshot', 'kimi' => 'moonshot',
    'z.ai' => 'zhipu', 'zhipu' => 'zhipu', 'meta' => 'meta',
    'xai' => 'xai', 'minimax' => 'minimax', 'tencent' => 'tencent', 'bytedance' => 'doubao',
    'xiaomi' => 'xiaomi', 'mistral' => 'mistral',
    /* 快照里的 org 写作「Z AI」（空格），而上面那行是 'z.ai'（点），
       strtolower 后是 'z ai' 匹配不上 —— 现在那 2 个模型在别名表里都显式给了图标，
       所以看不出问题；但新模型进来没进别名表时就会退化成一个字母徽标。 */
    'z ai' => 'zhipu',
];
foreach (($snapshot['models'] ?? []) as $model) {
    $id = (string)$model['id'];
    $slug = $llmAliases[$id]['icon'] ?? $orgIcons[strtolower((string)($model['org'] ?? ''))] ?? '';
    if (preg_match('/^[a-z0-9-]+$/', $slug) && is_file(__DIR__ . LLM_ICON_BASE . $slug . '.svg')) {
        $llmAliases[$id]['icon'] = $slug;
    }
}

// 信息条：3 个独立芯片（不输出 "Last Update: ... UTC" 日志句子）
$metaUpdated = '—';
$snapshotTime = null;
if ($snapshot !== null && !empty($snapshot['generated_at'])) {
    $ts = strtotime((string)$snapshot['generated_at']);
    if ($ts) {
        $snapshotTime = $ts;
        $metaUpdated = $currentLang === 'en-US'
            ? date('M j', $ts)
            : date('n月j日', $ts);
    }
}
// generated_at 超过 72 小时视为更新延迟
$metaStaleHidden = ($snapshotTime !== null && (time() - $snapshotTime) > 72 * 3600) ? '' : 'hidden';
// 仅核心数据源失败时提示；未配置的可选来源不视为同步异常。
$syncDegraded = false;
if ($snapshot !== null && !empty($snapshot['sources']) && is_array($snapshot['sources'])) {
    foreach (['lmarena', 'openrouter'] as $srcKey) {
        $src = $snapshot['sources'][$srcKey] ?? null;
        if (!is_array($src) || ($src['ok'] ?? false) !== true) {
            $syncDegraded = true;
            break;
        }
    }
}
// 芯片显示表内（已评分）模型数，与用户实际看到的行数一致
$metaCount = $snapshot !== null ? (string)count($rankedModels) : '—';

/* 这里删除过两块东西，记下原因，免得被重新加回来：
   1) 一张「当前视图芯片」（如 pareto 的「53 个模型已标价」）。它和 #llm-results-count
      说的是同一件事，而后者由 SSR 渲染、带 role="status"、筛选后实时重算 ——
      同时吐出两个计数，JS 还得隐掉一个，形成一次可见的闪现。
   2) 喂给「各 view 简介」的 $viewCounts 计数表。它唯一的消费者是 $viewIntro，
      而 $viewIntro 全仓库无人读取：页面上的 <p id="llm-intro"> 一直渲染的是
      llm.ui.intro / llm.ui.chart_intro 这类通用句，JS 走的也是同一份。
      没有出口的计数不必维护，它和 llm.intro_* 一起成了遗留。
   将来如果要按视图写不同简介，请接到 $llmMetaForJs['intro']（JS 切换视图真的会读它），
   而不是只把 $viewCounts 复活。 */

/* 图表横轴口径：价格视图 vs 速度视图是同一张图的两套横轴。
   这里定一次，SSR 与序列化给 JS 的 window.LLM_CHART_AXIS 都读它，两边不会各判一套。
   $isChartView 同理只写一次 —— 页面上有七处「是不是图表视图」的判断（宽版布局、简介句、
   隐藏表格、结果计数、图表区块本身…），各写各的迟早会有一处漏掉新视图。 */
$isChartView = in_array($view, ['pareto', 'speed'], true);
$chartAxis = $view === 'speed' ? 'speed' : 'price';

// 胶囊链接：无 JS 时 <a href> 直接打开 SSR 筛选页；lang 非 zh-CN 时透传
$pillHref = static function ($v) use ($currentLang) {
    $qs = [];
    if ($v !== 'all') $qs[] = 'view=' . rawurlencode($v);
    if ($currentLang !== '' && $currentLang !== 'zh-CN') $qs[] = 'lang=' . rawurlencode($currentLang);
    return '/llm-leaderboard.php' . ($qs ? '?' . implode('&', $qs) : '');
};

// 展示格式化
$esc = static function ($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};
// 价格 $/1M：缺失 —，免费 0，其余按量级保留合理小数
$fmtPrice = static function ($v) {
    if ($v === null) return '—';
    if ((float)$v === 0.0) return '0';
    if ($v >= 100) return '$' . number_format((float)$v, 0);
    if ($v >= 1) return '$' . number_format((float)$v, 2);
    return '$' . rtrim(rtrim(number_format((float)$v, 4, '.', ''), '0'), '.');
};
// 智能指数列显示条件：全部模型 aa_intelligence 均为 null 时不输出该列（SSR 与 JS 同步）
$hasIntel = false;
if ($snapshot !== null) {
    foreach ($snapshot['models'] as $m) {
        if (isset($m['aa_intelligence']) && $m['aa_intelligence'] !== null) {
            $hasIntel = true;
            break;
        }
    }
}
// 条形图线性归一基准（相对本表最高值，禁止 CoreMark 分段刻度）
// 移动端卡片列标签：thead 在移动端被隐藏，由 data-label + CSS ::before 输出（随语言走）
$colLabels = [
    'arena' => 'Arena',
    'intel' => $llm_seo('llm.col_intel', '智能指数'),
    'speed' => $llm_seo('llm.col_speed', '速度'),
    'ttft'  => $llm_seo('llm.col_ttft', '延迟'),
    'pin'   => $llm_seo('llm.col_pin', '输入价'),
    'pout'  => $llm_seo('llm.col_pout', '输出价'),
    'date'  => $llm_seo('llm.col_date', '上架'),
    'value' => $llm_seo('llm.ui.ratio', '评分/价格比'),
    'valueFree' => $llm_seo('llm.value_free', '免费'),
];

// 移动端指标面板的一格：标签在上、数值在下，缺值传 '—'（自动淡显、无需额外标志位）。
// 桌面专项列在 ≤600px 收起，这份面板承载同一批数值，顺序与表头一致；SSR 与 JS 同一套 class。
$metricTile = static function (string $label, string $value) use ($esc): string {
    return '<span class="llm-mobile-metric"><span class="llm-mobile-metric-label">' . $esc($label) . '</span>'
        . '<span class="llm-mobile-metric-value' . ($value === '—' ? ' is-empty' : '') . '">' . $esc($value) . '</span></span>';
};
$fmtMetric = static function ($v, int $decimals): string {
    return $v === null ? '—' : number_format((float)$v, $decimals);
};
// 智能指数用 AA 的原始分：SSR 与 JS 都按原值输出（一个数字不能有 51 / 51.0 两副面孔）
$fmtIntel = static function ($v): string {
    return $v === null ? '—' : (string)(float)$v;
};

// ---- 列头排序（可点击 / 粘性）与主指标（放大 + 归一化对比条）------------------------------
// 可排序列：下拉框与表头共用一套键，标签复用 $colLabels，不再多种一份翻译。
// 老口径 score/price 是同义键（view=cheap / 老链接仍会带），统一映射到列名用于高亮与下拉回显。
$sortAlias = llm_board_sort_alias($boardState['sort']);
// 没有智能指数列的老快照里，默认排序键 intel 实际按 Arena 兜底：高亮落到 Arena 列，
// 避免出现「整表在排序，却没有一列表头被点亮」。
if (!$hasIntel && $sortAlias === 'intel') $sortAlias = 'arena';
$sortOptions = [
    'intel' => $colLabels['intel'],
    'arena' => 'Arena',
    'speed' => $colLabels['speed'],
    'ttft'  => $colLabels['ttft'],
    'pin'   => $colLabels['pin'],
    'pout'  => $colLabels['pout'],
    'value' => $colLabels['value'],
    'new'   => $colLabels['date'],
];
// 出站链接统一口径（与 assets/js/llm-leaderboard.js 的 stateUrl() 同规则）：view 由排序键与权重
// 推导（老口径 cheap/new 视图），等于默认值的参数一律省略 —— 所以无 JS 点表头/点族徽得到的 URL
// 与 JS 拦截后 pushState 的 URL 完全一致。$extra 追加在 sort 之后（q 的位置，lang 永远最后）。
$llmLink = static function (string $sortKey, array $extra = [], ?string $group = null) use ($boardState, $currentLang, $isChartView) {
    $view = $boardState['view'];
    if (!$isChartView) {
        $view = $sortKey === 'price' ? 'cheap' : ($sortKey === 'new' ? 'new' : ($boardState['weights'] === 'open' ? 'open' : 'all'));
    }
    $defaultWeights = $view === 'open' ? 'open' : 'all';
    $defaultSort = $view === 'cheap' ? 'price' : ($view === 'new' ? 'new' : 'intel');
    $group = $group ?? $boardState['group'];
    $qs = [];
    if ($view !== 'all') $qs[] = 'view=' . rawurlencode($view);
    if ($boardState['weights'] !== $defaultWeights) $qs[] = 'weights=' . rawurlencode($boardState['weights']);
    if ($sortKey !== $defaultSort) $qs[] = 'sort=' . rawurlencode($sortKey);
    if ($group !== 'model') $qs[] = 'group=' . rawurlencode($group);
    foreach ($extra as $name => $value) $qs[] = $name . '=' . rawurlencode((string)$value);
    if ($currentLang !== '' && $currentLang !== 'zh-CN') $qs[] = 'lang=' . rawurlencode($currentLang);
    return '/llm-leaderboard.php' . ($qs === [] ? '' : '?' . implode('&', $qs)) . '#llm-views';
};
$sortHref = static fn (string $key) => $llmLink($key, $boardState['q'] === '' ? [] : ['q' => $boardState['q']]);
// 显示口径切换（按模型 / 按档位）的链接：与 JS 的 stateUrl 同规则，无 JS 点它也能切。
// 默认口径是 model（无参数），带 group 的 URL 都进了 noindex 口径。
$groupHref = static fn (string $group) => $llmLink($boardState['sort'], $boardState['q'] === '' ? [] : ['q' => $boardState['q']], $group);
// 默认口径（一模型一行）的脚注：行上没有档位徽标、没有族徽，所以「这一行是哪一档」只能写在脚注里。
// 展开视图（group=tier）另有 llm.effort_note 说明档位徽标，两者互斥。
$groupCopy = [
    'note' => $llm_seo('llm.group_note', '本表一个模型一行：取的是当前排序列里有实测值的最优档位（同值时取更强的一档），Arena 分与价格是模型级数据。想看同一模型的各推理档位，切到「按档位」。'),
];
// 同族色条：只在「按档位」视图里有意义——它把同一模型散落在不同名次的几行连起来。
// 按模型视图一行就是一个模型，色条没有信息量，所以不画。
$familyRowAttr = static function (array $m) use ($boardState): string {
    if ($boardState['group'] !== 'tier') return '';
    $color = (int)($m['family_color'] ?? 0);
    return $color > 0 ? ' class="is-family" style="--llm-family:var(--llm-family-' . $color . ')"' : '';
};
// 可排序表头：<th> 内是真链接（无 JS 也能排序，也能被爬虫跟随），箭头与 aria-sort 只标当前列；
// 方向固定为「更优在前」（分数/速度降序、延迟/价格升序、上架倒序），见 llm_board_sort_direction。
$sortTh = static function (string $key, string $label, array $options = []) use ($esc, $sortAlias, $sortHref) {
    $direction = llm_board_sort_direction($key);
    $on = $sortAlias === $key;
    $arrow = $on ? ($direction === 'asc' ? '↑' : '↓') : '↕';
    $class = trim((string)($options['class'] ?? '') . ' is-sortable');
    return '<th class="' . $esc($class) . '" aria-sort="' . ($on ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none') . '"'
        . (isset($options['attr']) ? ' ' . $options['attr'] : '') . '>'
        . '<span class="llm-heading-with-tip"><a class="llm-sort' . ($on ? ' is-active' : '') . '" data-sort="' . $esc($key) . '" href="' . $esc($sortHref($key)) . '">'
        . $esc($label) . '<span class="llm-sort-arrow" aria-hidden="true">' . $arrow . '</span></a>'
        . ($options['inner'] ?? '') . '</span>'
        . ($options['after'] ?? '') . '</th>';
};
// 主指标列：有智能指数就突出智能指数（默认口径），否则突出 Arena；对比条按当前表内主指标最高值归一，
// 只表示表内相对位置（不是绝对分数，也不做分段刻度）。
$primaryColumn = $hasIntel ? 'intel' : 'arena';
$primaryMax = 0.0;
foreach ($ssrRows as $primaryRow) {
    $primaryValue = $primaryRow[$primaryColumn === 'intel' ? 'aa_intelligence' : 'arena_score'] ?? null;
    if (is_numeric($primaryValue) && is_finite((float)$primaryValue)) $primaryMax = max($primaryMax, (float)$primaryValue);
}
$meterWidth = static function ($value) use ($primaryMax): string {
    if ($value === null || !is_numeric($value) || !is_finite((float)$value) || $primaryMax <= 0) return '0';
    return (string) max(0, min(100, (int) round((float)$value / $primaryMax * 100)));
};
// 主指标格：标签（桌面隐藏、移动端当卡片右上角的字段名）+ 数值 + 对比条；SSR 与 JS 同一套 class
$primaryCell = static function ($value, string $text) use ($esc, $colLabels, $primaryColumn, $meterWidth): string {
    return '<span class="llm-primary"><span class="llm-primary-label">' . $esc($colLabels[$primaryColumn]) . '</span>'
        . '<span class="llm-primary-value' . ($value === null ? ' is-empty' : '') . '">' . $esc($text) . '</span>'
        . '<span class="llm-meter" aria-hidden="true"><i class="llm-meter-fill" style="width:' . $meterWidth($value) . '%"></i></span></span>';
};

// 推理强度档位文案：SSR 与 JS 共用这一份（window.LLM_EFFORT），避免两处翻译漂移。
// 徽标用 AA 的英文档位名（llm_board_effort_label_en），五语同一份；只有悬浮说明按语言本地化。
$effortLabels = [];
foreach (llm_board_effort_labels() as $effortKey) {
    $effortLabels[$effortKey] = $llm_seo('llm.effort_' . str_replace('-', '_', $effortKey), llm_board_effort_label_en($effortKey));
}
$effortText = static fn($label) => isset($effortLabels[$label]) ? $effortLabels[$label] : (string)$label;
// 档位徽标悬浮说明（{label} 由 SSR/JS 各自替换，JS 用同一模板）
$effortTipTemplate = $llm_seo('llm.effort_badge_tip', '该行取 {label} 档位的实测值，数据来自 Artificial Analysis。');

// Arena 置信区间（± 徽标）：数据来自 LMArena 的 rating_lower / rating_upper（同步脚本写入
// cache/llm/llm-leaderboard.json 的 arena_ci_low / arena_ci_high）。Arena 分是成对比较的统计量，
// 相邻名次常常只差 1~2 分，这个差距经常整个落在区间内 —— 不显示区间，读者会把噪声当名次。
// 半宽取 max(high-score, score-low)，三项齐全且 > 0 才显示；缺 CI 的模型留空，而不是显示 ±0。
$ciTipTemplate = $llm_seo('llm.ci_tip', 'LMArena 评分的 95% 置信区间 {low}–{high}（±{half}）；区间越窄，名次越可信，相邻名次常落在彼此的区间内。');
// 半宽口径在数据层（llm_board_ci_half）：± 徽标、榜级分辨率、JS 与测试共用同一条，不在这里重写
$ciHalf = static fn($m): ?float => llm_board_ci_half(is_array($m) ? $m : []);
// 纯文本形态：移动端格子把它接在分数后面（一格放不下第二个元素），与 JS 的 ciText 同一口径
$ciText = static function ($m) use ($ciHalf): string {
    $half = $ciHalf($m);
    return $half === null ? '' : '±' . number_format($half, 1);
};
// 桌面列里的徽标：悬浮说明给出完整区间
$ciBadgeHtml = static function ($m) use ($ciHalf, $ciText, $ciTipTemplate, $esc): string {
    $half = $ciHalf($m);
    if ($half === null) return '';
    $tip = str_replace(
        ['{low}', '{high}', '{half}'],
        [number_format((float)$m['arena_ci_low'], 1), number_format((float)$m['arena_ci_high'], 1), number_format($half, 1)],
        $ciTipTemplate
    );
    return '<span class="llm-ci"' . ($tip !== '' ? ' title="' . $esc($tip) . '"' : '') . '>' . $esc($ciText($m)) . '</span>';
};

// Arena 格的悬浮说明：SSR 首屏与 JS 重渲染共用这一份（窗口注入 window.LLM_ARENA_TIP），
// 否则服务端的长说明和 JS 的短说明会标题不一致。票数少的那句拼在后面（见行渲染）。
$arenaTipBase = $llm_seo('llm.arena_tip', 'Arena 是 LMArena 的综合对战评分，采用 Bradley-Terry 统计口径，不是 Elo。分数越高，代表用户对战偏好越高；它不是参数量，也不是独立的编程能力分。');

// 许可徽标：只在数据明确时画（开源权重 / 闭源）。open_weights 为 null 时不画「未注明」——
// 拿不到就说没有，而不是每行挂一个没有信息量的徽标；筛选口径仍在「权重」下拉里（含「未知」）。
$weightBadge = static function ($m) use ($esc, $llm_seo): string {
    $state = $m['open_weights'] ?? null;
    if ($state !== true && $state !== false) return '';
    return '<span class="llm-badge ' . ($state === true ? 'llm-badge-open' : 'llm-badge-closed') . '">'
        . $esc($state === true ? __('llm.ui.weights_open') : $llm_seo('llm.badge_closed', '闭源')) . '</span>';
};

// 上架时间显示：正文给相对时间（今天 / 昨天 / N 天前 / N 周前 / N 个月前 / N 年前），精确日期退到
// title —— 一列生 ISO 日期（2026-09-01）在按分数排序的表里读不出「新还是旧」，还占宽度。
// 分档与 JS 的 listedText() 逐档一致，文案是一份种子（scripts/i18n/llm-leaderboard/14-listed.php），
// SSR 与 JS 共用（window.LLM_LISTED），避免两处翻译漂移。
$listedCopy = [
    'today' => $llm_seo('llm.listed_today', '今天'),
    'yesterday' => $llm_seo('llm.listed_yesterday', '昨天'),
    'days' => $llm_seo('llm.listed_days', '{n} 天前'),
    'weeks' => $llm_seo('llm.listed_weeks', '{n} 周前'),
    'week' => $llm_seo('llm.listed_week', '{n} 周前'),
    'months' => $llm_seo('llm.listed_months', '{n} 个月前'),
    'month' => $llm_seo('llm.listed_month', '{n} 个月前'),
    'years' => $llm_seo('llm.listed_years', '{n} 年前'),
    'year' => $llm_seo('llm.listed_year', '{n} 年前'),
];
// 分档规则在数据层（llm_board_listed_text），SSR / JS / 测试共用同一条，不在这里重写一遍
$listedText = static fn ($m): string => llm_board_listed_text($m['listed_at'] ?? null, $listedCopy);
// 单元格里的上架时间：没有可用时间戳时回落原 ISO 串（或 '—'），空值不编造「今天」
$listedCell = static function ($m) use ($listedText): array {
    $iso = (string)($m['listed_at_iso'] ?? '');
    $text = $listedText($m);
    if ($text === '') return ['text' => $iso !== '' ? $iso : '—', 'iso' => ''];
    return ['text' => $text, 'iso' => $iso];
};

// 性价比 = Arena 分 ÷ 输出价（$/1M tokens），数值越高越划算。
// 输出价为 0（免费）算不出比值，单独标「免费」；分数或价格缺失则为 null。
$valueScore = static function ($m) {
    $s = $m['arena_score'] ?? null;
    $p = $m['price_out'] ?? null;
    if ($s === null || $p === null) return null;
    if ((float)$p === 0.0) return null;
    return (float)$s / (float)$p;
};
$isFreeValued = static function ($m) {
    return ($m['arena_score'] ?? null) !== null
        && ($m['price_out'] ?? null) !== null
        && (float)$m['price_out'] === 0.0;
};
$fmtValue = static function ($v) {
    if ($v === null) return '—';
    if ($v >= 100) return (string)round($v);
    return number_format($v, 1);
};

// ---------------------------------------------------------------------------
// 排名升降：以「今天之前最近的一份每日归档」为基线，比较 LMArena 的 arena_rank。
// 刻意不用本站表内行序 —— 那会因为新增/移除收录模型造成整表平移的假变化。
// 基线不存在（首次上线当天）时整表不显示箭头，宁缺毋滥。
// ---------------------------------------------------------------------------
$rankDelta = [];      // id => ['d' => ?int（正=上升）, 'isNew' => bool]
$baselineDate = null; // 基线归档日期，用于文案与排错
if ($snapshot !== null) {
    $historyDir = __DIR__ . '/cache/llm/history';
    $today = date('Y-m-d');
    $histFiles = glob($historyDir . '/*.json') ?: [];
    rsort($histFiles); // 文件名 YYYY-MM-DD，字典序即时间序
    $baselineFile = null;
    foreach ($histFiles as $hf) {
        $base = basename($hf, '.json');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $base) !== 1) continue;
        if (strcmp($base, $today) >= 0) continue; // 跳过今天及之后
        $baselineFile = $hf;
        $baselineDate = $base;
        break;
    }
    if ($baselineFile !== null) {
        $pd = json_decode((string)file_get_contents($baselineFile), true);
        if (is_array($pd) && is_array($pd['models'] ?? null)) {
            $prevRank = [];
            foreach ($pd['models'] as $pm) {
                if (!is_array($pm) || ($pm['id'] ?? '') === '') continue;
                $prevRank[(string)$pm['id']] = ($pm['arena_rank'] ?? null) === null ? null : (int)$pm['arena_rank'];
            }
            foreach ($rankedModels as $m) {
                $mid = (string)$m['id'];
                $cur = ($m['arena_rank'] ?? null) === null ? null : (int)$m['arena_rank'];
                if (!array_key_exists($mid, $prevRank)) {
                    $rankDelta[$mid] = ['d' => null, 'isNew' => true, 'date' => $baselineDate];
                } elseif ($cur === null || $prevRank[$mid] === null) {
                    $rankDelta[$mid] = ['d' => null, 'isNew' => false, 'date' => $baselineDate];
                } else {
                    $rankDelta[$mid] = ['d' => $prevRank[$mid] - $cur, 'isNew' => false, 'date' => $baselineDate];
                }
            }
        }
    }
}
// 升降标记 HTML（SSR 与 JS 各有一份实现，输出必须一致）。只有真的升/降才画；
// 「新上榜」不再画标记（排名数字下面多一行碎字，读榜的人并不需要）。
$rankMoveHtml = static function (?array $d) use ($esc, $llm_seo, $baselineDate) {
    if ($d === null || !empty($d['isNew'])) return '';
    if ($d['d'] > 0) {
        return '<span class="llm-rank-move is-up" title="' . $esc($llm_seo('llm.rank_up_tip', '较 {date} 排名上升 {n} 位', ['date' => $baselineDate, 'n' => (int)$d['d']])) . '">↑' . (int)$d['d'] . '</span>';
    }
    if ($d['d'] < 0) {
        return '<span class="llm-rank-move is-down" title="' . $esc($llm_seo('llm.rank_down_tip', '较 {date} 排名下降 {n} 位', ['date' => $baselineDate, 'n' => (int)abs($d['d'])])) . '">↓' . (int)abs($d['d']) . '</span>';
    }
    return '';
};

// ---------------------------------------------------------------------------
// 行内明细（窄屏 / 触屏按需展开）。
// 精确上架日期、Arena 95% 区间原文、票数、名次变动与档位说明原先只挂在 title 上，而 title
// 在触屏上永远不出现（没有 hover）。桌面鼠标用户仍有悬浮说明，所以这个按钮只在窄屏/触屏
// 显示（见 .llm-row-more 的媒体查询），桌面一个像素都不占。
// 「该出现哪些事实」由数据层定（llm_board_row_facts ↔ JS data.rowFacts，两边一份规则）；
// 这里只做两件事：把数字本地化、把已有的 tip 句子填上参数 —— 不新写句子，手机上展开看到的
// 与桌面悬浮看到的是同一句。返回空串表示这行没有可展开的内容：不画按钮，也不画空明细行。
// ---------------------------------------------------------------------------
$rowDetailHtml = static function (array $m, array $listed, ?array $delta, string $effort) use ($esc, $llm_seo, $colLabels, $effortLabels, $effortTipTemplate, $boardState, $hasIntel): array {
    $facts = llm_board_row_facts($m, $delta, $boardState['group'] === 'tier' && $effort !== '');
    if ($facts === null) return ['toggle' => '', 'row' => ''];
    $items = [];
    if ($facts['date'] !== null) $items[] = [$colLabels['date'], $facts['date']];
    if ($facts['ci_low'] !== null) $items[] = [$llm_seo('llm.detail_ci', '95% 区间'), number_format($facts['ci_low'], 1) . '–' . number_format($facts['ci_high'], 1)];
    if ($facts['votes'] !== null) $items[] = [$llm_seo('llm.detail_votes', 'Arena 票数'), number_format($facts['votes'])];
    if ((int)$facts['rank_delta'] !== 0) {
        // 名次变动写「2026-09-04 ↑3」：不依赖语言，也不用另一句措辞不同的译文（见数据层注释）
        $items[] = [$llm_seo('llm.detail_move', '较上期名次'), trim((string)$facts['rank_date'] . ' ' . ((int)$facts['rank_delta'] > 0 ? '↑' : '↓') . abs((int)$facts['rank_delta']))];
    }
    $notes = [];
    if (!empty($facts['low_votes'])) $notes[] = __('llm.ui.low_votes_tip', ['votes' => number_format($facts['votes'])]);
    if (!empty($facts['effort']) && isset($effortLabels[$effort])) {
        $notes[] = str_replace('{label}', $effortLabels[$effort], $effortTipTemplate);
    }
    $detailId = 'llm-detail-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)($m['row_key'] ?? $m['id']));
    $html = '';
    foreach ($items as [$label, $value]) {
        $html .= '<span class="llm-detail-item"><span class="llm-detail-label">' . $esc($label) . '</span><span class="llm-detail-value">' . $esc($value) . '</span></span>';
    }
    foreach ($notes as $note) $html .= '<span class="llm-detail-note">' . $esc($note) . '</span>';
    return [
        'toggle' => '<button type="button" class="llm-row-more" aria-expanded="false" aria-controls="' . $esc($detailId) . '" aria-label="' . $esc($llm_seo('llm.detail_more', '展开该行明细')) . '">i</button>',
        'row' => '<tr class="llm-detail-row" id="' . $esc($detailId) . '" hidden><td colspan="' . ($hasIntel ? 10 : 7) . '">' . $html . '</td></tr>',
    ];
};

$itemListElements = [];
foreach ($ssrModels as $idx => $model) {
    $item = [
        '@type' => 'ListItem',
        'position' => $idx + 1,
        'name' => (string)($model['display_name'] ?? $model['id'] ?? ''),
    ];
    if (!empty($model['id'])) {
        $item['item'] = $siteUrl . '/llm-model.php?id=' . rawurlencode((string)$model['id']);
    }
    $itemListElements[] = $item;
}
?>

<!doctype html>
<html lang="<?php echo $esc($currentLang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="format-detection" content="telephone=no"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

    <!-- SEO Meta Tags -->
    <title><?php echo $esc($pageTitle); ?></title>
    <meta name="keywords" content="<?php echo $esc($seoKeywords); ?>"/>
    <meta name="description" content="<?php echo $esc($siteDescription); ?>"/>
    <meta name="author" content="<?php echo $esc($siteName); ?>">
    <link rel="canonical" href="<?php echo $esc($selfCanonicalUrl); ?>">

<?php include('includes/head-meta.php'); ?>
    <!-- 页面专用样式 -->
<link href="<?php echo asset_url_auto('/assets/css/llm-leaderboard.v2.css'); ?>&llm=<?php echo LLM_ASSET_VER; ?>" rel="stylesheet">
<link href="<?php echo asset_url_auto('/assets/css/llm-share-image.css'); ?>&llm=<?php echo LLM_ASSET_VER; ?>" rel="stylesheet">

<?php if ($llmNoindex): ?>
    <meta name="robots" content="noindex,follow">
    <meta name="googlebot" content="noindex,follow">
<?php else: ?>
    <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1"/>
    <meta name="googlebot" content="index,follow"/>
<?php endif; ?>
    <meta name="theme-color" content="#0877e3"/>

    <!-- hreflang：多语言入口（全站标准语言码 zh-CN/zh-TW/en-US/ja-JP/ko-KR）；带当前 view -->
<?php i18n_render_hreflang($hreflangBase); ?>

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $esc($canonicalUrl); ?>">
    <meta property="og:title" content="<?php echo $esc($ogTitle); ?>">
    <meta property="og:description" content="<?php echo $esc($ogDescription); ?>">
    <meta property="og:site_name" content="<?php echo $esc($siteName); ?>">
    <meta property="og:locale" content="<?php echo $esc($ogLocale); ?>">
    <meta property="og:image" content="<?php echo $esc($ogImage); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?php echo $esc($ogTitle); ?>">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="<?php echo $esc($ogImage); ?>">
    <meta name="twitter:url" content="<?php echo $esc($canonicalUrl); ?>">
    <meta name="twitter:title" content="<?php echo $esc($twitterTitle); ?>">
    <meta name="twitter:description" content="<?php echo $esc($twitterDescription); ?>">

    <!-- BreadcrumbList -->
    <script type="application/ld+json">
    <?php
    echo $j([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => $llm_seo('llm.breadcrumb_home', '首页'), 'item' => $siteUrl . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $llm_seo('llm.breadcrumb_parent', '跑分排行'), 'item' => $siteUrl . '/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $llm_seo('llm.breadcrumb_self', '大模型排行榜')],
        ],
    ]);
    ?>
    </script>

    <!-- ItemList：与当前视图 SSR 排序一致；仅开放权重模型提供详情链接 -->
    <?php if ($snapshot !== null && $itemListElements !== []): ?>
    <script type="application/ld+json">
    <?php echo $j([
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => $vsH1,
        'url' => $selfCanonicalUrl,
        'numberOfItems' => count($itemListElements),
        'itemListElement' => $itemListElements,
    ]); ?>
    </script>
    <?php endif; ?>

    <!-- FAQPage（照抄 raid-calculator.php 模式；与页内可见 FAQ 一致） -->
    <script type="application/ld+json">
    <?php
    $faqEntity = [];
    foreach ($faqItems as $item) {
        if ($item['q'] === '' || $item['a'] === '') continue;
        $faqEntity[] = [
            '@type' => 'Question',
            'name' => $item['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $item['a'],
            ],
        ];
    }
    echo $j([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $faqEntity,
    ]);
    ?>
    </script>
</head>

<body>
<?php $current_page = 'llm-leaderboard'; ?>
<?php include('includes/header.php'); ?>

<!-- 大模型排行榜：结构与 raid-calculator.php 同构；快照来自 cache/llm/llm-leaderboard.json -->
<main class="llm-leaderboard-page<?php echo $isChartView ? ' llm-page-wide' : ''; ?>" role="main">
    <div class="page-shell">
<?php
$breadcrumbs = [
    ['name' => $llm_seo('llm.breadcrumb_home', '首页'), 'url' => '/'],
    ['name' => $llm_seo('llm.breadcrumb_parent', '跑分排行'), 'url' => '/llm-leaderboard.php'],
    ['name' => $llm_seo('llm.breadcrumb_self', '大模型排行榜')],
];
include('includes/breadcrumb.php');
?>
        <div class="llm-hero">
        <header class="page-title">
            <h1><?php echo $esc($vsH1); ?></h1>
            <p id="llm-intro"><?php echo $esc(__($isChartView ? ($chartAxis === 'speed' ? 'llm.ui.chart_intro_speed' : 'llm.ui.chart_intro') : 'llm.ui.intro')); ?></p>
        </header>

        <div class="llm-meta">
            <span class="llm-meta-item"><?php echo $esc($llm_seo('llm.chip_updated_prefix', '更新于')); ?> <span id="llm-meta-updated"><?php echo $esc($metaUpdated); ?></span></span>
            <span class="llm-meta-item"><span id="llm-meta-count"><?php echo $esc($metaCount); ?></span> <?php echo $esc($llm_seo('llm.chip_models_suffix', '个模型')); ?></span>
            <span class="llm-meta-item"><?php echo $esc($llm_seo('llm.chip_daily', '每日同步')); ?></span>
<?php if (!empty($unrankedModels)): ?>            <a class="llm-chip llm-chip-link" href="#llm-unranked"><?php echo count($unrankedModels); ?> <?php echo $esc(__('llm.ui.pending')); ?> &darr;</a>
<?php endif; ?>
        </div>
        <div class="llm-status-line" role="status"<?php echo ($metaStaleHidden !== '' && !$syncDegraded) ? ' hidden' : ''; ?>>
            <span id="llm-meta-stale" class="llm-chip is-stale" <?php echo $metaStaleHidden; ?>><?php echo $esc($llm_seo('llm.chip_stale', '数据更新延迟')); ?></span>
<?php if ($syncDegraded): ?>            <span class="llm-chip is-stale"><?php echo $esc(__('llm.ui.sync_error')); ?></span>
<?php endif; ?>
        </div>
        </div>
        <!-- P2: 首屏 summary 卡（综合第一 / 最佳性价比 / 最佳开放 / 最新上榜）；取自同一快照，与 view 无关；无快照时整块隐藏 -->
<?php
// P6: 图表视图数据。横轴两套口径（$chartAxis）：价格视图 = 输出价对数，速度视图 = 实测输出速度线性
$paretoPts = llm_board_plottable($rankedModels, $chartAxis);
/* 前沿 = 「没有替代品同时更好」。两个视图各有一条，语义同构：
     价格视图 → 没有谁同时更便宜且更强
     速度视图 → 没有谁同时更快且更强
   速度轴不是照搬价格轴的方向：价格按升序扫、速度按降序扫，都在分数创新高处取点。
   （早前这里只算价格轴，理由是"很快但不强也算前沿"——那是把定义理解反了，
     正确口径下很快但不强恰恰是被支配的一方。） */
$paretoFrontier = llm_board_frontier($rankedModels, $chartAxis);
$paretoFrontierIds = array_column($paretoFrontier, 'id');

/* 图表气泡半径按「面积」映射（半径取平方根）——直接按半径线性映射会把大值夸大成三四倍面积，读起来是错的。
   两个视图都让「气泡越大 = 越大越好」，所以尺寸轴的语义反过来了：
   - 价格视图：气泡 ＝ 输出速度，越大越快
   - 速度视图：气泡 ＝ 输出价格，越大越便宜（速度已经在横轴上，再拿它当尺寸就是重复编码）
   范围由 llm_board_bubble_radii() 的默认值决定（当前 10.5–16.5，原委见该函数注释：
   30 个气泡 44/435 对重叠 → 收一档后 27 对）。这里不再抄一遍数字，避免两处漂移。
   页面同时有 SSR 点和 JS 重绘，两边必须同形，所以半径在这里算一次、序列化成
   window.LLM_CHART_BUBBLE 给 JS 读，而不是在两处各写一遍公式。 */
$bubbleUnit = $chartAxis === 'price' ? 'speed' : 'price';
$bubble = llm_board_bubble_radii($paretoPts, $bubbleUnit);
$bubbleRMin = $bubble['rMin'];
$bubbleRMax = $bubble['rMax'];
$bubbleR = $bubble['r'];
$bubbleLo = $bubble['lo'];
$bubbleHi = $bubble['hi'];
$bubbleLegend = $bubble['count'] === 0 ? null : ['lo' => $bubbleLo, 'hi' => $bubbleHi, 'unit' => $bubbleUnit];

$sumCards = [];
if ($snapshot !== null && $rankedModels !== []) {
    $sumByArena = $rankedModels;
    usort($sumByArena, $cmpArenaDesc);
    $sumDateFmt = static function ($ts) use ($currentLang) {
        return $currentLang === 'en-US' ? date('M j', (int)$ts) : date('n月j日', (int)$ts);
    };
    if (($sumByArena[0]['arena_score'] ?? null) !== null) {
        $sumCards['top'] = ['m' => $sumByArena[0], 'sub' => 'Arena ' . number_format((float)$sumByArena[0]['arena_score'], 1)];
    }
    $sumBestValue = null; $sumBestValueScore = -1.0; $sumBestOpen = null; $sumNewest = null; $sumNewestTs = 0;
    foreach ($rankedModels as $sm) {
        $sv = $valueScore($sm);
        if ($sv !== null && $sv > $sumBestValueScore) { $sumBestValueScore = $sv; $sumBestValue = $sm; }
        if (($sm['open_weights'] ?? null) === true && ($sm['arena_score'] ?? null) !== null
            && ($sumBestOpen === null || (((float)$sm['arena_score']) <=> ((float)$sumBestOpen['arena_score'])) > 0)) {
            $sumBestOpen = $sm;
        }
        $sTs = (int)($sm['listed_at'] ?? 0);
        if ($sTs > $sumNewestTs) { $sumNewestTs = $sTs; $sumNewest = $sm; }
    }
    if ($sumBestValue !== null) {
        $sumCards['value'] = ['m' => $sumBestValue, 'sub' => number_format($sumBestValueScore, 1)];
    }
    if ($sumBestOpen !== null) {
        $sumCards['open'] = ['m' => $sumBestOpen, 'sub' => 'Arena ' . number_format((float)$sumBestOpen['arena_score'], 1)];
    }
    if ($sumNewest !== null && $sumNewestTs > 0) {
        $sumCards['new'] = ['m' => $sumNewest, 'sub' => $sumDateFmt($sumNewestTs)];
    }
}
$sumLabels = [
    'top'   => ['llm.sum_top', '综合第一'],
    'value' => ['llm.ui.ratio_best', '最高评分/价格比'],
    'open'  => ['llm.ui.best_open', '最高评分开放权重模型'],
    'new'   => ['llm.sum_new', '最新上榜'],
];
?>
<?php include __DIR__ . '/includes/llm-board-views.php'; ?>
        <div id="llm-table-wrap"<?php echo $isChartView ? ' hidden' : ''; ?>>
<?php if ($snapshot === null): ?>
            <p class="llm-error"><?php echo $esc($llm_seo('llm.error_unavailable', '数据暂不可用：快照缺失或损坏，请稍后重试。')); ?></p>
<?php else: ?>
            <table id="llm-table" class="llm-table">
                <thead>
                    <tr>
                        <th class="llm-col-rank">#</th>
                        <th class="llm-col-model"><?php echo $esc($llm_seo('llm.col_model', '模型')); ?></th>
<?php echo $sortTh('arena', 'Arena', [
    'class' => 'llm-col-arena',
    'inner' => '<button type="button" class="llm-info-tip" aria-label="' . $esc($llm_seo('llm.arena_aria', '什么是 Arena 评分')) . '" aria-expanded="false" aria-controls="llm-arena-help">i</button>',
    'after' => '<span id="llm-arena-help" class="llm-tooltip" role="tooltip" aria-hidden="true">' . $esc($arenaTipBase) . '</span>',
]); ?>
<?php if ($hasIntel): echo $sortTh('intel', $colLabels['intel'], ['class' => 'llm-col-intel' . ($primaryColumn === 'intel' ? ' is-primary' : '')]); ?>
<?php echo $sortTh('speed', $colLabels['speed'], ['class' => 'llm-col-speed', 'attr' => 'title="' . $esc($llm_seo('llm.col_speed_tip', 'Artificial Analysis 实测输出速度，单位 tokens/秒，越高越快。')) . '"']); ?>
<?php echo $sortTh('ttft', $colLabels['ttft'], ['class' => 'llm-col-ttft', 'attr' => 'title="' . $esc($llm_seo('llm.col_ttft_tip', 'Artificial Analysis 实测首个 token 延迟，单位秒，越低响应越快。')) . '"']); ?>
<?php endif; echo $sortTh('pin', $colLabels['pin'], ['class' => 'llm-col-pin']); ?>
<?php echo $sortTh('pout', $colLabels['pout'], ['class' => 'llm-col-pout']); ?>
<?php echo $sortTh('value', $colLabels['value'], ['class' => 'llm-col-value', 'attr' => 'title="' . $esc(__('llm.ui.method_body')) . '"']); ?>
<?php echo $sortTh('new', $colLabels['date'], ['class' => 'llm-col-date']); ?>
                    </tr>
                </thead>
                <tbody id="llm-table-body">
<?php foreach ($ssrRows as $idx => $m):
    $llmRank = $idx + 1;
    $modelAlias = $llmAliases[$m['id']] ?? [];
    $iconSlug = (string)($modelAlias['icon'] ?? '');
    if (preg_match('/^[a-z0-9-]+$/', $iconSlug) !== 1) $iconSlug = '';
    $effort = (isset($m['effort']) && is_string($m['effort']) && $m['effort'] !== '') ? $m['effort'] : '';
    // 升降箭头是模型级名次（LMArena），只画在模型本体命中的那一行，不让同模型的多行重复同一箭头
    $rowDelta = ($effort === '' || $effort === (string)($m['effort_current'] ?? '')) ? ($rankDelta[(string)$m['id']] ?? null) : null;
    // 许可徽标：未知（null）不画 —— 数据没有就说没有，不占一个「未注明」的位
    $mWeight = $weightBadge($m);
    // 上架时间：正文相对时间，精确日期进 title
    $mListed = $listedCell($m);
    // 行内明细：按钮进了名次格（窄屏/触屏才显示），明细行跟在模型行后面（见 $rowDetailHtml）
    $mDetail = $rowDetailHtml($m, $mListed, $rowDelta, $effort);
    // Arena 格的悬浮说明：票数少的那句并进来（原先是一个几乎每行都挂的「低样本」徽标）
    $mArenaTip = $arenaTipBase;
    if (($m['arena_votes'] ?? null) !== null && (int)$m['arena_votes'] < llm_board_low_votes_threshold()) {
        $mArenaTip .= ' ' . __('llm.ui.low_votes_tip', ['votes' => number_format((int)$m['arena_votes'])]);
    }
?>
                    <tr data-id="<?php echo $esc($m['id']); ?>" data-row-key="<?php echo $esc((string)($m['row_key'] ?? $m['id'])); ?>"<?php echo $effort !== '' ? ' data-effort="' . $esc($effort) . '"' : ''; ?><?php echo $familyRowAttr($m); ?>>
                        <td class="llm-col-rank"><span class="llm-rank-dot<?php echo $llmRank <= 3 ? ' r' . $llmRank : ''; ?>"><?php echo $llmRank; ?></span><?php echo $rankMoveHtml($rowDelta); ?><?php echo $mDetail['toggle']; ?></td>
                        <td class="llm-col-model">
                            <div class="llm-model-cell">
<?php if ($iconSlug !== ''): ?>                                <img class="llm-org-icon llm-org-icon--<?php echo $esc($iconSlug); ?>" width="28" height="28" alt="" aria-hidden="true" src="<?php echo $esc(asset_url_auto(LLM_ICON_BASE . $iconSlug . '.svg')); ?>" onload="this.classList.remove('is-error');this.nextElementSibling?.setAttribute('hidden','hidden')" onerror="this.classList.add('is-error');this.nextElementSibling?.removeAttribute('hidden')">
<?php endif; ?>                                <span class="llm-org-mark"<?php echo $iconSlug !== '' ? ' hidden' : ''; ?>><?php echo $esc(mb_strtoupper((string)mb_substr((string)($m['org'] ?? '?'), 0, 1))); ?></span>
                                <span class="llm-model-text">
                                    <span class="llm-model-name"><a href="/llm-model.php?id=<?php echo $esc(rawurlencode((string)$m['id'])); ?>"><?php echo $esc($m['display_name'] ?? $m['id']); ?></a><?php if ($boardState['group'] === 'tier' && $effort !== ''): ?><span class="llm-effort-badge" title="<?php echo $esc(str_replace('{label}', $effortText($effort), $effortTipTemplate)); ?>"><?php echo $esc($effortText($effort)); ?></span><?php endif; ?> <span class="llm-model-org"><?php echo $esc($m['org'] ?? ''); ?><?php echo $mWeight === '' ? '' : ' ' . $mWeight; ?></span></span>
                                </span>
                            </div>
                        </td>
                        <td class="llm-col-arena<?php echo $primaryColumn === 'arena' ? ' is-primary' : ''; ?>" data-label="<?php echo $esc($colLabels['arena']); ?>">
                            <div class="llm-arena-cell" title="<?php echo $esc($mArenaTip); ?>">
<?php if ($primaryColumn === 'arena'): $mArena = $m['arena_score'] ?? null; ?>
                                <?php echo $primaryCell($mArena, $mArena === null ? '—' : number_format((float)$mArena, 1)); ?>
                                <?php elseif (($m['arena_score'] ?? null) !== null): ?>
                                <span class="llm-arena-score"><?php echo $esc(number_format((float)$m['arena_score'], 1)); ?></span>
                                <?php else: ?>
                                <span class="llm-arena-score is-empty">—</span>
                                <?php endif; ?>
<?php echo $ciBadgeHtml($m); ?>
                            </div>
                        </td>
<?php if ($hasIntel): $mIntel = $m['aa_intelligence'] ?? null; ?>                        <td class="llm-col-intel is-primary"<?php echo (isset($m['aa_variant']) && $m['aa_variant'] !== null && $m['aa_variant'] !== '') ? ' title="' . $esc($llm_seo('llm.col_variant_tip', '当前为 {variant} 推理档位的实测值', ['variant' => (string)$m['aa_variant']])) . '"' : ''; ?>><?php echo $primaryCell($mIntel, $fmtIntel($mIntel)); ?></td>
                        <td class="llm-col-speed"><?php echo isset($m['aa_speed']) && $m['aa_speed'] !== null ? $esc(number_format((float)$m['aa_speed'], 0)) : '—'; ?></td>
                        <td class="llm-col-ttft"><?php echo isset($m['aa_ttft']) && $m['aa_ttft'] !== null ? $esc(number_format((float)$m['aa_ttft'], 2)) . 's' : '—'; ?></td>
<?php endif; ?>                        <td class="llm-col-pin" data-label="<?php echo $esc($colLabels['pin']); ?>">
                            <div class="llm-price-cell">
                                <?php if (($m['price_in'] ?? null) !== null): ?>
                                <span class="llm-price-num"><?php echo $esc($fmtPrice($m['price_in'])); ?></span>
                                <?php else: ?>
                                <span class="llm-price-num is-empty">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="llm-col-pout" data-label="<?php echo $esc($colLabels['pout']); ?>">
                            <div class="llm-price-cell">
                                <?php if (($m['price_out'] ?? null) !== null): ?>
                                <span class="llm-price-num"><?php echo $esc($fmtPrice($m['price_out'])); ?></span>
                                <?php else: ?>
                                <span class="llm-price-num is-empty">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
<?php $mValue = $valueScore($m); ?>                        <td class="llm-col-value" data-label="<?php echo $esc($colLabels['value']); ?>"><?php if ($isFreeValued($m)): ?><span class="llm-value-num is-free"><?php echo $esc($colLabels['valueFree']); ?></span><?php else: ?><span class="llm-value-num<?php echo $mValue === null ? ' is-empty' : ''; ?>"><?php echo $esc($fmtValue($mValue)); ?></span><?php endif; ?></td>
                        <td class="llm-col-date" data-label="<?php echo $esc($colLabels['date']); ?>"<?php echo $mListed['iso'] === '' ? '' : ' title="' . $esc($mListed['iso']) . '"'; ?>><?php echo $esc($mListed['text']); ?></td>
                        <td class="llm-mobile-metrics">
<?php
// 与桌面列同源同序：Arena / 智能指数 / 速度 / 延迟 / 输入价 / 输出价 / 评分·价格比 / 上架
$mFree = $isFreeValued($m);
$mSpeed = $fmtMetric($m['aa_speed'] ?? null, 0);
$mTtft = $fmtMetric($m['aa_ttft'] ?? null, 2);
echo $metricTile($colLabels['arena'], $fmtMetric($m['arena_score'] ?? null, 1) . ($ciText($m) !== '' ? ' ' . $ciText($m) : ''));
if ($hasIntel) {
    echo $metricTile($colLabels['intel'], $fmtIntel($m['aa_intelligence'] ?? null));
    echo $metricTile($colLabels['speed'], $mSpeed === '—' ? '—' : $mSpeed . '/s');
    echo $metricTile($colLabels['ttft'], $mTtft === '—' ? '—' : $mTtft . 's');
}
echo $metricTile($colLabels['pin'], ($m['price_in'] ?? null) === null ? '—' : $fmtPrice($m['price_in']));
echo $metricTile($colLabels['pout'], ($m['price_out'] ?? null) === null ? '—' : $fmtPrice($m['price_out']));
echo $metricTile($colLabels['value'], $mFree ? $colLabels['valueFree'] : $fmtValue($mValue));
echo $metricTile($colLabels['date'], $mListed['text']);
?>
                        </td>
                    </tr>
<?php echo $mDetail['row']; ?>
<?php endforeach; ?>
<?php if ($ssrRows === []): ?><tr><td colspan="<?php echo $hasIntel ? 10 : 7; ?>" class="llm-error"><?php echo $esc(__('llm.js.no_matches')); ?></td></tr><?php endif; ?>
                </tbody>
            </table>
            <p class="llm-footnote"><?php echo $esc($llm_seo('llm.footnote', 'Arena 评分为 LMArena 的 Bradley-Terry 口径（非 Elo）；价格为 OpenRouter 平台报价（$/1M tokens），可能与官方 API 不同。')); ?> <span id="llm-ci-resolution"<?php echo $ciResolutionText === '' ? ' hidden' : ''; ?>><?php echo $esc($ciResolutionText); ?></span></p>
            <p class="llm-footnote" id="llm-effort-note"<?php echo $boardState['group'] === 'model' ? ' hidden' : ''; ?>><?php echo $esc($llm_seo('llm.effort_note', '同一模型的不同推理强度档位在本表各占一行，徽标沿用 Artificial Analysis 的英文档位名（Max / XHigh / High / Medium / Low …）；分数取对应档位的实测值，Arena 分与价格是模型级数据，同模型各行相同。同族行以名次格左侧色条与「×N 档位 / 同族 k/N」标记相连，点击标记只看这一个模型的全部档位。')); ?></p>
            <p class="llm-footnote"><?php echo $esc($llm_seo('llm.sort_note', '点击列头即可按该列排序：分数与速度从高到低，延迟与价格从低到高。')); ?> <?php echo $esc($llm_seo('llm.bar_note', '主指标列数值后的对比条按当前表内该列最高值归一，只表示表内相对位置，不代表绝对水平。')); ?></p>
            <p class="llm-footnote" id="llm-group-note"<?php echo $boardState['group'] === 'model' ? '' : ' hidden'; ?>><?php echo $esc($groupCopy['note']); ?></p>
            <details class="llm-method-note"><summary><?php echo $esc(__('llm.ui.method')); ?></summary><p><?php echo $esc(__('llm.ui.method_body')); ?> <a class="llm-method-link" href="#llm-sources"><?php echo $esc($llm_seo('llm.sources_h2', '数据来源')); ?></a></p></details>
<?php endif; ?>
        </div>
        <section class="llm-summary-grid" aria-label="<?php echo $esc($llm_seo('llm.sum_aria', '排行榜摘要')); ?>"<?php echo $sumCards === [] ? ' hidden' : ''; ?>>
<?php foreach ($sumLabels as $sk => $sl): if (!isset($sumCards[$sk])) continue; $sc = $sumCards[$sk]; ?>
            <a class="llm-summary-card" href="/llm-model.php?id=<?php echo $esc(rawurlencode((string)$sc['m']['id'])); ?>">
                <span class="llm-summary-label"><?php echo $esc($llm_seo($sl[0], $sl[1])); ?></span>
                <strong class="llm-summary-name"><?php echo $esc($sc['m']['display_name'] ?? $sc['m']['id']); ?></strong>
                <span class="llm-summary-sub"><?php echo $esc($sc['sub']); ?></span>
            </a>
<?php endforeach; ?>
        </section>
        <nav class="llm-shortcuts" aria-label="<?php echo $esc(__('llm.ui.shortcuts')); ?>">
<?php foreach (['open' => 'open_link', 'cheap' => 'price_link', 'new' => 'new_link'] as $shortcut => $label): ?>
            <a href="<?php echo $esc($pillHref($shortcut)); ?>#llm-views"><?php echo $esc(__('llm.ui.' . $label)); ?></a>
<?php endforeach; ?>
        </nav>
<?php
// 时间线只展示「已上架且已有 Arena 评分」的新模型；未评分的已在上方「尚未进入 Arena 排名」区块，
// 不重复出现（否则同一模型会在相邻两个区块各出现一次）。
$rankedIds = array_flip(array_map(static function ($m) { return (string)$m['id']; }, $rankedModels));
$timelineModels = array_values(array_filter(
    (array)($snapshot['new_models'] ?? []),
    static function ($nm) use ($rankedIds) { return isset($rankedIds[(string)($nm['id'] ?? '')]); }
));
$supportSectionCount = (!empty($unrankedModels) ? 1 : 0) + (!empty($timelineModels) ? 1 : 0);
?>
<?php if ($supportSectionCount > 0): ?>
        <div class="llm-support-grid<?php echo $supportSectionCount === 1 ? ' is-single' : ''; ?>">
<?php if ($snapshot !== null && !empty($unrankedModels)): ?>
        <section id="llm-unranked" class="llm-section llm-support-section">
            <h2><?php echo $esc($llm_seo('llm.unranked_h2', '尚未进入 Arena 排名')); ?></h2>
            <p class="llm-section-note"><?php echo $esc($llm_seo('llm.unranked_note', '以下 {count} 个模型已被本站收录，但 LMArena 尚未给出评分，因此不参与上方排名（有名次才叫排名，不造分）。按上架时间倒序；LMArena 收录后会自动进入排行榜。', ['count' => count($unrankedModels)])); ?></p>
            <ul class="llm-support-list">
<?php foreach ($unrankedModels as $um):
    $unrankedAlias = $llmAliases[$um['id']] ?? [];
    $unrankedIcon = (string)($unrankedAlias['icon'] ?? '');
    if (preg_match('/^[a-z0-9-]+$/', $unrankedIcon) !== 1) $unrankedIcon = '';
    $unrankedOrg = (string)($um['org'] ?? '?');
    $unrankedName = (string)($um['display_name'] ?? $um['id']);
?>
                <li>
                    <a class="llm-support-model" href="/llm-model.php?id=<?php echo $esc(rawurlencode((string)$um['id'])); ?>&amp;lang=<?php echo $esc(rawurlencode($currentLang)); ?>">
                        <span class="llm-support-icon-wrap">
<?php if ($unrankedIcon !== ''): ?>                            <img class="llm-support-icon llm-org-icon--<?php echo $esc($unrankedIcon); ?>" width="28" height="28" alt="" src="<?php echo $esc(asset_url_auto(LLM_ICON_BASE . $unrankedIcon . '.svg')); ?>" onload="this.classList.remove('is-error');this.nextElementSibling?.setAttribute('hidden','hidden')" onerror="this.classList.add('is-error');this.nextElementSibling?.removeAttribute('hidden')">
<?php endif; ?>                            <span class="llm-support-mark"<?php echo $unrankedIcon !== '' ? ' hidden' : ''; ?>><?php echo $esc(mb_strtoupper((string)mb_substr($unrankedOrg, 0, 1))); ?></span>
                        </span>
                        <span class="llm-support-copy"><strong class="llm-support-name"><?php echo $esc($unrankedName); ?></strong><span class="llm-support-meta"><?php echo $esc($unrankedOrg); ?> · <?php echo $esc($um['listed_at_iso'] ?? '—'); ?></span></span>
                    </a>
                </li>
<?php endforeach; ?>
            </ul>
        </section>
<?php endif; ?>
<?php if ($snapshot !== null && !empty($timelineModels)): ?>
        <section id="llm-timeline" class="llm-section llm-support-section">
            <h2><?php echo $esc($llm_seo('llm.timeline_h2', '近 14 天新模型')); ?></h2>
            <p class="llm-section-note"><?php echo $esc($llm_seo('llm.timeline_note', '按上架时间排序，均为已进入排行榜的模型；尚未评分的新模型见上方「尚未进入 Arena 排名」。')); ?></p>
            <ul class="llm-support-list">
<?php foreach ($timelineModels as $nm):
    $timelineAlias = $llmAliases[$nm['id']] ?? [];
    $timelineIcon = (string)($timelineAlias['icon'] ?? '');
    if (preg_match('/^[a-z0-9-]+$/', $timelineIcon) !== 1) $timelineIcon = '';
    $timelineOrg = (string)($nm['org'] ?? '?');
    $timelineName = (string)($nm['display_name'] ?? $nm['id']);
?>
                <li>
                    <a class="llm-support-model" href="/llm-model.php?id=<?php echo $esc(rawurlencode((string)$nm['id'])); ?>&amp;lang=<?php echo $esc(rawurlencode($currentLang)); ?>">
                        <span class="llm-support-icon-wrap">
<?php if ($timelineIcon !== ''): ?>                            <img class="llm-support-icon llm-org-icon--<?php echo $esc($timelineIcon); ?>" width="28" height="28" alt="" src="<?php echo $esc(asset_url_auto(LLM_ICON_BASE . $timelineIcon . '.svg')); ?>" onload="this.classList.remove('is-error');this.nextElementSibling?.setAttribute('hidden','hidden')" onerror="this.classList.add('is-error');this.nextElementSibling?.removeAttribute('hidden')">
<?php endif; ?>                            <span class="llm-support-mark"<?php echo $timelineIcon !== '' ? ' hidden' : ''; ?>><?php echo $esc(mb_strtoupper((string)mb_substr($timelineOrg, 0, 1))); ?></span>
                        </span>
                        <span class="llm-support-copy"><strong class="llm-support-name"><?php echo $esc($timelineName); ?></strong><span class="llm-support-meta"><?php echo $esc($timelineOrg ?: '—'); ?> · <?php echo $esc($nm['listed_at_iso'] ?? '—'); ?><?php if (($nm['open_weights'] ?? null) === true): ?> · <?php echo $esc(__('llm.ui.weights_open')); ?><?php elseif (($nm['open_weights'] ?? null) === false): ?> · <?php echo $esc($llm_seo('llm.badge_closed', '闭源')); ?><?php endif; ?></span></span>
                    </a>
                </li>
<?php endforeach; ?>
            </ul>
        </section>
<?php endif; ?>
        </div>
<?php endif; ?>

        <section id="llm-sources" class="llm-section">
            <h2><?php echo $esc($llm_seo('llm.sources_h2', '数据来源')); ?></h2>
            <?php
            $llmSourceEntries = [
                'lmarena' => [
                    'url' => 'https://lmarena.ai/',
                    'name' => 'LMArena',
                    'desc' => $llm_seo('llm.source_lmarena', 'Arena 评分，数据集 CC-BY-4.0'),
                ],
                'openrouter' => [
                    'url' => 'https://openrouter.ai/',
                    'name' => 'OpenRouter',
                    'desc' => $llm_seo('llm.source_openrouter', '模型价格与上架信息'),
                ],
                'aa' => [
                    'url' => 'https://artificialanalysis.ai/',
                    'name' => 'Artificial Analysis',
                    'desc' => $llm_seo('llm.source_aa', '评测智能指数，未在本表展示'),
                ],
            ];
            // 只列出快照里真正成功的来源 —— 示例数据下不展示任何第三方标识
            $llmActiveSources = array_filter(
                $llmSourceEntries,
                function ($row, $key) use ($snapshot) {
                    return ($snapshot['sources'][$key]['ok'] ?? false) === true;
                },
                ARRAY_FILTER_USE_BOTH
            );
            ?>
<?php if (!empty($llmActiveSources)): ?>            <ul class="llm-source-list">
<?php foreach ($llmActiveSources as $srcEntry): ?>                <li><a class="llm-source-link" href="<?php echo $esc($srcEntry['url']); ?>" target="_blank" rel="noopener noreferrer"><img class="llm-source-logo" src="<?php echo $esc($srcEntry['logo']); ?>" alt="" width="24" height="24" loading="lazy"><span><strong class="llm-source-name"><?php echo $esc($srcEntry['name']); ?></strong><small><?php echo $esc($srcEntry['desc']); ?></small></span></a></li>
<?php endforeach; ?>            </ul>
<?php endif; ?>
            <p class="llm-source-note"><?php echo $esc($llm_seo('llm.source_note', '本页为非官方镜像，不自造总分；价格为 OpenRouter 平台报价（$/1M tokens）；Arena 分为 Bradley-Terry 口径，不是 Elo。')); ?></p>
        </section>

        <section id="llm-faq" class="llm-section">
            <h2><?php echo $esc($llm_seo('llm.faq_h2', '常见问题')); ?></h2>
<?php foreach ($faqItems as $item): if ($item['q'] === '' || $item['a'] === '') continue; ?>
            <details class="llm-faq-item">
                <summary><span><?php echo $esc($item['q']); ?></span></summary>
                <div class="llm-faq-answer"><p><?php echo isset($item['a_html']) && $item['a_html'] !== '' ? $item['a_html'] : $esc($item['a']); ?></p></div>
            </details>
<?php endforeach; ?>
        </section>
    </div>
</main>

<?php include('includes/footer.php'); ?>

<script>
window.LLM_BOARD = <?php echo $snapshot === null ? 'null' : json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
// 气泡半径（按模型 id）：SSR 与 JS 重绘读同一份，避免两边各算一遍导致同形被破坏
// unit 说明这个尺寸代表什么（价格视图=speed，速度视图=price）——图例/提示文案据此选词
window.LLM_CHART_BUBBLE = <?php echo $j(['r' => $bubbleR, 'lo' => $bubbleLo, 'hi' => $bubbleHi, 'rMin' => $bubbleRMin, 'rMax' => $bubbleRMax, 'unit' => $bubbleUnit]); ?>;
// 图表横轴口径（price|speed）：SSR 与 JS 重绘必须读同一个值，否则同一次加载会画出两张不同的图
window.LLM_CHART_AXIS = <?php echo $j($chartAxis); ?>;
window.LLM_ICON_BASE = <?php echo $j(LLM_ICON_BASE); ?>;
window.LLM_SITE_NAME = <?php echo $j($siteName); ?>;
window.LLM_ICON_VERSION = <?php echo $j(ASSET_VERSION); ?>;
window.LLM_COL_LABELS = <?php echo $j($colLabels); ?>;
window.LLM_EFFORT = <?php echo $j(['labels' => $effortLabels, 'tip' => $effortTipTemplate]); ?>;
// 上架时间的相对时间文案（今天/昨天/{n} 天前/{n} 周前/{n} 个月前/{n} 年前）：
// SSR 与 JS 共用同一份，见页面里的 $listedCopy
window.LLM_LISTED = <?php echo $j($listedCopy); ?>;
window.LLM_RANK_DELTA = <?php echo $j($rankDelta); ?>;
// Arena 置信区间徽标的悬浮说明（{low}/{high}/{half}）：SSR 与 JS 共用同一份，见 $ciTipTemplate
window.LLM_CI = <?php echo $j(['tip' => $ciTipTemplate, 'resolution' => $ciResolutionTemplate]); ?>;
// 行内明细的标签（句子沿用各自的 tip 文案，见 scripts/i18n/llm-leaderboard/15-row-detail.php）
window.LLM_DETAIL = <?php echo $j([
    'more' => $llm_seo('llm.detail_more', '展开该行明细'),
    'less' => $llm_seo('llm.detail_less', '收起该行明细'),
    'ci' => $llm_seo('llm.detail_ci', '95% 区间'),
    'votes' => $llm_seo('llm.detail_votes', 'Arena 票数'),
    'move' => $llm_seo('llm.detail_move', '较上期名次'),
]); ?>;
// Arena 格的悬浮说明（JS 重渲染用；与 SSR 首屏同一句，避免长短不一）
window.LLM_ARENA_TIP = <?php echo $j($arenaTipBase); ?>;
window.LLM_MODEL_META = <?php
$llmModelMeta = [];
foreach ($llmAliases as $canonical => $entry) {
    $slug = (string)($entry['icon'] ?? '');
    if (preg_match('/^[a-z0-9-]+$/', $slug) === 1) $llmModelMeta[$canonical] = ['icon' => $slug];
}
echo $j($llmModelMeta);
?>;
window.LLM_I18N = <?php echo $j($i18n->getByPrefix('llm.js.')); ?>;
window.LLM_LEGACY_NOTES = <?php echo $j(['code' => __('llm.ui.legacy_code'), 'intel' => __('llm.ui.legacy_intel')]); ?>;
<?php
// 8d：JS 切换视图时同步 title/H1/芯片文案（步骤 7 起与 SSR 同一套 i18n 键 + 中文回落）
$llmMetaForJs = ['view' => $view, 'lang' => $currentLang, 'h1' => [], 'title' => [], 'intro' => []];
foreach ($llmViews as $v) {
    $seoRow = $llmViewSeo[$v] ?? $llmViewSeo['all'];
    $llmMetaForJs['intro'][$v] = __($v === 'speed' ? 'llm.ui.chart_intro_speed' : ($v === 'pareto' ? 'llm.ui.chart_intro' : 'llm.ui.intro'));
    $llmMetaForJs['h1'][$v] = $llm_seo($seoRow['h1'][0], $seoRow['h1'][1]);
    $llmMetaForJs['title'][$v] = $llm_seo($seoRow['title'][0], $seoRow['title'][1], ['year' => $seoYear]);
}
echo 'window.LLM_VIEW_META = ' . $j($llmMetaForJs) . ';';
?>
</script>
<script src="<?php echo asset_url_auto('/assets/js/llm-leaderboard.js'); ?>&llm=<?php echo LLM_ASSET_VER; ?>" defer></script>
<script src="<?php echo asset_url_auto('/assets/js/llm-pareto-tip.js'); ?>&llm=<?php echo LLM_ASSET_VER; ?>" defer></script>
<script src="<?php echo asset_url_auto('/assets/js/llm-events.js'); ?>&llm=<?php echo LLM_ASSET_VER; ?>" defer></script>
<script src="<?php echo asset_url_auto('/assets/js/llm-share-image.js'); ?>&llm=<?php echo LLM_ASSET_VER; ?>" defer></script>

</body>
</html>
