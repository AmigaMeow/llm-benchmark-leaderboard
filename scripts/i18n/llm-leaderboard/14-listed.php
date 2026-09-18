<?php
/**
 * llm-leaderboard.php — 「上架」列的相对时间文案。
 *
 * 这一列的正文从生 ISO 日期（2026-09-01）改成了相对时间：按分数排序时，一堆 ISO 串读不出
 * 「新还是旧」，还占宽度。精确日期退到单元格的 title，正文只说人话。
 *
 * 分档（SSR 的 $listedText 与 JS 的 listedText() 逐档一致）：
 *   ≤0 天 → 今天；1 天 → 昨天；2–6 天 → {n} 天前；7–27 天 → {n} 周前；
 *   28–364 天 → {n} 个月前；≥365 天 → {n} 年前。
 * 天只出现在复数档（1 天已被「昨天」吃掉），所以 days 没有单数键；weeks/months/years
 * 会出现 1，英语需要单数形，因此各留一个单数键（其它语言与复数同文）。
 *
 * 占位符：{n} = 数量。用法：php scripts/seed-llm-leaderboard-i18n.php
 */

return [
    'llm.listed_today' => [
        'zh-CN' => '今天',
        'zh-TW' => '今天',
        'en-US' => 'Today',
        'ja-JP' => '今日',
        'ko-KR' => '오늘',
    ],
    'llm.listed_yesterday' => [
        'zh-CN' => '昨天',
        'zh-TW' => '昨天',
        'en-US' => 'Yesterday',
        'ja-JP' => '昨日',
        'ko-KR' => '어제',
    ],
    'llm.listed_days' => [
        'zh-CN' => '{n} 天前',
        'zh-TW' => '{n} 天前',
        'en-US' => '{n} days ago',
        'ja-JP' => '{n} 日前',
        'ko-KR' => '{n}일 전',
    ],
    'llm.listed_weeks' => [
        'zh-CN' => '{n} 周前',
        'zh-TW' => '{n} 週前',
        'en-US' => '{n} weeks ago',
        'ja-JP' => '{n} 週間前',
        'ko-KR' => '{n}주 전',
    ],
    'llm.listed_week' => [
        'zh-CN' => '{n} 周前',
        'zh-TW' => '{n} 週前',
        'en-US' => '{n} week ago',
        'ja-JP' => '{n} 週間前',
        'ko-KR' => '{n}주 전',
    ],
    'llm.listed_months' => [
        'zh-CN' => '{n} 个月前',
        'zh-TW' => '{n} 個月前',
        'en-US' => '{n} months ago',
        'ja-JP' => '{n} か月前',
        'ko-KR' => '{n}개월 전',
    ],
    'llm.listed_month' => [
        'zh-CN' => '{n} 个月前',
        'zh-TW' => '{n} 個月前',
        'en-US' => '{n} month ago',
        'ja-JP' => '{n} か月前',
        'ko-KR' => '{n}개월 전',
    ],
    'llm.listed_years' => [
        'zh-CN' => '{n} 年前',
        'zh-TW' => '{n} 年前',
        'en-US' => '{n} years ago',
        'ja-JP' => '{n} 年前',
        'ko-KR' => '{n}년 전',
    ],
    'llm.listed_year' => [
        'zh-CN' => '{n} 年前',
        'zh-TW' => '{n} 年前',
        'en-US' => '{n} year ago',
        'ja-JP' => '{n} 年前',
        'ko-KR' => '{n}년 전',
    ],
];
