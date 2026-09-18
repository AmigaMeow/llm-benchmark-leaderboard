<?php
/**
 * 语言切换组件
 * 可以包含在任何页面的header中
 */

// 确保I18n已加载
if (!class_exists('I18n')) {
    require_once __DIR__ . '/i18n.php';
}

$i18n = I18n::getInstance();
$currentLang = $i18n->getCurrentLanguage();
$languages = $i18n->getEnabledLanguages();
$pageLanguageFilter = isset($pageSupportedLanguages) && is_array($pageSupportedLanguages)
    ? $pageSupportedLanguages
    : null;
if ($pageLanguageFilter !== null) {
    $languages = array_values(array_filter(
        $languages,
        static fn($language) => in_array($language['code'] ?? '', $pageLanguageFilter, true)
    ));
}

// Keep the visitor on the current page while changing language.  Tool pages
// use a query parameter for language, so replace only `lang` and preserve any
// other query parameters the page may use.
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestParts = parse_url($requestUri);
$languagePath = $requestParts['path'] ?? '/';
$languageQuery = [];
if (!empty($requestParts['query'])) {
    parse_str($requestParts['query'], $languageQuery);
}
unset($languageQuery['lang']);

// 支持页面级语言白名单的页面使用无参数 URL 作为简体中文规范地址；
// 其他旧页面继续显式传 lang=zh-CN，避免语言 Cookie 干扰切换。
$languageHref = static function ($code) use ($languagePath, $languageQuery, $pageLanguageFilter) {
    $query = $languageQuery;
    if ($code === 'zh-CN' && $pageLanguageFilter !== null) {
        unset($query['lang']);
    } else {
        $query['lang'] = $code;
    }
    $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    return $languagePath . ($queryString !== '' ? '?' . $queryString : '');
};

// 获取当前语言的本地化名称
$currentLangName = '';
foreach ($languages as $lang) {
    if ($lang['code'] === $currentLang) {
        $currentLangName = $lang['native_name'];
        break;
    }
}
?>

<!-- 语言切换器 -->
<div class="i18n-lang-switcher">
    <button type="button" id="languageSwitcherBtn" aria-haspopup="true" aria-expanded="false">
        <svg class="i18n-icon" width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
        </svg>
        <span class="i18n-current-lang"><?php echo htmlspecialchars($currentLangName); ?></span>
        <svg class="i18n-arrow" width="7" height="7" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 7px; height: 7px; flex-shrink: 0; opacity: 0.6;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <!-- 下拉菜单 - 使用内联样式防止FOUC闪烁 -->
    <div id="languageMenu" class="i18n-dropdown hidden" style="display: none !important;">
        <div class="i18n-dropdown-inner">
            <?php foreach ($languages as $lang): ?>
                <a href="<?php echo htmlspecialchars($languageHref($lang['code']), ENT_QUOTES, 'UTF-8'); ?>"
                   data-no-swup
                   data-language="<?php echo htmlspecialchars($lang['code'], ENT_QUOTES, 'UTF-8'); ?>"
                   class="i18n-lang-item <?php echo $lang['code'] === $currentLang ? 'active' : ''; ?>">
                    <div class="i18n-lang-info">
                        <span class="i18n-flag"><?php echo getFlagEmoji($lang['flag_icon']); ?></span>
                        <div class="i18n-lang-text">
                            <div class="i18n-lang-native"><?php echo htmlspecialchars($lang['native_name']); ?></div>
                            <div class="i18n-lang-en"><?php echo htmlspecialchars($lang['name']); ?></div>
                        </div>
                    </div>
                    <?php if ($lang['code'] === $currentLang): ?>
                        <svg class="i18n-check" width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
