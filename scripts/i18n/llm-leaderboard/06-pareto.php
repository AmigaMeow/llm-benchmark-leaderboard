<?php
/* P6：Pareto（帕累托）视图文案 */
return [
    'llm.seo_title_pareto' => [
        'zh-CN' => '大模型性价比图：哪个模型最值 | {site}',
        'zh-TW' => '大模型性價比圖：哪個模型最值 | {site}',
        'en-US' => 'LLM Best Value Chart: Arena Score × API Price | {site}',
        'ja-JP' => 'LLM パレート図：Arena スコア × API 価格 | {site}',
        'ko-KR' => 'LLM 파레토 차트: Arena 점수 × API 가격 | {site}',
    ],
    'llm.h1_pareto' => [
        'zh-CN' => '大模型性价比图',
        'zh-TW' => '大模型性價比圖',
        'en-US' => 'LLM Best Value Chart',
        'ja-JP' => 'LLM パレート図',
        'ko-KR' => 'LLM 파레토 차트',
    ],
    'llm.seo_desc_pareto' => [
        'zh-CN' => '大模型帕累托前沿图：横轴为 OpenRouter 输出价（对数刻度），纵轴为 LMArena Arena 评分。绿线上的模型在同等价格下没有更强的替代，是一分钱一分货的最优解。每日更新（{year}）。',
        'zh-TW' => '大模型帕累托前沿圖：橫軸為 OpenRouter 輸出價（對數刻度），縱軸為 LMArena Arena 評分。綠線上的模型在同等價格下沒有更強的替代，是一分錢一分貨的最優解。每日更新（{year}）。',
        'en-US' => 'LLM Pareto frontier chart: OpenRouter output price (log scale) vs LMArena Arena score. Models on the green line have no cheaper-and-stronger replacement. Updated daily ({year}).',
        'ja-JP' => 'LLM パレートフロンティア図：横軸は OpenRouter 出力価格（対数スケール）、縦軸は LMArena Arena スコア。緑の線上のモデルは「より安くてより強い」代替が存在しません。毎日更新（{year}）。',
        'ko-KR' => 'LLM 파레토 프론티어 차트: 가로축은 OpenRouter 출력 가격(로그 스케일), 세로축은 LMArena Arena 점수. 녹색 선 위의 모델은 더 싸고 더 강한 대체재가 없습니다. 매일 업데이트 ({year}).',
    ],
    /* 速度视图（?view=speed）：与价格视图共用同一张图的骨架，但横轴是另一件事。
       SEO 三件套必须是独立文案 —— 两个视图各有一条自指 canonical，描述串混用
       会让 SERP 摘要说的横轴与页面实际横轴对不上。 */
    'llm.seo_title_speed' => [
        'zh-CN' => '大模型速度榜：评分与输出速度对照 | {site}',
        'zh-TW' => '大模型速度榜：評分與輸出速度對照 | {site}',
        'en-US' => 'LLM Speed Chart: Score vs Output Speed | {site}',
        'ja-JP' => 'LLM 速度チャート：スコア × 出力速度 | {site}',
        'ko-KR' => 'LLM 속도 차트: 점수 × 출력 속도 | {site}',
    ],
    'llm.h1_speed' => [
        'zh-CN' => '大模型速度图',
        'zh-TW' => '大模型速度圖',
        'en-US' => 'LLM Speed Chart',
        'ja-JP' => 'LLM 速度チャート',
        'ko-KR' => 'LLM 속도 차트',
    ],
    'llm.seo_desc_speed' => [
        'zh-CN' => '大模型速度图：横轴为实测输出速度（tokens/秒，线性刻度），纵轴为智能指数。越靠右上代表又快又强；气泡越大代表输出价越低。每日更新（{year}）。',
        'zh-TW' => '大模型速度圖：橫軸為實測輸出速度（tokens/秒，線性刻度），縱軸為智慧指數。越靠右上代表又快又強；氣泡越大代表輸出價越低。每日更新（{year}）。',
        'en-US' => 'LLM speed chart: measured output speed (tokens/s, linear scale) vs the intelligence index. Up and to the right means fast and strong; bigger bubbles mean cheaper output. Updated daily ({year}).',
        'ja-JP' => 'LLM 速度チャート：横軸は実測の出力速度（tokens/秒、線形スケール）、縦軸は知能指数。右上にあるほど速くて強く、バブルが大きいほど出力価格が安いことを示します。毎日更新（{year}）。',
        'ko-KR' => 'LLM 속도 차트: 가로축은 실측 출력 속도(tokens/초, 선형 스케일), 세로축은 지능 지수. 오른쪽 위일수록 빠르고 강하며, 버블이 클수록 출력 가격이 저렴합니다. 매일 업데이트 ({year}).',
    ],
];
