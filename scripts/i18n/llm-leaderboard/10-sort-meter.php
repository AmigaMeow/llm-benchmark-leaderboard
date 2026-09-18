<?php
/**
 * llm-leaderboard.php — 可排序表头与主指标对比条的说明文案。
 *
 * 表头点击排序与「归一化对比条」都是本轮新增的交互，用户第一次看到会问
 * 「点了会发生什么 / 条子代表什么」，所以两句话都放在表格脚注里；
 * 表头不额外加 ⓘ（移动端表头本就收起，加了解释不到）。
 *
 * 用法：php scripts/seed-llm-leaderboard-i18n.php  （薄壳，见脚本头注释）
 */

return [
    'llm.sort_note' => [
        'zh-CN' => '点击列头即可按该列排序：分数与速度从高到低，延迟与价格从低到高。',
        'zh-TW' => '點擊欄頭即可依該欄排序：分數與速度由高到低，延遲與價格由低到高。',
        'en-US' => 'Click a column header to sort by that column: scores and speed run high to low, latency and prices low to high.',
        'ja-JP' => '列見出しをクリックするとその列で並べ替えます。スコアと速度は高い順、遅延と価格は低い順です。',
        'ko-KR' => '열 제목을 클릭하면 해당 열 기준으로 정렬됩니다. 점수와 속도는 높은 순, 지연과 가격은 낮은 순입니다.',
    ],
    'llm.bar_note' => [
        'zh-CN' => '主指标列数值后的对比条按当前表内该列最高值归一，只表示表内相对位置，不代表绝对水平。',
        'zh-TW' => '主指標欄數值後的對比條按當前表內該欄最高值歸一，只表示表內相對位置，不代表絕對水準。',
        'en-US' => 'The bar after the primary metric is normalized against the highest value currently listed, so it shows relative position within this table, not an absolute level.',
        'ja-JP' => '主指標の数値の後ろのバーは、現在の表内で最も高い値で正規化したもので、表内の相対位置を示すだけで絶対的な水準ではありません。',
        'ko-KR' => '주요 지표 뒤의 막대는 현재 표에서 가장 높은 값으로 정규화한 것으로, 표 안에서의 상대 위치만 나타내며 절대 수준은 아닙니다.',
    ],
];
