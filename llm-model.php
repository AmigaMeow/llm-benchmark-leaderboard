<?php
/** 模型详情：仅从本地排行榜快照读取，不请求任何第三方 API。 */
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/og-image.php';
$i18n = I18n::getInstance();
$currentLang = $i18n->getCurrentLanguage();
$siteUrl = rtrim((string)SiteSettings::getSiteUrl(), '/');
$siteName = SiteSettings::getSiteName();

if (!defined('LLM_ICON_BASE')) {
    define('LLM_ICON_BASE', '/assets/llm/icons/'); // P1: 自托管 vendor 图标（原 unpkg 第三方 CDN）
}

$esc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$t = static function (string $key, string $fallback, array $params = []): string {
    $text = __($key, $params);
    return ($text === '' || $text === $key) ? strtr(str_replace('17NAS', $GLOBALS['siteName'], $fallback), array_combine(
        array_map(static fn($k) => '{' . $k . '}', array_keys($params)),
        array_map('strval', array_values($params))
    ) ?: []) : $text;
};
$formatPrice = static function ($value): string {
    if ($value === null) return '—';
    if ((float)$value === 0.0) return '0';
    if ((float)$value >= 100) return '$' . number_format((float)$value, 0);
    if ((float)$value >= 1) return '$' . number_format((float)$value, 2);
    return '$' . rtrim(rtrim(number_format((float)$value, 4, '.', ''), '0'), '.');
};

$id = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
$snapshot = null;
$snapshotFile = __DIR__ . '/cache/llm/llm-leaderboard.json';
if (is_readable($snapshotFile)) {
    $decoded = json_decode((string)file_get_contents($snapshotFile), true);
    if (is_array($decoded) && is_array($decoded['models'] ?? null)) $snapshot = $decoded;
}

$model = null;
if ($snapshot !== null && $id !== '') {
    foreach ($snapshot['models'] as $candidate) {
        if (is_array($candidate) && (string)($candidate['id'] ?? '') === $id) {
            $model = $candidate;
            break;
        }
    }
}
// 快照内收录的模型都可查看（含闭源）；只有快照里查不到的 id 才 404。
// 闭源模型此前一律 404，导致排行榜前列的 Claude / GPT / Gemini 无法点进详情页。
$found = $model !== null;
if (!$found) {
    http_response_code(404);
    header('X-Robots-Tag: noindex, follow', true);
}

// 开放权重三态：true / false / null（部分模型上游未标注，按未知处理，不臆断）
$isOpen = $found && (($model['open_weights'] ?? null) === true);
$openWeightsText = !$found
    ? '—'
    : (($model['open_weights'] ?? null) === true
        ? $t('llm.model.yes', '是')
        : (($model['open_weights'] ?? null) === false ? $t('llm.model.no', '否') : '—'));

$aliases = require __DIR__ . '/config/llm_model_aliases.php';
$alias = $found && isset($aliases[$id]) && is_array($aliases[$id]) ? $aliases[$id] : [];
$displayName = $found ? (string)($model['display_name'] ?? $id) : $t('llm.model.not_found_title', '未找到该模型');
// 标题后缀按开放权重视角区分。注意：llm.model.title_suffix 是库里已有的旧键，
// 译文为「开源大模型 / Open-Source LLM」，只能用于开源模型；闭源另用新键，否则会把
// Claude 之类闭源模型标成 open-source。
$titleSuffix = $isOpen
    ? $t('llm.model.title_suffix', '开源大模型 | 17NAS')
    : $t('llm.model.title_suffix_closed', '大模型 | 17NAS');
// P4：title 关键词化（参考 AA/LMArena 模型页 title 模式）
$pageTitle = $found
    ? $t($isOpen ? 'llm.model.title_kw_open' : 'llm.model.title_kw_closed', '{model} 排名、Arena 分数、API 价格 | 17NAS', ['model' => $displayName])
    : $displayName . ' | ' . $siteName;
$siteDescription = $found
    ? $t('llm.model.description', '查看 {model} 的 Arena 评分与排名、API 输入输出价格、上下文长度和上架时间。', ['model' => $displayName])
    : $t('llm.model.not_found_message', '该模型不在本站收录范围内，无法查看详情。');
