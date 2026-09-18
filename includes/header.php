<?php
/**
 * 顶部导航（开源版）。
 *
 * 用法：页面在 include 前可设置
 *   $current_page  —— 当前页标识，用于高亮 menu-link
 *   $navLinks      —— [['href'=>'/x.php','icon'=>'trophy','label'=>'文本','page'=>'x'], ...]
 *                     未设置时默认只有排行榜首页。
 */
if (!class_exists('I18n')) {
    require_once __DIR__ . '/i18n.php';
}
require_once __DIR__ . '/settings.php';

$headerUrl = static function (string $path): string {
    return htmlspecialchars(i18n_localized_url($path), ENT_QUOTES, 'UTF-8');
};

$navLinks = $navLinks ?? [
    ['href' => '/', 'label' => __('nav.home') !== 'nav.home' ? __('nav.home') : '排行榜', 'page' => 'home'],
];
?>
<nav class="top-navbar">
    <div class="navbar-container">
        <div class="navbar-logo">
            <a class="logo-link" href="<?php echo $headerUrl('/'); ?>">
                <img class="logo-img" src="/images/logo.svg" alt="<?php echo htmlspecialchars(SiteSettings::getSiteName()); ?>">
                <span class="logo-text"><?php echo htmlspecialchars(SiteSettings::getSiteName()); ?></span>
            </a>
        </div>
        <div class="navbar-menu">
            <?php foreach ($navLinks as $link): ?>
                <a class="menu-link <?php echo (isset($current_page) && $current_page === ($link['page'] ?? '')) ? 'active' : ''; ?>"
                   href="<?php echo $headerUrl($link['href']); ?>">
                    <span class="menu-text"><?php echo htmlspecialchars($link['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="navbar-lang">
            <?php include __DIR__ . '/language-switcher.php'; ?>
        </div>
    </div>
</nav>
