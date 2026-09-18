<?php
/**
 * 移动端底部导航（开源版）。
 * 链接项与顶部 $navLinks 一致；未设置时只有排行榜首页。
 */
require_once __DIR__ . '/i18n.php';
$mobileNavUrl = static function (string $path): string {
    return htmlspecialchars(i18n_localized_url($path), ENT_QUOTES, 'UTF-8');
};
$mobileNavLinks = $navLinks ?? [
    ['href' => '/', 'label' => __('nav.home') !== 'nav.home' ? __('nav.home') : '排行榜', 'page' => 'home'],
];
?>
<nav id="mobileNav">
    <?php foreach ($mobileNavLinks as $link): ?>
    <a href="<?php echo $mobileNavUrl($link['href']); ?>" class="<?php echo (isset($current_page) && $current_page === ($link['page'] ?? '')) ? 'active' : ''; ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5">
                <path d="M8 21h8m-4-4v4m-6-19h12a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/>
            </g>
        </svg>
        <span><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></span>
    </a>
    <?php endforeach; ?>
</nav>
