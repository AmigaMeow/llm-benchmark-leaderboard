<?php
// Copy to config/site.php and adjust. Every key is optional — the getters in
// includes/settings.php fall back to built-in defaults.
return [
    'site_name' => getenv('SITE_NAME') ?: 'LLM Leaderboard',
    'site_url' => getenv('SITE_URL') ?: 'https://example.com',
    'site_description' => 'LLM leaderboard: open-source weights, coding, price-performance, latest releases and output speed.',
    'seo_keywords' => 'LLM leaderboard,AI models,benchmark,open weights',
    'homepage_title' => 'LLM Benchmark Leaderboard',
    'homepage_og_title' => 'LLM Benchmark Leaderboard',
    'homepage_og_description' => 'LLM leaderboard: open-source weights, coding, price-performance, latest releases and output speed.',
];
