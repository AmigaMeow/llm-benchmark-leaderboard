<?php
/**
 * llm-leaderboard.php — Arena 评分置信区间（± 徽标）文案。
 *
 * 背景：同步脚本早就把 LMArena 的 rating_lower / rating_upper 取回了
 * （cache/llm/llm-leaderboard.json 的 arena_ci_low / arena_ci_high），
 * 但一直没在榜上露过面。Arena 分是成对比较的统计量，相邻模型常常差 1~2 分，
 * 这个差距往往落在区间内 —— 不显示区间，读者会把噪声当成名次。
 *
 * 展示口径：分数后面跟一个小号 `±{half}`，{half} = max(high-score, score-low)，
 * 悬浮说明给出完整区间。徽标只在三项（score / low / high）都齐全且 half > 0 时出现，
 * 所以缺 CI 的模型（本快照里 57 个模型有 42 个带 CI）不会显示 ±，而不是显示 ±0。
 *
 * 文案很小，占位符 {low}/{high}/{half} 都是已格式化的数字字符串（跟随页面语言的小数点）。
 * 用法：php scripts/seed-llm-leaderboard-i18n.php  （薄壳，见脚本头注释）
 */

return [
    'llm.ci_tip' => [
        'zh-CN' => 'LMArena 评分的 95% 置信区间 {low}–{high}（±{half}）；区间越窄，名次越可信，相邻名次常落在彼此的区间内。',
        'zh-TW' => 'LMArena 評分的 95% 信賴區間 {low}–{high}（±{half}）；區間越窄，名次越可信，相鄰名次常落在彼此的區間內。',
        'en-US' => '95% confidence interval of the LMArena rating: {low}–{high} (±{half}). The narrower the interval, the more trustworthy the rank; neighbouring ranks often overlap.',
        'ja-JP' => 'LMArena スコアの 95% 信頼区間は {low}–{high}（±{half}）。区間が狭いほど順位は信頼でき、隣接する順位はしばしば重なります。',
        'ko-KR' => 'LMArena 점수의 95% 신뢰구간은 {low}–{high}(±{half})입니다. 구간이 좁을수록 순위를 신뢰할 수 있으며, 인접 순위는 서로 겹치는 경우가 많습니다.',
    ],
];
