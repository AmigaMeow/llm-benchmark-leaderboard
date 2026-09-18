<?php
/**
 * llm-leaderboard.php — 两处「按需展开」的文案。
 *
 * 一、榜级分辨率（llm.ci_resolution）
 *   上一轮把 ± 区间画进了 Arena 列，但读者仍要自己把「±4.6」和「相邻只差 1.8 分」两个
 *   数字对起来才明白名次分不分得开。所以由榜算一次：本屏（当前筛选后）带 CI 的模型中位
 *   半宽、按 Arena 排序时相邻模型的中位分差、以及相邻对里区间重叠的比例，写成一句话，
 *   接在 Arena 口径那句脚注后面。
 *   刻意不做逐行标记：真实快照上按 Arena 排序时 40/41 组相邻对全部重叠（41 行里 41 行要挂
 *   标记、还连成一条 41 行的巨带），逐行标等于给全表挂噪音。一屏说一次才有信息量。
 *   占位符 {half} {gap} 是跟随语言格式化的数字串，{overlap} {pairs} 是整数。
 *
 * 二、行内明细（llm.detail_*）
 *   精确上架日期、Arena 95% 区间、票数、名次变动、档位说明这些原来只挂在 title 上，
 *   触屏永远看不到。窄屏/触屏给一个可按需展开的明细行，文案复用已有的 tip 句子
 *   （low_votes_tip / rank_up_tip / rank_down_tip / effort_badge_tip），这里只加标签。
 *
 * 用法：php scripts/seed-llm-leaderboard-i18n.php
 */

return [
    'llm.ci_resolution' => [
        'zh-CN' => '本屏 Arena 分中位浮动 ±{half} 分，相邻模型中位只差 {gap} 分（{pairs} 组相邻里 {overlap} 组落在彼此区间内）：差距小于 {half} 分时，名次先后说明不了什么。',
        'zh-TW' => '本屏 Arena 分中位浮動 ±{half} 分，相鄰模型中位只差 {gap} 分（{pairs} 組相鄰裡 {overlap} 組落在彼此區間內）：差距小於 {half} 分時，名次先後說明不了什麼。',
        'en-US' => 'In this view the median Arena margin is ±{half} while neighbouring models sit a median {gap} apart ({overlap} of {pairs} adjacent pairs overlap): gaps under {half} say nothing about which model ranks higher.',
        'ja-JP' => 'この表示では Arena スコアの中央値の幅が ±{half}、隣接モデルの中位差は {gap} です（{pairs} 組中 {overlap} 組が区間の重なり）。{half} 未満の差では順位の前後は語れません。',
        'ko-KR' => '이 화면의 Arena 점수 중앙값 폭은 ±{half}, 인접 모델의 중앙 격차는 {gap}입니다({pairs}쌍 중 {overlap}쌍이 구간 중첩). {half} 미만 차이는 순위를 말해주지 않습니다.',
    ],
    'llm.detail_more' => [
        'zh-CN' => '展开该行明细',
        'zh-TW' => '展開該行明細',
        'en-US' => 'Show row details',
        'ja-JP' => 'この行の詳細を表示',
        'ko-KR' => '이 행의 세부정보 표시',
    ],
    'llm.detail_less' => [
        'zh-CN' => '收起该行明细',
        'zh-TW' => '收合該行明細',
        'en-US' => 'Hide row details',
        'ja-JP' => 'この行の詳細を隠す',
        'ko-KR' => '이 행의 세부정보 숨기기',
    ],
    'llm.detail_ci' => [
        'zh-CN' => '95% 区间',
        'zh-TW' => '95% 區間',
        'en-US' => '95% range',
        'ja-JP' => '95% 区間',
        'ko-KR' => '95% 구간',
    ],
    'llm.detail_votes' => [
        'zh-CN' => 'Arena 票数',
        'zh-TW' => 'Arena 票數',
        'en-US' => 'Arena votes',
        'ja-JP' => 'Arena 票数',
        'ko-KR' => 'Arena 투표 수',
    ],
    'llm.detail_move' => [
        'zh-CN' => '较上期名次',
        'zh-TW' => '較上期名次',
        'en-US' => 'Rank change',
        'ja-JP' => '前回との順位差',
        'ko-KR' => '이전 대비 순위',
    ],
];
