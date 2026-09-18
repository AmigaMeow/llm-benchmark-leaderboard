<?php
/**
 * llm-leaderboard.php — 推理强度档位（AA 分档行）文案。
 *
 * 榜表把「模型 × 推理强度档位」拆成独立行（与 Artificial Analysis 榜单同构），
 * 行内徽标文案走这里；SSR 与 JS 共用 llm-leaderboard.php 生成的 window.LLM_EFFORT。
 *
 * 徽标统一用英文档位名（Max / XHigh / High / Medium / Low …），五门语言同一份：
 * 这些是 Artificial Analysis 的档位专名，翻成「最高强度 / 最大エフォート」之类
 * 反而和 AA 页面上的叫法对不上；悬浮说明与下方脚注仍按语言本地化，
 * 只在句子中间嵌那个英文标签。改徽标文案要同步 includes/llm-board-data.php
 * 的 llm_board_effort_label_en()（tests/llm-board-data.php 会校验两者逐字一致）。
 */

return [
    'llm.effort_max' => ['zh-CN' => 'Max', 'zh-TW' => 'Max', 'en-US' => 'Max', 'ja-JP' => 'Max', 'ko-KR' => 'Max'],
    'llm.effort_xhigh' => ['zh-CN' => 'XHigh', 'zh-TW' => 'XHigh', 'en-US' => 'XHigh', 'ja-JP' => 'XHigh', 'ko-KR' => 'XHigh'],
    'llm.effort_high' => ['zh-CN' => 'High', 'zh-TW' => 'High', 'en-US' => 'High', 'ja-JP' => 'High', 'ko-KR' => 'High'],
    'llm.effort_medium' => ['zh-CN' => 'Medium', 'zh-TW' => 'Medium', 'en-US' => 'Medium', 'ja-JP' => 'Medium', 'ko-KR' => 'Medium'],
    'llm.effort_low' => ['zh-CN' => 'Low', 'zh-TW' => 'Low', 'en-US' => 'Low', 'ja-JP' => 'Low', 'ko-KR' => 'Low'],
    'llm.effort_non_reasoning' => ['zh-CN' => 'Non-reasoning', 'zh-TW' => 'Non-reasoning', 'en-US' => 'Non-reasoning', 'ja-JP' => 'Non-reasoning', 'ko-KR' => 'Non-reasoning'],
    'llm.effort_reasoning' => ['zh-CN' => 'Reasoning', 'zh-TW' => 'Reasoning', 'en-US' => 'Reasoning', 'ja-JP' => 'Reasoning', 'ko-KR' => 'Reasoning'],
    'llm.effort_adaptive' => ['zh-CN' => 'Adaptive', 'zh-TW' => 'Adaptive', 'en-US' => 'Adaptive', 'ja-JP' => 'Adaptive', 'ko-KR' => 'Adaptive'],
    'llm.effort_thinking' => ['zh-CN' => 'Thinking', 'zh-TW' => 'Thinking', 'en-US' => 'Thinking', 'ja-JP' => 'Thinking', 'ko-KR' => 'Thinking'],
    'llm.effort_badge_tip' => [
        'zh-CN' => '该行取 {label} 档位的实测值，数据来自 Artificial Analysis。',
        'zh-TW' => '該列取 {label} 檔位的實測值，資料來自 Artificial Analysis。',
        'en-US' => 'Measured at the {label} setting by Artificial Analysis.',
        'ja-JP' => 'Artificial Analysis の {label} 設定での実測値です。',
        'ko-KR' => 'Artificial Analysis의 {label} 설정 실측값입니다.',
    ],
    'llm.effort_note' => [
        'zh-CN' => '当前按档位展开：同一模型的不同推理强度档位各占一行，徽标沿用 Artificial Analysis 的英文档位名（Max / XHigh / High / Medium / Low …），名次格左侧同色细条把同一模型的行连起来；分数取对应档位的实测值，Arena 分与价格是模型级数据，同模型各行相同。',
        'zh-TW' => '當前按檔位展開：同一模型的不同推理強度檔位各佔一行，徽標沿用 Artificial Analysis 的英文檔位名（Max / XHigh / High / Medium / Low …），名次格左側同色細條把同一模型的行連起來；分數取對應檔位的實測值，Arena 分與價格是模型級資料，同模型各行相同。',
        'en-US' => 'Expanded by tier: each reasoning-effort setting of a model gets its own row, labelled with Artificial Analysis’s own effort names (Max, XHigh, High, Medium, Low, …), and the colour bar on the left ties the rows of one model together. The score is that setting’s measurement; Arena scores and prices are model-level, so rows of the same model share them.',
        'ja-JP' => '段階ごとに展開中：同一モデルの推論エフォート設定ごとに 1 行とし、バッジは Artificial Analysis のエフォート名（Max / XHigh / High / Medium / Low …）をそのまま使います。左端の同色バーが同じモデルの行をつなぎます。スコアは該当設定の実測値、Arena スコアと価格はモデル単位のデータです。',
        'ko-KR' => '단계별로 펼친 상태입니다. 같은 모델의 추론 에포트 설정마다 한 행이며, 배지에는 Artificial Analysis의 에포트 이름(Max, XHigh, High, Medium, Low …)을 그대로 씁니다. 왼쪽 색 막대가 같은 모델의 행을 연결합니다. 점수는 해당 설정의 실측값이고 Arena 점수와 가격은 모델 단위 데이터입니다.',
    ],
];
