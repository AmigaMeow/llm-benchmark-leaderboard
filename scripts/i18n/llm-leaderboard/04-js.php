<?php
/**
 * llm-leaderboard.php — JS 运行时文案（llm.js.* 前缀，PHP getByPrefix 注入 window.LLM_I18N）。
 *
 * 页面把这一组 echo 成 window.LLM_I18N，JS 读取时以中文为内联回落。
 */

return [

    'llm.js.badge_closed' => [
        'zh-CN' => '闭源',
        'zh-TW' => '閉源',
        'en-US' => 'Closed',
        'ja-JP' => 'クローズド',
        'ko-KR' => '클로즈드',
    ],
    'llm.js.arena_tip' => [
        'zh-CN' => 'Arena 综合对战评分；分数越高代表综合偏好越高，不是独立编程能力分。',
        'zh-TW' => 'Arena 綜合對戰評分；分數越高代表綜合偏好越高，不是獨立程式能力分。',
        'en-US' => 'Arena overall head-to-head rating; a higher score indicates stronger overall preference, not an independent coding score.',
        'ja-JP' => 'Arena 総合対戦スコア。スコアが高いほど総合的な好みが高く、独立したコーディング能力のスコアではありません。',
        'ko-KR' => 'Arena 종합 대결 점수입니다. 점수가 높을수록 종합 선호도가 높으며 독립적인 코딩 점수가 아닙니다.',
    ],
];