$canonicalUrl = $siteUrl . '/llm-model.php?id=' . rawurlencode($id);
// P4：zh-CN（默认语言）canonical 用裸 URL，与 hreflang 互相一致
$curLang = $i18n->getCurrentLanguage();
$selfCanonicalUrl = i18n_canonical_url($canonicalUrl, $curLang);
$seoKeywords = $isOpen
    ? $displayName . ',' . $t('llm.model.kw_open', '开源大模型,开放权重模型,模型价格')
    : $displayName . ',' . $t('llm.model.kw_closed', '大模型,API价格,Arena评分,上下文长度');

// 上下文长度：按行业惯例用 K / M 紧凑显示（128K、1M、1.31M）
$formatContext = static function ($value): string {
    if ($value === null || $value === '') return '—';
    $n = (float)$value;
    if ($n <= 0) return '—';
    if ($n >= 1000000) return rtrim(rtrim(number_format($n / 1000000, 2, '.', ''), '0'), '.') . 'M';
    if ($n >= 1000) return rtrim(rtrim(number_format($n / 1000, 1, '.', ''), '0'), '.') . 'K';
    return number_format($n, 0, '.', '');
};
$ogTitle = $twitterTitle = $pageTitle;
$ogDescription = $twitterDescription = $siteDescription;

$iconSlug = (string)($alias['icon'] ?? '');
if (preg_match('/^[a-z0-9-]+$/', $iconSlug) !== 1) $iconSlug = '';
$hfRepo = $found ? trim((string)($model['hf_repo'] ?? '')) : '';
if (preg_match('/^[\w.-]+\/[\w.-]+$/', $hfRepo) !== 1) $hfRepo = '';
$hfUrl = '';
if ($hfRepo !== '') {
    [$hfOrg, $hfName] = explode('/', $hfRepo, 2);
    $hfUrl = 'https://huggingface.co/' . rawurlencode($hfOrg) . '/' . rawurlencode($hfName);
}
$homepage = trim((string)($alias['homepage'] ?? ''));
if ($homepage !== '' && (!filter_var($homepage, FILTER_VALIDATE_URL) || !in_array(parse_url($homepage, PHP_URL_SCHEME), ['http', 'https'], true))) {
    $homepage = '';
}

// 排行榜口径：只统计有 Arena 评分的模型（与排行榜页一致），用于排名上下文
$rankedList = [];
if ($snapshot !== null) {
    foreach ($snapshot['models'] as $m) {
        if (is_array($m) && ($m['arena_score'] ?? null) !== null) $rankedList[] = $m;
    }
    usort($rankedList, static function ($a, $b) {
        return (float)$b['arena_score'] <=> (float)$a['arena_score'];
    });
}
$rankTotal = count($rankedList);
$topModel = $rankedList[0] ?? null;
$rankPos = null;
if ($found && ($model['arena_score'] ?? null) !== null) {
    foreach ($rankedList as $i => $rm) {
        if ((string)($rm['id'] ?? '') === (string)$id) { $rankPos = $i + 1; break; }
    }
}

