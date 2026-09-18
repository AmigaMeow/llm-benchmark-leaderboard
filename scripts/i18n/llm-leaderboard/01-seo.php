<?php
/**
 * llm-leaderboard.php — SEO 文案（title / H1 / description / keywords，按视图分键）。
 *
 * 口径：title/H1 不带年份前缀；description 里的「每日更新（{year}）」由页面传 date('Y')。
 * 模型名与厂商（DeepSeek、Qwen、Llama、GLM、Kimi…）是产品名，不翻译。
 */

return [

    'llm.seo_title_all' => [
        'zh-CN' => '大模型排行榜 - 开源、编程与价格对比 | {site}',
        'zh-TW' => '大模型排行榜 - 開源、程式與價格對比 | {site}',
        'en-US' => 'LLM Leaderboard | {site}',
        'ja-JP' => 'LLM リーダーボード | {site}',
        'ko-KR' => 'LLM 리더보드 | {site}',
    ],
    'llm.seo_title_open' => [
        'zh-CN' => '开源大模型排行榜 | {site}',
        'zh-TW' => '開源大模型排行榜 | {site}',
        'en-US' => 'Open-Source LLM Leaderboard | {site}',
        'ja-JP' => 'オープンソース LLM リーダーボード | {site}',
        'ko-KR' => '오픈소스 LLM 리더보드 | {site}',
    ],
    'llm.seo_title_code' => [
        'zh-CN' => '编程大模型排行榜 | {site}',
        'zh-TW' => '程式大模型排行榜 | {site}',
        'en-US' => 'Coding LLM Leaderboard | {site}',
        'ja-JP' => 'コーディング LLM リーダーボード | {site}',
        'ko-KR' => '코딩 LLM 리더보드 | {site}',
    ],
    'llm.seo_title_cheap' => [
        'zh-CN' => '大模型性价比排行榜 | {site}',
        'zh-TW' => '大模型性價比排行榜 | {site}',
        'en-US' => 'Best-Value LLM Rankings | {site}',
        'ja-JP' => 'コスパ LLM ランキング | {site}',
        'ko-KR' => '가성비 LLM 랭킹 | {site}',
    ],
    'llm.seo_title_new' => [
        'zh-CN' => '最新大模型发布与价格 | {site}',
        'zh-TW' => '最新大模型發布與價格 | {site}',
        'en-US' => 'Latest LLM Releases | {site}',
        'ja-JP' => '最新 LLM リリース | {site}',
        'ko-KR' => '최신 LLM 출시 | {site}',
    ],
    'llm.h1_all' => [
        'zh-CN' => '大模型排行榜',
        'zh-TW' => '大模型排行榜',
        'en-US' => 'LLM Leaderboard',
        'ja-JP' => 'LLM リーダーボード',
        'ko-KR' => 'LLM 리더보드',
    ],
    'llm.h1_open' => [
        'zh-CN' => '开源大模型排行榜',
        'zh-TW' => '開源大模型排行榜',
        'en-US' => 'Open-Source LLM Leaderboard',
        'ja-JP' => 'オープンソース LLM リーダーボード',
        'ko-KR' => '오픈소스 LLM 리더보드',
    ],
    'llm.h1_code' => [
        'zh-CN' => '编程大模型排行榜',
        'zh-TW' => '程式大模型排行榜',
        'en-US' => 'Coding LLM Leaderboard',
        'ja-JP' => 'コーディング LLM リーダーボード',
        'ko-KR' => '코딩 LLM 리더보드',
    ],
    'llm.h1_cheap' => [
        'zh-CN' => '大模型性价比排行榜',
        'zh-TW' => '大模型性價比排行榜',
        'en-US' => 'Best-Value LLM Rankings',
        'ja-JP' => 'コスパ LLM ランキング',
        'ko-KR' => '가성비 LLM 랭킹',
    ],
    'llm.h1_new' => [
        'zh-CN' => '最新大模型',
        'zh-TW' => '最新大模型',
        'en-US' => 'Latest LLMs',
        'ja-JP' => '最新 LLM',
        'ko-KR' => '최신 LLM',
    ],
    'llm.seo_desc_all' => [
        'zh-CN' => '大模型排行榜与测评对照，覆盖全球与国产模型、开源权重、编程向筛选和 API 价格。每日更新（{year}），不自造总分。',
        'zh-TW' => '大模型排行榜與測評對照，覆蓋全球與國產模型、開源權重、程式向篩選和 API 價格。每日更新（{year}），不自造總分。',
        'en-US' => 'LLM leaderboard and benchmark comparison covering global and Chinese models, open weights, coding filters and API prices. Updated daily ({year}), no self-invented scores.',
        'ja-JP' => 'LLM リーダーボードとベンチマーク対照。世界と中国製モデル、オープンウェイト、コーディング向け絞り込み、API 価格を網羅。毎日更新（{year}）、独自スコアは作りません。',
        'ko-KR' => 'LLM 리더보드와 벤치마크 대조. 글로벌·중국 모델, 오픈 웨이트, 코딩 필터, API 가격을 다룹니다. 매일 갱신({year}), 자체 점수는 만들지 않습니다.',
    ],
    'llm.seo_desc_open' => [
        'zh-CN' => '开源大模型排行榜，对照全球开源权重模型的排名与价格，含 DeepSeek、Qwen、Kimi、GLM 等，可本机或私有部署。每日更新（{year}）。',
        'zh-TW' => '開源大模型排行榜，對照全球開源權重模型的排名與價格，含 DeepSeek、Qwen、Kimi、GLM 等，可本機或私有部署。每日更新（{year}）。',
        'en-US' => 'Open-source LLM leaderboard comparing rankings and prices of open-weight models worldwide, including DeepSeek, Qwen, Kimi and GLM — self-hostable or privately deployable. Updated daily ({year}).',
        'ja-JP' => 'オープンソース LLM リーダーボード。世界のオープンウェイトモデルの順位と価格を対照（DeepSeek、Qwen、Kimi、GLM など）。自前・プライベート配置が可能。毎日更新（{year}）。',
        'ko-KR' => '오픈소스 LLM 리더보드. 전 세계 오픈 웨이트 모델의 순위와 가격을 대조합니다(DeepSeek·Qwen·Kimi·GLM 등). 자체·사설 배포 가능. 매일 갱신({year}).',
    ],
    'llm.seo_desc_code' => [
        'zh-CN' => '编程大模型排行榜。本表暂无独立代码基准，编程视图按综合排名排序。每日更新（{year}）。',
        'zh-TW' => '程式大模型排行榜。本表暫無獨立程式碼基準，程式視圖按綜合排名排序。每日更新（{year}）。',
        'en-US' => 'Coding LLM leaderboard. There is no separate coding benchmark in this table yet; the coding view follows the overall ranking. Updated daily ({year}).',
        'ja-JP' => 'コーディング LLM リーダーボード。この表には独立したコーディングベンチマークがまだなく、コーディング表示は総合ランキング順です。毎日更新（{year}）。',
        'ko-KR' => '코딩 LLM 리더보드. 이 표에는 아직 독립된 코딩 벤치마크가 없어 코딩 보기는 종합 순위를 따릅니다. 매일 갱신({year}).',
    ],
    'llm.seo_desc_cheap' => [
        'zh-CN' => '大模型性价比排行榜，按输出价从低到高对照排名与是否开源。价格为 OpenRouter 平台报价。每日更新（{year}）。',
        'zh-TW' => '大模型性價比排行榜，按輸出價從低到高對照排名與是否開源。價格為 OpenRouter 平台報價。每日更新（{year}）。',
        'en-US' => 'Best-value LLM rankings comparing rank and open weights by output price, lowest first. Prices are OpenRouter quotes. Updated daily ({year}).',
        'ja-JP' => 'コスパ LLM ランキング。出力価格の安い順に順位とオープンソースかどうかを対照。価格は OpenRouter の報価です。毎日更新（{year}）。',
        'ko-KR' => '가성비 LLM 랭킹. 출력 가격이 낮은 순으로 순위·오픈소스 여부를 대조합니다. 가격은 OpenRouter 플랫폼 기준. 매일 갱신({year}).',
    ],
    'llm.seo_desc_new' => [
        'zh-CN' => '近期上架的大模型与价格，按上架时间排序。每日更新（{year}）。',
        'zh-TW' => '近期上架的大模型與價格，按上架時間排序。每日更新（{year}）。',
        'en-US' => 'Recently listed LLMs with prices, sorted by listing date. Updated daily ({year}).',
        'ja-JP' => '最近公開された LLM と価格。公開日順に並べています。毎日更新（{year}）。',
        'ko-KR' => '최근 등록된 LLM과 가격. 등록일순으로 정렬합니다. 매일 갱신({year}).',
    ],
    'llm.seo_keywords' => [
        'zh-CN' => '大模型排行榜,AI大模型排行榜,大模型测评,大模型天梯,开源大模型排行榜,编程大模型,大模型性价比,国产大模型排名,哪个大模型好用,LLM排行榜,开源模型盘点,全球大模型排名',
        'zh-TW' => '大模型排行榜,AI大模型排行榜,大模型測評,大模型天梯,開源大模型排行榜,程式大模型,大模型性價比,國產大模型排名,哪個大模型好用,LLM排行榜,開源模型盤點,全球大模型排名',
        'en-US' => 'LLM leaderboard,LLM ranking,LLM benchmarks,open-source LLM leaderboard,coding LLM,best-value LLM,Chinese LLM ranking,LLM price comparison',
        'ja-JP' => 'LLM リーダーボード,LLM ランキング,LLM ベンチマーク,オープンソース LLM リーダーボード,コーディング LLM,コスパ LLM,中国 LLM ランキング,LLM 価格比較',
        'ko-KR' => 'LLM 리더보드,LLM 랭킹,LLM 벤치마크,오픈소스 LLM 리더보드,코딩 LLM,가성비 LLM,중국 LLM 랭킹,LLM 가격 비교',
    ],
    'llm.breadcrumb_home' => [
        'zh-CN' => '首页',
        'zh-TW' => '首頁',
        'en-US' => 'Home',
        'ja-JP' => 'ホーム',
        'ko-KR' => '홈',
    ],
    'llm.breadcrumb_parent' => [
        'zh-CN' => '跑分排行',
        'zh-TW' => '跑分排行',
        'en-US' => 'Benchmarks',
        'ja-JP' => 'ベンチマーク',
        'ko-KR' => '벤치마크',
    ],
    'llm.breadcrumb_self' => [
        'zh-CN' => '大模型排行榜',
        'zh-TW' => '大模型排行榜',
        'en-US' => 'LLM Leaderboard',
        'ja-JP' => 'LLM リーダーボード',
        'ko-KR' => 'LLM 리더보드',
    ],
];