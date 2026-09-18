<?php
/**
 * Site settings for the standalone leaderboard.
 *
 * Values come from config/site.php (copy config/site.example.php); anything
 * not configured falls back to the defaults below. Same getter names as the
 * production SiteSettings class so the pages barely change.
 */
class SiteSettings {

    private static $config = null;

    private static function config(): array {
        if (self::$config === null) {
            $file = __DIR__ . '/../config/site.php';
            self::$config = is_file($file) ? (require $file) : [];
        }
        return self::$config;
    }

    public static function get(string $key, $default = '') {
        $config = self::config();
        return $config[$key] ?? $default;
    }

    public static function getSiteName(): string {
        return (string)self::get('site_name', 'Benchmark Leaderboard');
    }

    public static function getSiteUrl(): string {
        $url = (string)self::get('site_url', '');
        if ($url !== '') {
            return rtrim($url, '/');
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    public static function getSiteDescription(): string {
        return (string)self::get('site_description', 'Community CPU benchmark leaderboard powered by CoreMark submissions.');
    }

    public static function getSeoKeywords(): string {
        return (string)self::get('seo_keywords', 'CoreMark,NAS,CPU benchmark,leaderboard');
    }

    public static function getHomepageTitle(): string {
        return (string)self::get('homepage_title', self::getSiteName());
    }

    public static function getHomepageOgTitle(): string {
        return (string)self::get('homepage_og_title', self::getHomepageTitle());
    }

    public static function getHomepageOgDescription(): string {
        return (string)self::get('homepage_og_description', self::getSiteDescription());
    }
}