// 同厂商其它模型（含未评分的，未评分沉底），给详情页补内链与上下文，最多 6 个
// P4.5：模型 FAQ（3 问，全部由快照数据生成，不造事实；正文与 FAQPage JSON-LD 共用）
$mFaq = [];
if ($found) {
    $fmtUsd4 = static function ($v) {
        if ($v === null) return null;
        return '$' . rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
    };
    if ($rankPos !== null) {
        $mFaq[] = ['q' => $t('llm.model.faq_q1', '{model} 现在排名第几？', ['model' => $displayName]),
            'a' => $t('llm.model.faq_a1', '{model} 在本站排行榜（共 {count} 个已评分模型）中排第 {rank} 名，Arena 分 {score}；LMArena 原始名次为 #{arank}。',
                ['model' => $displayName, 'count' => (int)$rankTotal, 'rank' => (int)$rankPos,
                 'score' => number_format((float)$model['arena_score'], 1), 'arank' => (int)($model['arena_rank'] ?? 0)])];
    } else {
        $mFaq[] = ['q' => $t('llm.model.faq_q1', '{model} 现在排名第几？', ['model' => $displayName]),
            'a' => $t('llm.model.faq_a1u', '{model} 尚未被 LMArena 评分，暂未进入排行榜。', ['model' => $displayName])];
    }
    $pin4 = $model['price_in'] ?? null;
    $pout4 = $model['price_out'] ?? null;
    if ($pin4 !== null || $pout4 !== null) {
        $mFaq[] = ['q' => $t('llm.model.faq_q2', '{model} 的 API 价格是多少？', ['model' => $displayName]),
            'a' => $t('llm.model.faq_a2', '输入 {pin} / 输出 {pout}（每百万 token，OpenRouter 报价，可能随官方调整变动）。',
                ['pin' => $fmtUsd4($pin4) ?? '—', 'pout' => $fmtUsd4($pout4) ?? '—'])];
    } else {
        $mFaq[] = ['q' => $t('llm.model.faq_q2', '{model} 的 API 价格是多少？', ['model' => $displayName]),
            'a' => $t('llm.model.faq_a2u', '暂无公开的 API 报价。')];
    }
    $lic4 = trim((string)($model['license'] ?? ''));
    if (($model['open_weights'] ?? null) === true) {
        $mFaq[] = ['q' => $t('llm.model.faq_q3', '{model} 是开源的吗？能本机部署吗？', ['model' => $displayName]),
            'a' => $t('llm.model.faq_a3_open', '{model} 是开放权重模型（许可证：{license}），可下载权重自行部署。', ['model' => $displayName, 'license' => $lic4 !== '' ? $lic4 : '—'])];
    } else {
        $mFaq[] = ['q' => $t('llm.model.faq_q3', '{model} 是开源的吗？能本机部署吗？', ['model' => $displayName]),
            'a' => $t('llm.model.faq_a3_closed', '{model} 是闭源模型，只能通过官方 API 或托管服务使用。', ['model' => $displayName])];
    }
}

$siblings = [];
if ($found && $snapshot !== null) {
    $selfOrg = (string)($model['org'] ?? '');
    $cand = [];
    foreach ($snapshot['models'] as $m) {
        if (!is_array($m) || (string)($m['id'] ?? '') === (string)$id) continue;
        if ((string)($m['org'] ?? '') !== $selfOrg) continue;
        $cand[] = $m;
    }
    usort($cand, static function ($a, $b) {
        $as = $a['arena_score'] ?? null;
        $bs = $b['arena_score'] ?? null;
        if ($as === null && $bs === null) return 0;
        if ($as === null) return 1;
        if ($bs === null) return -1;
        return (float)$bs <=> (float)$as;
    });
    $siblings = array_slice($cand, 0, 6);
}
?>
<!doctype html>
<html lang="<?php echo $esc($currentLang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="format-detection" content="telephone=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $esc($pageTitle); ?></title>
    <meta name="keywords" content="<?php echo $esc($seoKeywords); ?>">
    <meta name="description" content="<?php echo $esc($siteDescription); ?>">
    <meta name="author" content="<?php echo $esc($siteName); ?>">
    <link rel="canonical" href="<?php echo $esc($selfCanonicalUrl); ?>">
<?php i18n_render_hreflang($canonicalUrl); ?>
<?php
/* 每模型一张专属分享卡，由 tools/generate-og-image.php 在同步时生成到
   assets/images/og/models/（该目录是派生品，不进 git）。
   拿到 URL 才设 $ogImage：它有两个作用 —— 一是下面那段标签有值可输出，
   二是让 head-meta.php 的站点兜底卡让位（否则会同时输出两条 og:image）。
   文件不在就别设，兜底卡接手 —— 宁可显示一张通用卡，也绝不出现指向空文件的 og:image。 */
$ogModelCardUrl = $found ? og_model_card_url($id, __DIR__) : null;
if ($ogModelCardUrl !== null) {
    $ogImage = $ogModelCardUrl;
}
include __DIR__ . '/includes/head-meta.php';
?>
<?php if ($found): ?>    <meta name="robots" content="index,follow,max-image-preview:large">
<?php else: ?>    <meta name="robots" content="noindex,follow">
    <meta name="googlebot" content="noindex,follow">
