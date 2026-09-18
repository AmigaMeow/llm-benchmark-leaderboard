<?php
/**
 * llm-leaderboard.php — 表格区文案（H1 下说明、信息条芯片、胶囊、搜索、表头、徽章、失败文案）。
 *
 * 模型名/厂商不翻译；{count} 由页面传各视图模型数；{model} 传行模型名。
 */

return [

    /* 口径必须与 llm-leaderboard.php 的 $defaultSort 一致：open 视图默认排序是 intel
       （$view === 'cheap' ? 'price' : ($view === 'new' ? 'new' : 'intel')），不是 Arena。
       原文案写「按 Arena 评分排名」，与页面实际排序矛盾。 */
    'llm.chip_updated_prefix' => [
        'zh-CN' => '更新于',
        'zh-TW' => '更新於',
        'en-US' => 'Updated',
        'ja-JP' => '更新',
        'ko-KR' => '갱신',
    ],
    'llm.chip_models_suffix' => [
        'zh-CN' => '个模型',
        'zh-TW' => '個模型',
        'en-US' => 'models',
        'ja-JP' => 'モデル',
        'ko-KR' => '모델',
    ],
    'llm.chip_daily' => [
        'zh-CN' => '每日同步',
        'zh-TW' => '每日同步',
        'en-US' => 'Synced daily',
        'ja-JP' => '毎日同期',
        'ko-KR' => '매일 동기화',
    ],
    'llm.chip_stale' => [
        'zh-CN' => '数据更新延迟',
        'zh-TW' => '數據更新延遲',
        'en-US' => 'Data update delayed',
        'ja-JP' => 'データ更新が遅れています',
        'ko-KR' => '데이터 갱신 지연',
    ],
    'llm.arena_aria' => [
        'zh-CN' => '什么是 Arena 评分',
        'zh-TW' => '什麼是 Arena 評分',
        'en-US' => 'What is the Arena score?',
        'ja-JP' => 'Arena スコアとは？',
        'ko-KR' => 'Arena 점수란?',
    ],
    'llm.arena_tip' => [
        'zh-CN' => 'Arena 是 LMArena 的综合对战评分，采用 Bradley-Terry 统计口径，不是 Elo。分数越高，代表用户对战偏好越高；它不是参数量，也不是独立的编程能力分。',
        'zh-TW' => 'Arena 是 LMArena 的綜合對戰評分，採用 Bradley-Terry 統計口徑，不是 Elo。分數越高，代表使用者對戰偏好越高；它不是參數量，也不是獨立的程式能力分。',
        'en-US' => 'Arena is LMArena\'s overall head-to-head rating, using the Bradley-Terry scale rather than Elo. A higher score indicates stronger user preference in battles; it is not a parameter count or an independent coding score.',
        'ja-JP' => 'Arena は LMArena の総合対戦スコアで、Elo ではなく Bradley-Terry 方式を採用しています。スコアが高いほど対戦でユーザーに好まれたことを示し、パラメータ数や独立したコーディング能力のスコアではありません。',
        'ko-KR' => 'Arena는 LMArena의 종합 대결 점수로, Elo가 아닌 Bradley-Terry 방식을 사용합니다. 점수가 높을수록 대결에서 사용자 선호도가 높았다는 뜻이며, 파라미터 수나 독립적인 코딩 점수가 아닙니다.',
    ],
    'llm.search_placeholder' => [
        'zh-CN' => '搜索模型名 / 厂商',
        'zh-TW' => '搜尋模型名 / 廠商',
        'en-US' => 'Search model or vendor',
        'ja-JP' => 'モデル名・ベンダーを検索',
        'ko-KR' => '모델명·벤더 검색',
    ],
    'llm.search_aria' => [
        'zh-CN' => '搜索模型名或厂商',
        'zh-TW' => '搜尋模型名或廠商',
        'en-US' => 'Search by model name or vendor',
        'ja-JP' => 'モデル名かベンダーで検索',
        'ko-KR' => '모델 이름이나 벤더 검색',
    ],

    'llm.col_model' => [
        'zh-CN' => '模型',
        'zh-TW' => '模型',
        'en-US' => 'Model',
        'ja-JP' => 'モデル',
        'ko-KR' => '모델',
    ],
    'llm.col_variant_tip' => [
        'zh-CN' => '当前为 {variant} 推理档位的实测值', 'zh-TW' => '當前為 {variant} 推理檔位的實測值', 'en-US' => 'Measured at the {variant} reasoning effort', 'ja-JP' => '{variant} 推論レベルの実測値', 'ko-KR' => '{variant} 추론 레벨의 측정값',
    ],
    'llm.col_intel' => [
        'zh-CN' => '智能指数',
        'zh-TW' => '智能指數',
        'en-US' => 'Index',
        'ja-JP' => '指数',
        'ko-KR' => '지수',
    ],
    'llm.col_pin' => [
        'zh-CN' => '输入价',
        'zh-TW' => '輸入價',
        'en-US' => 'Input price',
        'ja-JP' => '入力価格',
        'ko-KR' => '입력 가격',
    ],
    // 速度 / 延迟两列过去漏了种子，非中文页一直回落成中文表头（移动端指标面板里更显眼）
    'llm.col_speed' => [
        'zh-CN' => '速度',
        'zh-TW' => '速度',
        'en-US' => 'Speed',
        'ja-JP' => '速度',
        'ko-KR' => '속도',
    ],
    'llm.col_speed_tip' => [
        'zh-CN' => 'Artificial Analysis 实测输出速度，单位 tokens/秒，越高越快。',
        'zh-TW' => 'Artificial Analysis 實測輸出速度，單位 tokens/秒，越高越快。',
        'en-US' => 'Output speed measured by Artificial Analysis, in tokens per second — higher is faster.',
        'ja-JP' => 'Artificial Analysis が実測した出力速度（tokens/秒）。高いほど速くなります。',
        'ko-KR' => 'Artificial Analysis가 측정한 출력 속도(tokens/초)입니다. 높을수록 빠릅니다.',
    ],
    'llm.col_ttft' => [
        'zh-CN' => '延迟',
        'zh-TW' => '延遲',
        'en-US' => 'Latency',
        'ja-JP' => '遅延',
        'ko-KR' => '지연',
    ],
    'llm.col_ttft_tip' => [
        'zh-CN' => 'Artificial Analysis 实测首个 token 延迟，单位秒，越低响应越快。',
        'zh-TW' => 'Artificial Analysis 實測首個 token 延遲，單位秒，越低回應越快。',
        'en-US' => 'Time to first token measured by Artificial Analysis, in seconds — lower is more responsive.',
        'ja-JP' => 'Artificial Analysis が実測した初回トークンまでの遅延（秒）。低いほど応答が速くなります。',
        'ko-KR' => 'Artificial Analysis가 측정한 첫 토큰 지연 시간(초)입니다. 낮을수록 응답이 빠릅니다.',
    ],
    'llm.col_pout' => [
        'zh-CN' => '输出价',
        'zh-TW' => '輸出價',
        'en-US' => 'Output price',
        'ja-JP' => '出力価格',
        'ko-KR' => '출력 가격',
    ],
    'llm.col_date' => [
        'zh-CN' => '上架',
        'zh-TW' => '上架',
        'en-US' => 'Listed',
        'ja-JP' => '公開日',
        'ko-KR' => '등록일',
    ],
    'llm.badge_closed' => [
        'zh-CN' => '闭源',
        'zh-TW' => '閉源',
        'en-US' => 'Closed',
        'ja-JP' => 'クローズド',
        'ko-KR' => '클로즈드',
    ],
    'llm.error_unavailable' => [
        'zh-CN' => '数据暂不可用：快照缺失或损坏，请稍后重试。',
        'zh-TW' => '數據暫不可用：快照缺失或損壞，請稍後重試。',
        'en-US' => 'Data unavailable right now: the snapshot is missing or corrupted. Please retry later.',
        'ja-JP' => 'データを利用できません：スナップショットがないか壊れています。しばらくしてからやり直してください。',
        'ko-KR' => '데이터를 사용할 수 없습니다: 스냅샷이 없거나 손상되었습니다. 잠시 후 다시 시도하세요.',
    ],
    'llm.footnote' => [
        'zh-CN' => 'Arena 评分为 LMArena 的 Bradley-Terry 口径（非 Elo）；价格为 OpenRouter 平台报价（$/1M tokens），可能与官方 API 不同。',
        'zh-TW' => 'Arena 評分為 LMArena 的 Bradley-Terry 口徑（非 Elo）；價格為 OpenRouter 平台報價（$/1M tokens），可能與官方 API 不同。',
        'en-US' => 'Arena scores are LMArena\'s Bradley-Terry scale (not Elo); prices are OpenRouter quotes per million tokens and may differ from official APIs.',
        'ja-JP' => 'Arena スコアは LMArena の Bradley-Terry 方式（Elo ではありません）。価格は OpenRouter の報価（$/1M トークン）で、公式 API とは異なる場合があります。',
        'ko-KR' => 'Arena 점수는 LMArena의 Bradley-Terry 방식입니다(Elo 아님). 가격은 OpenRouter 플랫폼 기준($/1M 토큰)으로 공식 API와 다를 수 있습니다.',
    ],
// P0 补缺（模板已引用但种子缺失，导致非中文回落到中文内联文案）
    'llm.value_free' => [
        'zh-CN' => '免费',
        'zh-TW' => '免費',
        'en-US' => 'Free',
        'ja-JP' => '無料',
        'ko-KR' => '무료',
    ],
];

