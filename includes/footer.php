<?php
/**
 * 页脚（开源版）：简介 + 链接 + 移动端底部导航。
 *
 * 页面可在 include 前设置 $footerLinks（同 $navLinks 结构）追加链接。
 */
$footerLang = i18n_lang();
$footerUrl = static function (string $path): string {
    return htmlspecialchars(i18n_localized_url($path), ENT_QUOTES, 'UTF-8');
};
$footerLinks = $footerLinks ?? [];
?>
<footer class="site-footer">
    <div class="site-footer-inner">
        <p class="site-footer-desc"><?php echo htmlspecialchars(SiteSettings::getSiteDescription()); ?></p>
        <?php if (!empty($footerLinks)): ?>
        <p class="site-footer-links">
            <?php foreach ($footerLinks as $i => $link): ?>
                <?php if ($i > 0): ?> · <?php endif; ?><a href="<?php echo $footerUrl($link['href']); ?>"><?php echo htmlspecialchars($link['label']); ?></a>
            <?php endforeach; ?>
        </p>
        <?php endif; ?>
    </div>
</footer>
<?php include __DIR__ . '/mobile-nav.php'; ?>