(function() {
    const btn = document.getElementById('languageSwitcherBtn');
    const menu = document.getElementById('languageMenu');

    if (!btn || !menu) return;

    // 切换下拉菜单
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const isHidden = menu.classList.contains('hidden');

        if (isHidden) {
            // 显示菜单：移除hidden类和内联样式
            menu.classList.remove('hidden');
            menu.style.display = '';
            btn.setAttribute('aria-expanded', 'true');
        } else {
            // 隐藏菜单：添加hidden类和内联样式
            menu.classList.add('hidden');
            menu.style.display = 'none';
            btn.setAttribute('aria-expanded', 'false');
        }
    });

    // 点击外部关闭
    document.addEventListener('click', function(e) {
        if (!menu.classList.contains('hidden') && !menu.contains(e.target)) {
            menu.classList.add('hidden');
            menu.style.display = 'none';
            btn.setAttribute('aria-expanded', 'false');
        }
    });

    // ESC键关闭
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !menu.classList.contains('hidden')) {
            menu.classList.add('hidden');
            menu.style.display = 'none';
            btn.setAttribute('aria-expanded', 'false');
        }
    });

    // Persist language even on pages that do not write the cookie (I18N_PUBLIC_READONLY).
    menu.addEventListener('click', function(e) {
        var item = e.target && e.target.closest ? e.target.closest('.i18n-lang-item') : null;
        if (!item) return;
        var href = item.getAttribute('href') || '';
        var match = href.match(/[?&]lang=([^&]+)/);
        var selectedLanguage = item.getAttribute('data-language');
        if (!selectedLanguage && match) {
            selectedLanguage = decodeURIComponent(match[1]);
        }
        if (!selectedLanguage) return;
        try {
            document.cookie = 'language=' + encodeURIComponent(selectedLanguage)
                + '; path=/; max-age=31536000; secure; samesite=lax';
            document.cookie = 'language_manual=1; path=/; max-age=31536000; secure; samesite=lax';
        } catch (err) {}
    });
})();
</script>

<style>
/* 语言切换器 - 使用命名空间避免样式冲突 */
/* 关键CSS - 防止FOUC闪烁，必须放在最前面 */
.i18n-dropdown {
    display: none !important; /* 默认隐藏，防止闪烁 */
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    width: 220px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    z-index: 9999;
    animation: i18nSlideDown 0.2s ease-out;
}

.i18n-dropdown:not(.hidden) {
    display: block !important; /* 移除hidden类时显示 */
}

.menu-item-lang {
    padding: 0 !important;
}

.i18n-lang-switcher {
    position: relative;
    display: block;
    user-select: none;
    width: 100%;
    height: 100%;
}

.i18n-lang-switcher button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 12px;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
    color: #6b7280;
    white-space: nowrap;
    width: 100%;
    height: 100%;
    border-radius: 0;
}

.i18n-lang-switcher button:hover {
    color: #9333ea;
    background-color: rgba(147, 51, 234, 0.05);
}

.i18n-lang-switcher button:focus {
    outline: none;
}

.i18n-icon {
    width: 10px !important;
    height: 10px !important;
    min-width: 10px;
    min-height: 10px;
    max-width: 10px;
    max-height: 10px;
    flex-shrink: 0;
    background: transparent;
    box-shadow: none;
}

.i18n-current-lang {
    font-weight: 500;
    font-size: 14px;
}

.i18n-arrow {
    width: 7px !important;
    height: 7px !important;
    min-width: 7px;
    min-height: 7px;
    max-width: 7px;
    max-height: 7px;
    flex-shrink: 0;
    opacity: 0.6;
    background: transparent;
    box-shadow: none;
}

.i18n-dropdown-inner {
    padding: 4px;
}

.i18n-lang-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border-radius: 6px;
    text-decoration: none;
    transition: background-color 0.15s;
    cursor: pointer;
}

.i18n-lang-item:hover {
    background-color: #f3f4f6;
}

.i18n-lang-item.active {
    background-color: #f3e8ff;
}

.i18n-lang-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.i18n-flag {
    font-size: 20px;
    line-height: 1;
    flex-shrink: 0;
}

.i18n-lang-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.i18n-lang-native {
    font-size: 13px;
    font-weight: 500;
    color: #111827;
    line-height: 1.2;
}

.i18n-lang-en {
    font-size: 11px;
    color: #6b7280;
    line-height: 1.2;
}

.i18n-check {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    color: #9333ea;
}

@keyframes i18nSlideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* 移动端适配 */
@media (max-width: 768px) {
    .i18n-lang-switcher button {
        padding: 4px 8px;
        gap: 4px;
    }

    .i18n-current-lang {
        font-size: 13px;
    }

    .i18n-dropdown {
        width: 200px;
    }
}
</style>

<?php
/**
 * 将国家代码转换为flag emoji
 */
function getFlagEmoji($countryCode) {
    $flags = [
        'CN' => '🇨🇳',
        'US' => '🇺🇸',
        'JP' => '🇯🇵',
        'KR' => '🇰🇷',
        'DE' => '🇩🇪',
        'FR' => '🇫🇷',
        'ES' => '🇪🇸',
        'RU' => '🇷🇺',
        'IT' => '🇮🇹',
    ];

    return $flags[$countryCode] ?? '🌐';
}
?>
