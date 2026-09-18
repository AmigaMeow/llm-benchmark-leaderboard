<?php
/**
 * og:image 的共用逻辑：模型卡的文件名规则，以及「有就用、没有就回落」的解析。
 *
 * 生成脚本（tools/generate-og-image.php）和模型页（llm-model.php）都 require 本文件。
 */

function og_site_url(): string
{
    if (class_exists('SiteSettings')) {
        return rtrim(SiteSettings::getSiteUrl(), '/');
    }
    return '';
}

/** 模型 id（形如 anthropic/claude-fable-5.1）→ 安全文件名（不含扩展名） */
function og_model_slug(string $id): string
{
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $id) ?? '');
    return trim($slug, '-');
}

/**
 * 模型专属分享卡的绝对 URL；没有生成过就返回 null，由调用方回落到站点兜底卡。
 */
function og_model_card_url(string $id, string $siteRoot): ?string
{
    $slug = og_model_slug($id);
    if ($slug === '') return null;
    $path = rtrim($siteRoot, '/') . '/assets/images/og/models/' . $slug . '.png';
    if (!is_file($path)) return null;
    return og_site_url() . '/assets/images/og/models/' . $slug . '.png?v=' . filemtime($path);
}

/** 分享卡（og:image）的按语言挑选器：找不到本地化文件时回落到默认卡。 */
function og_image_lang_suffix(): string
{
    $lang = function_exists('i18n_lang') ? i18n_lang() : 'zh-CN';
    if ($lang === 'zh-CN') {
        return '';
    }
    $known = ['zh-TW' => '-tw', 'en-US' => '-en', 'ja-JP' => '-ja'];
    return $known[$lang] ?? '-en';
}

/** 取某张分享卡的 URL（带 ?v= 缓存版本）。$key 形如 default / llm-leaderboard。 */
function og_image_url(string $key): string
{
    $dir = realpath(__DIR__ . '/../assets/images/og');
    $base = og_site_url();
    if ($dir === false) {
        return $base . '/assets/images/og/' . $key . '.png';
    }
    foreach ([og_image_lang_suffix(), ''] as $suffix) {
        $file = $dir . '/' . $key . $suffix . '.png';
        if (is_file($file)) {
            return $base . '/assets/images/og/' . $key . $suffix . '.png?v=' . filemtime($file);
        }
    }
    return $base . '/assets/images/og/' . $key . '.png';
}

/** og:image:alt 按语言走 */
function og_image_alt(): string
{
    $lang = function_exists('i18n_lang') ? i18n_lang() : 'zh-CN';
    $map = [
        'zh-CN' => 'Benchmark leaderboard',
        'zh-TW' => 'Benchmark leaderboard',
        'en-US' => 'Benchmark leaderboard',
        'ja-JP' => 'Benchmark leaderboard',
    ];
    return $map[$lang] ?? $map['en-US'];
}
