<?php
/**
 * 面包屑导航组件
 *
 * 使用方法:
 * $breadcrumbs = [
 *     ['name' => '首页', 'url' => '/'],
 *     ['name' => '工具箱', 'url' => '/tools/'],
 *     ['name' => 'IPv6测试'] // 最后一项不需要url
 * ];
 * include(__DIR__ . '/breadcrumb.php');
 */

if (!isset($breadcrumbs) || empty($breadcrumbs)) {
    return;
}
?>
<nav aria-label="面包屑导航" class="breadcrumb-nav">
    <ol class="breadcrumb-list" itemscope itemtype="https://schema.org/BreadcrumbList">
        <?php foreach ($breadcrumbs as $index => $item): ?>
            <li class="breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <?php if (isset($item['url']) && !empty($item['url'])): ?>
                    <a href="<?php echo htmlspecialchars($item['url']); ?>" itemprop="item">
                        <span itemprop="name"><?php echo htmlspecialchars($item['name']); ?></span>
                    </a>
                <?php else: ?>
                    <span itemprop="name" class="current"><?php echo htmlspecialchars($item['name']); ?></span>
                <?php endif; ?>
                <meta itemprop="position" content="<?php echo $index + 1; ?>" />
                <?php if ($index < count($breadcrumbs) - 1): ?>
                    <svg class="breadcrumb-separator" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="currentColor" d="M8.59 16.59L13.17 12L8.59 7.41L10 6l6 6l-6 6z"/>
                    </svg>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>

<style>
/* 面包屑导航样式 */
.breadcrumb-nav {
    margin: 20px 0;
    padding: 12px 0;
}

.breadcrumb-list {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    list-style: none;
    margin: 0;
    padding: 0;
    font-size: 14px;
    gap: 4px;
}

.breadcrumb-item {
    display: flex;
    align-items: center;
    color: #666;
}

.breadcrumb-item a {
    color: #2196f3;
    text-decoration: none;
    transition: color 0.2s ease;
    padding: 4px 8px;
    border-radius: 4px;
}

.breadcrumb-item a:hover {
    color: #1976d2;
    background: #f5f5f5;
}

.breadcrumb-item .current {
    color: #333;
    font-weight: 500;
    padding: 4px 8px;
}

.breadcrumb-separator {
    color: #999;
    margin: 0 2px;
    flex-shrink: 0;
}

/* 移动端优化 */
@media (max-width: 768px) {
    .breadcrumb-nav {
        margin: 12px 0;
        padding: 8px 0;
    }

    .breadcrumb-list {
        font-size: 13px;
    }

    .breadcrumb-item a,
    .breadcrumb-item .current {
        padding: 4px 6px;
    }
}

/* 超小屏幕 */
@media (max-width: 480px) {
    .breadcrumb-list {
        font-size: 12px;
    }

    .breadcrumb-separator {
        width: 14px;
        height: 14px;
    }
}
</style>
