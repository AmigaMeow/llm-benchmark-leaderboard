<?php
/**
 * Flat-file i18n for the open-source leaderboard.
 *
 * Same public API as the production I18n class (__, i18n_lang,
 * i18n_localized_url, ...) but translations live in lang/<code>.php files
 * that return a plain key => text array instead of the database tables.
 * Keys missing from the active language fall back to zh-CN, then to the
 * key itself.
 */

class I18n {
    private static $instance = null;
    private $currentLanguage = 'zh-CN';
    private $translations = [];

    public const LANGUAGES = [
        'zh-CN' => ['name' => 'Simplified Chinese', 'native_name' => '简体中文', 'flag_icon' => 'CN'],
        'zh-TW' => ['name' => 'Traditional Chinese', 'native_name' => '繁體中文', 'flag_icon' => 'TW'],
        'en-US' => ['name' => 'English', 'native_name' => 'English', 'flag_icon' => 'US'],
        'ja-JP' => ['name' => 'Japanese', 'native_name' => '日本語', 'flag_icon' => 'JP'],
        'ko-KR' => ['name' => 'Korean', 'native_name' => '한국어', 'flag_icon' => 'KR'],
    ];

    private function __construct() {
        $this->detectLanguage();
        $this->loadTranslations();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function langFile(string $code): string {
        return __DIR__ . '/../lang/' . $code . '.php';
    }

    private function isValidLanguage($code): bool {
        return is_string($code) && isset(self::LANGUAGES[$code]) && is_file($this->langFile($code));
    }

    private function detectLanguage() {
        if (!headers_sent()) {
            header('Vary: Accept-Language, Cookie', false);
        }

        if (isset($_GET['lang']) && $this->isValidLanguage($_GET['lang'])) {
            $this->currentLanguage = $_GET['lang'];
            if (!headers_sent()) {
                setcookie('language', $this->currentLanguage, [
            'expires' => time() + 86400 * 365,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
            }
            return;
        }

        $cookieLang = $_COOKIE['language'] ?? null;
        if ($cookieLang && $this->isValidLanguage($cookieLang)) {
            $this->currentLanguage = $cookieLang;
            return;
        }

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $accepted = $this->parseAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            if ($accepted) {
                $this->currentLanguage = $accepted;
                return;
            }
        }

        $this->currentLanguage = 'zh-CN';
    }

    private function parseAcceptLanguage($acceptLanguage) {
        $aliases = [
            'en' => 'en-US', 'en-us' => 'en-US', 'en-gb' => 'en-US', 'en-au' => 'en-US',
            'zh' => 'zh-CN', 'zh-cn' => 'zh-CN', 'zh-sg' => 'zh-CN', 'zh-hans' => 'zh-CN',
            'zh-tw' => 'zh-TW', 'zh-hk' => 'zh-TW', 'zh-mo' => 'zh-TW', 'zh-hant' => 'zh-TW',
            'ja' => 'ja-JP', 'ja-jp' => 'ja-JP',
            'ko' => 'ko-KR', 'ko-kr' => 'ko-KR',
        ];
        foreach (explode(',', (string)$acceptLanguage) as $item) {
            $parts = array_map('trim', explode(';', $item));
            $code = strtolower(str_replace('_', '-', $parts[0] ?? ''));
            if ($code === '' || $code === '*') {
                continue;
            }
            $canonical = strtolower((string)strtok($code, '-'));
            $region = strpos($code, '-') !== false ? substr($code, strpos($code, '-') + 1) : '';
            if ($region !== '') {
                $canonical .= '-' . strtoupper($region);
            }
            foreach ([$canonical, $code, $aliases[$code] ?? null, $aliases[$canonical] ?? null] as $candidate) {
                if ($candidate && $this->isValidLanguage($candidate)) {
                    return $candidate;
                }
            }
        }
        return null;
    }

    public function setLanguage($code) {
        if (!$this->isValidLanguage($code)) {
            return false;
        }
        $this->currentLanguage = $code;
        $this->translations = [];
        $this->loadTranslations();
        return true;
    }

    public function getCurrentLanguage() {
        return $this->currentLanguage;
    }

    public function getEnabledLanguages() {
        $languages = [];
        $order = 0;
        foreach (self::LANGUAGES as $code => $meta) {
            if (!is_file($this->langFile($code))) {
                continue;
            }
            $languages[] = [
                'code' => $code,
                'name' => $meta['name'],
                'native_name' => $meta['native_name'],
                'flag_icon' => $meta['flag_icon'],
                'is_default' => $code === 'zh-CN' ? 1 : 0,
                'sort_order' => $order++,
            ];
        }
        return $languages;
    }

    private function loadTranslations() {
        $base = [];
        $baseFile = $this->langFile('zh-CN');
        if (is_file($baseFile)) {
            $base = require $baseFile;
        }
        if ($this->currentLanguage !== 'zh-CN' && is_file($this->langFile($this->currentLanguage))) {
            $overlay = require $this->langFile($this->currentLanguage);
            $base = array_merge($base, array_filter($overlay, static fn($v) => $v !== null && $v !== ''));
        }
        $this->translations = $base;
    }

    public function t($key, $params = []) {
        $text = $this->translations[$key] ?? $key;
        if (strpos($text, '{site}') !== false) {
            $text = str_replace('{site}', class_exists('SiteSettings') ? SiteSettings::getSiteName() : 'Leaderboard', $text);
        }
        foreach ($params as $paramKey => $value) {
            $text = str_replace('{' . $paramKey . '}', (string)$value, $text);
        }
        return $text;
    }

    public function getByPrefix($prefix) {
        $len = strlen($prefix);
        $result = [];
        foreach ($this->translations as $key => $text) {
            if (strncmp($key, $prefix, $len) === 0) {
                $result[substr($key, $len)] = $text;
            }
        }
        return $result;
    }

    /**
     * SEO metadata for a page. Flat-file mode reads seo.<page>.* keys from
     * the language files; missing keys return empty strings so callers fall
     * back to SiteSettings defaults.
     */
    public function seo($pageKey) {
        $fields = ['meta_title', 'meta_description', 'meta_keywords', 'og_title', 'og_description', 'twitter_title', 'twitter_description'];
        $seo = [];
        foreach ($fields as $field) {
            $seo[$field] = $this->translations['seo.' . $pageKey . '.' . $field] ?? '';
        }
        return $seo;
    }

    public function renderSeoMeta($pageKey) {
        $seo = $this->seo($pageKey);
        foreach (['meta_description' => 'description', 'meta_keywords' => 'keywords'] as $field => $name) {
            if ($seo[$field] !== '') {
                echo '<meta name="' . $name . '" content="' . htmlspecialchars($seo[$field]) . '">' . "\n";
            }
        }
    }

    public function formatNumber($number, $decimals = 0) {
        $locale = [
            'zh-CN' => [',', '.'], 'en-US' => [',', '.'], 'ja-JP' => [',', '.'],
            'de-DE' => ['.', ','], 'fr-FR' => [' ', ','],
        ];
        $current = $locale[$this->currentLanguage] ?? $locale['zh-CN'];
        return number_format($number, $decimals, $current[1], $current[0]);
    }

    public function formatDate($timestamp, $format = 'short') {
        $formats = [
            'zh-CN' => ['short' => 'Y年n月j日', 'long' => 'Y年n月j日 H:i:s'],
            'en-US' => ['short' => 'M j, Y', 'long' => 'M j, Y H:i:s'],
            'ja-JP' => ['short' => 'Y年n月j日', 'long' => 'Y年n月j日 H:i:s'],
        ];
        $formatStr = $formats[$this->currentLanguage][$format] ?? $formats['zh-CN'][$format];
        return date($formatStr, is_numeric($timestamp) ? $timestamp : strtotime($timestamp));
    }
}

function __($key, $params = []) {
    return I18n::getInstance()->t($key, $params);
}

function i18n_seo($pageKey) {
    return I18n::getInstance()->seo($pageKey);
}

function i18n_render_seo($pageKey) {
    I18n::getInstance()->renderSeoMeta($pageKey);
}

function i18n_lang() {
    return I18n::getInstance()->getCurrentLanguage();
}

function i18n_set_lang($code) {
    return I18n::getInstance()->setLanguage($code);
}

function seo_language_label($lang = null) {
    $lang = $lang ?: i18n_lang();
    $labels = [
        'zh-TW' => '繁體中文', 'en-US' => 'English', 'ja-JP' => '日本語',
        'ko-KR' => '한국어', 'de-DE' => 'Deutsch', 'fr-FR' => 'Français',
        'es-ES' => 'Español', 'ru-RU' => 'Русский',
    ];
    return $labels[$lang] ?? '';
}

function seo_normalize_title($title, $fallback = '', $lang = null) {
    $title = trim((string)$title);
    $fallback = trim((string)$fallback);
    $placeholders = ['', '項目 タイトル', 'ページタイトル', 'Page Title', '頁面標題', '페이지 제목', '페이지SEO제목', '중국어 원문 보완 필요'];
    if (in_array($title, $placeholders, true) && $fallback !== '') {
        $title = $fallback;
    }
    $lang = $lang ?: i18n_lang();
    $label = seo_language_label($lang);
    if (isset($_GET['lang']) && $label !== '' && strpos($title, $label) === false) {
        $title .= ' | ' . $label;
    }
    return $title;
}

function i18n_hreflang_langs() {
    return array_map(static fn($l) => $l['code'], I18n::getInstance()->getEnabledLanguages());
}

function i18n_localized_url($baseUrl, $lang = null) {
    $lang = $lang ?: i18n_lang();
    if ($lang === '') {
        return $baseUrl;
    }
    $fragment = '';
    $fragmentPos = strpos($baseUrl, '#');
    if ($fragmentPos !== false) {
        $fragment = substr($baseUrl, $fragmentPos);
        $baseUrl = substr($baseUrl, 0, $fragmentPos);
    }
    $sep = (strpos($baseUrl, '?') === false) ? '?' : '&';
    return $baseUrl . $sep . 'lang=' . rawurlencode($lang) . $fragment;
}

function i18n_canonical_url($baseUrl, $lang = null) {
    $lang = $lang ?: i18n_lang();
    if ($lang === '' || $lang === 'zh-CN') {
        return $baseUrl;
    }
    return i18n_localized_url($baseUrl, $lang);
}

function i18n_render_hreflang($baseUrl) {
    $esc = static function ($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    };
    foreach (i18n_hreflang_langs() as $hl) {
        $hreflangUrl = $hl === 'zh-CN' ? $baseUrl : i18n_localized_url($baseUrl, $hl);
        echo '    <link rel="alternate" hreflang="' . $esc($hl) . '" href="' . $esc($hreflangUrl) . '">' . "\n";
    }
    echo '    <link rel="alternate" hreflang="x-default" href="' . $esc($baseUrl) . '">' . "\n";
}
