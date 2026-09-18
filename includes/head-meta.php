<?php
// 引入版本控制
if (!defined('ASSET_VERSION')) {
    require_once(__DIR__ . '/version.php');
}
require_once(__DIR__ . '/og-image.php');
?>
<?php
// Pages that set $ogImage before including this file keep their own card.
if (!isset($ogImage)) {
    $ogFallback = og_image_url('default');
    ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($ogFallback, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?php echo htmlspecialchars(og_image_alt(), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:locale" content="<?php echo htmlspecialchars(str_replace('-', '_', i18n_lang()), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($ogFallback, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
}
?>
    <!-- Critical CSS -->
    <style>
        html, body {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow-x: hidden !important;
        }

        .top-navbar {
            width: 100% !important;
            max-width: 100% !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            margin: 0 !important;
            box-sizing: border-box !important;
            z-index: 1000 !important;
            background: #ffffff !important;
        }

        .navbar-container {
            max-width: 100% !important;
            width: 100% !important;
            margin: 0 !important;
            box-sizing: border-box !important;
        }

        @media (min-width: 769px) {
            body {
                padding-top: 44px !important;
            }
        }

        #mainLayout,
        .page-container,
        .container.mx-auto {
            margin-top: 24px !important;
        }

        #swup {
            margin-top: 0 !important;
        }

        @media (max-width: 768px) {
            body {
                padding-top: 60px !important;
                padding-bottom: calc(60px + env(safe-area-inset-bottom, 0)) !important;
            }

            #mainLayout,
            .page-container,
            .container.mx-auto {
                margin-top: 0 !important;
            }
        }

        #mobileNav {
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 999 !important;
            background: #fff !important;
            border-top: 1px solid #e8e8e8 !important;
            box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.08) !important;
            height: 60px !important;
            display: none !important;
        }

        @media (max-width: 768px) {
            #mobileNav {
                display: flex !important;
            }
        }

        @media (min-width: 769px) {
            #mobileNav {
                display: none !important;
            }
        }

        #mobileNav a {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #666;
            gap: 4px;
        }

        #mobileNav a.active {
            color: #2c3e50;
            background: rgba(44, 62, 80, 0.06);
        }

        #mobileNav svg {
            width: 18px !important;
            height: 18px !important;
            min-width: 18px !important;
            min-height: 18px !important;
            max-width: 18px !important;
            max-height: 18px !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        #mobileNav span {
            font-size: 10px;
            font-weight: 500;
        }

        .top-navbar svg,
        .top-navbar i {
            background: transparent !important;
            box-shadow: none !important;
        }

        .header-icon {
            width: 56px !important;
            height: 56px !important;
            color: #2c3e50 !important;
            margin-bottom: 16px !important;
        }

        img {
            max-width: 100%;
            height: auto;
        }

        .logo-img {
            width: 16px;
            height: 16px;
            display: inline-block;
        }

        @media (min-width: 769px) {
            .top-navbar .navbar-container {
                height: 44px !important;
            }

            .top-navbar .logo-img {
                width: 16px !important;
                height: 16px !important;
            }

            .top-navbar .logo-text {
                margin-left: 7px !important;
                font-size: 16px !important;
            }

            .top-navbar .navbar-menu {
                gap: 4px !important;
                margin-left: 24px !important;
                margin-right: 24px !important;
            }

            .top-navbar .menu-link {
                gap: 4px !important;
                padding: 6px 9px !important;
                font-size: 13px !important;
            }

            .top-navbar .menu-text {
                font-size: 13px !important;
            }

            .navbar-menu {
                display: flex !important;
            }
        }

        @media (max-width: 768px) {
            .top-navbar {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                z-index: 1000 !important;
            }

            .navbar-container {
                justify-content: center !important;
                align-items: center !important;
                height: 60px !important;
                padding: 0 15px !important;
                margin: 0 !important;
            }

            .navbar-logo {
                margin: 0 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }

            .logo-link {
                display: flex !important;
                align-items: center !important;
                gap: 10px !important;
                padding: 8px !important;
            }

            .logo-img {
                width: 20px !important;
                height: 20px !important;
            }

            .logo-text {
                font-size: 20px !important;
                font-weight: 700 !important;
            }

            .navbar-menu {
                display: none !important;
            }
        }

        .coremark-guide {
            min-height: 80px;
            contain: layout;
        }

        #tableContentWrapper {
            min-height: 400px;
            contain: layout;
        }

        .notice {
            min-height: 40px;
            contain: layout;
        }

        .score-text {
            display: inline-block;
            min-width: 60px;
        }
    </style>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    <!-- 预加载合并后的CSS（首屏必需） -->
    <link rel="preload" href="<?php echo asset_url_auto('/assets/css/combined.css'); ?>" as="style">
    <link href="<?php echo asset_url_auto('/assets/css/combined.css'); ?>" rel="stylesheet">

    <!-- Bootstrap -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous" referrerpolicy="no-referrer">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet" crossorigin="anonymous" integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous" referrerpolicy="no-referrer">
<?php if (isset($extra_css)) { foreach ($extra_css as $css) { ?>
    <link href="<?php echo asset_url_auto($css); ?>" rel="stylesheet">
<?php }} ?>

<?php
// Optional analytics: copy config/analytics.example.php to config/analytics.php.
// Empty values produce zero output.
$__analyticsFile = __DIR__ . '/../config/analytics.php';
$__analytics = is_file($__analyticsFile) ? include $__analyticsFile : [];
if (is_array($__analytics)) {
    if (!empty($__analytics['gsc_verification'])) {
        echo '    <meta name="google-site-verification" content="' . htmlspecialchars((string)$__analytics['gsc_verification'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
    if (!empty($__analytics['ga4_measurement_id']) && preg_match('/^G-[A-Z0-9]+$/', (string)$__analytics['ga4_measurement_id'])) {
        $__gaId = (string)$__analytics['ga4_measurement_id'];
        echo "    <!-- GA4 -->\n";
        echo '    <script async src="https://www.googletagmanager.com/gtag/js?id=' . htmlspecialchars($__gaId, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
        echo "    <script>\n";
        echo "      window.dataLayer = window.dataLayer || [];\n";
        echo "      function gtag(){dataLayer.push(arguments);}\n";
        echo "      gtag('js', new Date());\n";
        echo "      gtag('config', '" . addslashes($__gaId) . "');\n";
        echo "    </script>\n";
    }
}
?>