<?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $esc($canonicalUrl); ?>">
    <meta property="og:title" content="<?php echo $esc($ogTitle); ?>">
    <meta property="og:description" content="<?php echo $esc($ogDescription); ?>">
    <meta property="og:site_name" content="<?php echo $esc($siteName); ?>">
<?php if (isset($ogImage)): ?>
    <meta property="og:image" content="<?php echo $esc($ogImage); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?php echo $esc($ogTitle); ?>">
    <meta name="twitter:image" content="<?php echo $esc($ogImage); ?>">
<?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <style>
    .llm-model-page{background:#fff;color:#111827}.llm-model-shell{max-width:920px;margin:0 auto;padding:0 24px 80px}
    .llm-model-hero{display:flex;align-items:center;gap:14px;margin:8px 0 28px}.llm-model-hero h1{font-size:34px;line-height:1.2;margin:0 0 5px}.llm-model-hero p{margin:0;color:#6b7280}
    .llm-org-icon,.llm-org-mark{flex:0 0 24px;width:24px;height:24px}.llm-org-icon{object-fit:contain;display:block}.llm-org-icon--anthropic{filter:brightness(0) saturate(100%) invert(46%) sepia(93%) saturate(1100%) hue-rotate(350deg) brightness(96%)}.llm-org-icon--openai{filter:brightness(0) saturate(100%) invert(42%) sepia(57%) saturate(1000%) hue-rotate(112deg) brightness(88%)}.llm-org-icon--gemini{filter:brightness(0) saturate(100%) invert(42%) sepia(99%) saturate(1700%) hue-rotate(205deg) brightness(99%)}.llm-org-icon--xai{filter:brightness(0) saturate(100%) invert(10%) sepia(20%) saturate(1300%) hue-rotate(180deg) brightness(90%)}.llm-org-icon--meta{filter:brightness(0) saturate(100%) invert(35%) sepia(93%) saturate(1700%) hue-rotate(205deg) brightness(102%)}.llm-org-icon--zhipu,.llm-org-icon--qwen,.llm-org-icon--minimax,.llm-org-icon--moonshot,.llm-org-icon--deepseek,.llm-org-icon--tencent,.llm-org-icon--doubao,.llm-org-icon--xiaomi{filter:brightness(0) saturate(100%) invert(38%) sepia(80%) saturate(1200%) hue-rotate(190deg) brightness(97%)}.llm-org-mark{border-radius:8px;background:#2563eb;color:#fff;font-weight:700;line-height:24px;text-align:center;font-size:13px}
    .llm-model-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:24px}.llm-model-fact{padding:18px;border:1px solid #e5e7eb;border-radius:12px}.llm-model-fact dt{font-size:13px;color:#6b7280;margin-bottom:8px}.llm-model-fact dd{font-size:18px;font-weight:600;margin:0;overflow-wrap:anywhere}
    .llm-model-actions{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 32px}.llm-model-actions a{display:inline-flex;padding:10px 16px;border-radius:9px;background:#111827;color:#fff;text-decoration:none}.llm-model-variants{margin:0 0 28px}.llm-model-variants h2{font-size:18px;font-weight:700;margin:0 0 4px}.llm-model-variants h2 small{font-weight:400;color:#6b7280;font-size:13px;margin-left:8px}.llm-variant-table{width:100%;border-collapse:collapse;font-variant-numeric:tabular-nums}.llm-variant-table th,.llm-variant-table td{padding:9px 12px;border:1px solid #e5e7eb;text-align:left;font-size:14px}.llm-variant-table thead th{background:#f9fafb;color:#6b7280;font-weight:600}.llm-variant-table tbody th{font-weight:600}.llm-variant-table tr.is-current td,.llm-variant-table tr.is-current th{background:#f0f6ff}.llm-variant-cur{display:inline-block;margin-left:6px;padding:1px 7px;border-radius:99px;background:#2563eb;color:#fff;font-size:11px;font-weight:600}.llm-model-back{border-top:1px solid #e5e7eb;padding-top:24px}.llm-model-back a{color:#2563eb}.llm-model-error{padding:28px;border:1px solid #fecaca;background:#fef2f2;border-radius:12px;margin-bottom:28px}
    .llm-model-context{margin:-16px 0 24px;padding:12px 14px;border-left:3px solid #2563eb;background:#f5f8ff;color:#46546a;font-size:14px;line-height:1.65}
    .llm-model-siblings{margin:0 0 28px}.llm-model-siblings h2{font-size:18px;font-weight:700;color:#111827;margin:0 0 12px}
    .llm-sibling-list{list-style:none;margin:0;padding:0}.llm-sibling-list li{display:flex;align-items:baseline;justify-content:space-between;gap:12px;padding:10px 14px;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:8px}
    .llm-sibling-list a{color:#111827;font-weight:600;text-decoration:none}.llm-sibling-list a:hover{color:#2563eb;text-decoration:underline}
    .llm-sibling-meta{color:#6b7280;font-size:13px;font-variant-numeric:tabular-nums;white-space:nowrap}
    @media(max-width:700px){.llm-model-grid{grid-template-columns:1fr 1fr}.llm-model-hero h1{font-size:28px}}@media(max-width:440px){.llm-model-grid{grid-template-columns:1fr}}
    .llm-model-faq{margin:0 0 28px}.llm-model-faq h2{font-size:18px;font-weight:700;margin:0 0 12px}.llm-faq-item{padding:14px 16px;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:10px}.llm-faq-item h3{font-size:15px;margin:0 0 6px}.llm-faq-item p{margin:0;color:#46546a;font-size:14px;line-height:1.65}
    </style>
<?php if ($found): ?>
    <!-- P4：结构化数据（WebPage + SoftwareApplication + BreadcrumbList；与页面可见值同源） -->
    <script type="application/ld+json">
    <?php
    $j4 = static function ($v) { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); };
    $modelUrl4 = $siteUrl . '/llm-model.php?id=' . rawurlencode((string)$id);
    $props4 = [];
    $add4 = static function (string $name, $value) use (&$props4) {
        if ($value === null || $value === '') return;
        $props4[] = ['@type' => 'PropertyValue', 'name' => $name, 'value' => $value];
    };
    $add4('Arena score', ($model['arena_score'] ?? null) !== null ? round((float)$model['arena_score'], 1) : null);
    $add4('LMArena rank', ($model['arena_rank'] ?? null) !== null ? '#' . (int)$model['arena_rank'] : null);
    $add4('Input price USD per 1M tokens', $model['price_in'] ?? null);
    $add4('Output price USD per 1M tokens', $model['price_out'] ?? null);
    $add4('Context length (tokens)', $model['context_length'] ?? null);
    $add4('Open weights', ($model['open_weights'] ?? null) === true ? 'true' : (($model['open_weights'] ?? null) === false ? 'false' : null));
    $add4('Intelligence index', isset($model['aa_intelligence']) && $model['aa_intelligence'] !== null ? round((float)$model['aa_intelligence'], 1) : null);
    $add4('Coding index', isset($model['aa_coding']) && $model['aa_coding'] !== null ? round((float)$model['aa_coding'], 1) : null);
    $add4('Output speed (tokens per second)', isset($model['aa_speed']) && $model['aa_speed'] !== null ? round((float)$model['aa_speed'], 1) : null);
    $add4('Time to first token (seconds)', isset($model['aa_ttft']) && $model['aa_ttft'] !== null ? round((float)$model['aa_ttft'], 2) : null);
    // head 渲染时 $breadcrumbs 尚未定义（body 里才构建），此处内联构建同一层级
    $crumb4 = [
        ['@type' => 'ListItem', 'position' => 1, 'name' => $t('llm.model.breadcrumb_home', '首页'), 'item' => $siteUrl . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $t('llm.model.breadcrumb_board', '大模型排行榜'), 'item' => $siteUrl . '/llm-leaderboard.php'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $displayName],
    ];
    echo $j4([
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'WebPage',
                '@id' => $modelUrl4 . '#webpage',
                'url' => $modelUrl4,
                'name' => $pageTitle,
                'description' => $siteDescription,
                'inLanguage' => $curLang !== '' ? $curLang : 'zh-CN',
            ],
            [
                '@type' => 'SoftwareApplication',
                '@id' => '#model',
                'name' => $displayName,
                'applicationCategory' => 'Artificial Intelligence Model',
                'operatingSystem' => 'API',
                'creator' => ['@type' => 'Organization', 'name' => (string)($model['org'] ?? '')],
                'additionalProperty' => $props4,
            ],
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => $crumb4,
            ],
        ],
    ]);
    ?>
    </script>
<?php if ($found && $mFaq !== []): ?>
    <script type="application/ld+json">
    <?php
    $faqEnt4 = [];
    foreach ($mFaq as $f4) {
        $faqEnt4[] = ['@type' => 'Question', 'name' => $f4['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f4['a']]];
    }
    echo $j4(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqEnt4]);
    ?>
    </script>
<?php endif; ?>
<?php endif; ?>
<script src="<?php echo asset_url_auto('/assets/js/llm-events.js'); ?>" defer></script>
</head>
<body>
<?php $current_page = 'llm-leaderboard'; include __DIR__ . '/includes/header.php'; ?>
<main class="llm-model-page" role="main"><div class="llm-model-shell">
<?php
// 面包屑的上一级：开源模型指向 ?view=open，闭源/未知指向总榜（指到开源榜是错的路径）
$breadcrumbs = [
    ['name' => $t('llm.model.breadcrumb_home', '首页'), 'url' => '/'],
];
if ($isOpen) {
    $breadcrumbs[] = ['name' => $t('llm.model.breadcrumb_board', '大模型排行榜'), 'url' => '/llm-leaderboard.php'];
    $breadcrumbs[] = ['name' => $t('llm.model.breadcrumb_open', '开源大模型排行榜'), 'url' => '/llm-leaderboard.php?view=open'];
} else {
    $breadcrumbs[] = ['name' => $t('llm.model.breadcrumb_board', '大模型排行榜'), 'url' => '/llm-leaderboard.php'];
}
$breadcrumbs[] = ['name' => $displayName];
include __DIR__ . '/includes/breadcrumb.php';
?>
<?php if (!$found): ?>
    <section class="llm-model-error"><h1><?php echo $esc($displayName); ?></h1><p><?php echo $esc($siteDescription); ?></p></section>
<?php else: ?>
    <header class="llm-model-hero">
<?php if ($iconSlug !== ''): ?>        <img class="llm-org-icon llm-org-icon--<?php echo $esc($iconSlug); ?>" width="24" height="24" alt="" aria-hidden="true" src="<?php echo $esc(asset_url_auto(LLM_ICON_BASE . $iconSlug . '.svg')); ?>" onerror="this.style.display='none';this.nextElementSibling.hidden=false">
<?php endif; ?>        <span class="llm-org-mark"<?php echo $iconSlug !== '' ? ' hidden' : ''; ?>><?php echo $esc(mb_strtoupper(mb_substr((string)($model['org'] ?? '?'), 0, 1))); ?></span>
        <div><h1><?php echo $esc($displayName); ?></h1><p><?php echo $esc($model['org'] ?? '—'); ?></p></div>
    </header>
<?php if ($rankPos !== null && $topModel !== null && ($topModel['id'] ?? null) !== ($model['id'] ?? null) && ($topModel['arena_score'] ?? null) !== null): ?>
    <p class="llm-model-context"><?php echo $esc($t('llm.model.rank_vs_top', '{model} 的 Arena 分为 {score}，比榜首 {top}（{topScore} 分）低 {diff} 分。', ['model' => $displayName, 'score' => number_format((float)$model['arena_score'], 1), 'top' => (string)($topModel['display_name'] ?? ''), 'topScore' => number_format((float)$topModel['arena_score'], 1), 'diff' => number_format((float)$topModel['arena_score'] - (float)$model['arena_score'], 1)])); ?></p>
<?php elseif ($rankPos !== null && $topModel !== null && ($topModel['id'] ?? null) === ($model['id'] ?? null)): ?>
    <p class="llm-model-context"><?php echo $esc($t('llm.model.rank_top1', '{model} 是当前排行榜的第 1 名。', ['model' => $displayName])); ?></p>
<?php else: ?>
    <p class="llm-model-context"><?php echo $esc($t('llm.model.unranked_line', '该模型尚未被 LMArena 评分，暂未进入排行榜；价格与上下文长度信息每日同步。')); ?></p>
<?php endif; ?>
    <dl class="llm-model-grid">
        <div class="llm-model-fact"><dt><?php echo $esc($t('llm.model.vendor', '厂商')); ?></dt><dd><?php echo $esc($model['org'] ?? '—'); ?></dd></div>
        <div class="llm-model-fact"><dt><?php echo $esc($t('llm.model.arena_score', 'Arena 评分')); ?></dt><dd><?php echo ($model['arena_score'] ?? null) !== null ? $esc(number_format((float)$model['arena_score'], 1)) : '—'; ?></dd></div>
        <div class="llm-model-fact"><dt><?php echo $esc($t('llm.model.arena_rank', 'Arena 排名')); ?></dt><dd><?php echo ($model['arena_rank'] ?? null) !== null ? '#' . (int)$model['arena_rank'] : '—'; ?></dd></div>
        <div class="llm-model-fact"><dt><?php echo $esc($t('llm.model.input_price', '输入价（$/1M tokens）')); ?></dt><dd><?php echo $esc($formatPrice($model['price_in'] ?? null)); ?></dd></div>
        <div class="llm-model-fact"><dt><?php echo $esc($t('llm.model.output_price', '输出价（$/1M tokens）')); ?></dt><dd><?php echo $esc($formatPrice($model['price_out'] ?? null)); ?></dd></div>
        <div class="llm-model-fact"><dt><?php echo $esc($t('llm.model.context_length', '上下文长度')); ?></dt><dd><?php echo $esc($formatContext($model['context_length'] ?? null)); ?></dd></div>
        <div class="llm-model-fact"><dt><?php echo $esc($t('llm.model.listed_at', '上架时间')); ?></dt><dd><?php echo $esc($model['listed_at_iso'] ?? '—'); ?></dd></div>
        <div class="llm-model-fact"><dt><?php echo $esc($t('llm.model.license', '许可证')); ?></dt><dd><?php echo $esc($model['license'] ?? '—'); ?></dd></div>
        <div class="llm-model-fact"><dt><?php echo $esc($t('llm.model.open_weights', '开放权重')); ?></dt><dd><?php echo $esc($openWeightsText); ?></dd></div>
        <div class="llm-model-fact"><dt><?php echo $esc($t('llm.model.intel_index', '智能指数')) . ((isset($model['aa_variant']) && $model['aa_variant'] !== null && $model['aa_variant'] !== '') ? '（' . $esc((string)$model['aa_variant']) . '）' : ''); ?></dt><dd><?php echo isset($model['aa_intelligence']) && $model['aa_intelligence'] !== null ? $esc(number_format((float)$model['aa_intelligence'], 1)) : '—'; ?></dd></div>
        <div class="llm-model-fact"><dt><?php echo $esc($t('llm.model.coding_index', '编程指数')); ?></dt><dd><?php echo isset($model['aa_coding']) && $model['aa_coding'] !== null ? $esc(number_format((float)$model['aa_coding'], 1)) : '—'; ?></dd></div>
        <div class="llm-model-fact"><dt><?php echo $esc($t('llm.model.speed', '输出速度（tokens/秒）')); ?></dt><dd><?php echo isset($model['aa_speed']) && $model['aa_speed'] !== null ? $esc(number_format((float)$model['aa_speed'], 1)) : '—'; ?></dd></div>
        <div class="llm-model-fact"><dt><?php echo $esc($t('llm.model.ttft', '首 token 延迟（秒）')); ?></dt><dd><?php echo isset($model['aa_ttft']) && $model['aa_ttft'] !== null ? $esc(number_format((float)$model['aa_ttft'], 2)) : '—'; ?></dd></div>
    </dl>
<?php if ($hfUrl !== '' || $homepage !== ''): ?>    <nav class="llm-model-actions" aria-label="External links">
<?php if ($hfUrl !== ''): ?>        <a href="<?php echo $esc($hfUrl); ?>" target="_blank" rel="noopener">Hugging Face</a>
<?php endif; ?><?php if ($homepage !== ''): ?>        <a href="<?php echo $esc($homepage); ?>" target="_blank" rel="noopener"><?php echo $esc($t('llm.model.homepage', '官方网站')); ?></a>
<?php endif; ?>    </nav>
<?php endif; ?>
<?php $aaVariants = (isset($model['aa_variants']) && is_array($model['aa_variants'])) ? $model['aa_variants'] : []; ?>
<?php if (count($aaVariants) > 1): ?>
    <section class="llm-model-variants">
        <h2><?php echo $esc($t('llm.model.variants_h2', '推理档位对比')); ?> <small><?php echo $esc($t('llm.model.variants_note', '同一模型不同推理档位（Artificial Analysis 实测）')); ?></small></h2>
        <table class="llm-variant-table"><thead><tr>
            <th><?php echo $esc($t('llm.model.variant_label', '档位')); ?></th>
            <th><?php echo $esc($t('llm.model.intel_index', '智能指数')); ?></th>
            <th><?php echo $esc($t('llm.model.coding_index', '编程指数')); ?></th>
            <th><?php echo $esc($t('llm.model.speed', '输出速度（tokens/秒）')); ?></th>
            <th><?php echo $esc($t('llm.model.ttft', '首 token 延迟（秒）')); ?></th>
        </tr></thead><tbody>
<?php foreach ($aaVariants as $av): ?>
        <tr<?php echo (string)($model['aa_variant'] ?? '') === (string)$av['label'] ? ' class="is-current"' : ''; ?>>
            <th><?php echo $esc((string)$av['label']); ?><?php echo (string)($model['aa_variant'] ?? '') === (string)$av['label'] ? ' <span class="llm-variant-cur">' . $esc($t('llm.model.variant_current', '本表取值')) . '</span>' : ''; ?></th>
            <td><?php echo $av['intel'] !== null ? $esc(number_format((float)$av['intel'], 1)) : '—'; ?></td>
            <td><?php echo $av['coding'] !== null ? $esc(number_format((float)$av['coding'], 1)) : '—'; ?></td>
            <td><?php echo $av['speed'] !== null ? $esc(number_format((float)$av['speed'], 1)) : '—'; ?></td>
            <td><?php echo $av['ttft'] !== null ? $esc(number_format((float)$av['ttft'], 2)) : '—'; ?></td>
        </tr>
<?php endforeach; ?>
        </tbody></table>
    </section>
<?php endif; ?>
<?php if ($mFaq !== []): ?>
    <section class="llm-model-faq" aria-label="<?php echo $esc($t('llm.model.faq_h2', '常见问题')); ?>">
        <h2><?php echo $esc($t('llm.model.faq_h2', '常见问题')); ?></h2>
<?php foreach ($mFaq as $f4): ?>
        <div class="llm-faq-item">
            <h3><?php echo $esc($f4['q']); ?></h3>
            <p><?php echo $esc($f4['a']); ?></p>
        </div>
<?php endforeach; ?>
    </section>
<?php endif; ?>
<?php if (!empty($siblings)): ?>
    <section class="llm-model-siblings">
        <h2><?php echo $esc($t('llm.model.siblings_h2', '同厂商其它模型')); ?></h2>
        <ul class="llm-sibling-list">
<?php foreach ($siblings as $sib): ?>
            <li>
                <a href="/llm-model.php?id=<?php echo $esc(rawurlencode((string)$sib['id'])); ?>"><?php echo $esc($sib['display_name'] ?? $sib['id']); ?></a>
                <span class="llm-sibling-meta"><?php echo ($sib['arena_score'] ?? null) !== null ? 'Arena ' . $esc(number_format((float)$sib['arena_score'], 1)) : $esc($t('llm.model.pending_short', '待评分')); ?> · <?php echo $esc($formatPrice($sib['price_out'] ?? null)); ?> / 1M</span>
            </li>
<?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
<?php endif; ?>
    <p class="llm-model-back"><?php if ($isOpen): ?><a href="/llm-leaderboard.php?view=open"><?php echo $esc($t('llm.model.back_open', '返回开源大模型排行榜')); ?></a><?php else: ?><a href="/llm-leaderboard.php"><?php echo $esc($t('llm.model.back_board', '返回大模型排行榜')); ?></a><?php endif; ?></p>
</div></main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body></html>
