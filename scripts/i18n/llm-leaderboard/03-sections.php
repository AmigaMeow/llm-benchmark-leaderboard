<?php
/**
 * llm-leaderboard.php — 页面区块文案（时间线、来源、FAQ 5 问）。
 *
 * FAQ 答案里的 {link} 由页面分别替换为链接（可见区）与纯文本（JSON-LD）。
 * 模型名/厂商/许可证不翻译。
 */

return [

    'llm.timeline_h2' => [
        'zh-CN' => '近 14 天新模型',
        'zh-TW' => '近 14 天新模型',
        'en-US' => 'New models in the last 14 days',
        'ja-JP' => '過去 14 日間の新着モデル',
        'ko-KR' => '최근 14일 신규 모델',
    ],

    'llm.sources_h2' => [
        'zh-CN' => '数据来源',
        'zh-TW' => '數據來源',
        'en-US' => 'Data sources',
        'ja-JP' => 'データソース',
        'ko-KR' => '데이터 출처',
    ],
    'llm.source_lmarena' => [
        'zh-CN' => '（Arena 评分，数据集 CC-BY-4.0）',
        'zh-TW' => '（Arena 評分，數據集 CC-BY-4.0）',
        'en-US' => '(Arena scores, dataset CC-BY-4.0)',
        'ja-JP' => '（Arena スコア、データセット CC-BY-4.0）',
        'ko-KR' => '(Arena 점수, 데이터셋 CC-BY-4.0)',
    ],
    'llm.source_openrouter' => [
        'zh-CN' => '（模型价格与上架信息）',
        'zh-TW' => '（模型價格與上架資訊）',
        'en-US' => '(model prices and listing info)',
        'ja-JP' => '（モデル価格と公開情報）',
        'ko-KR' => '(모델 가격 및 등록 정보)',
    ],
    'llm.source_aa' => [
        'zh-CN' => '（评测智能指数，未在本表展示）',
        'zh-TW' => '（評測智能指數，未在本表展示）',
        'en-US' => '(intelligence index, not shown in this table)',
        'ja-JP' => '（評価指数、この表には未掲載）',
        'ko-KR' => '(평가 지수, 이 표에는 미표시)',
    ],
    'llm.source_note' => [
        'zh-CN' => '本页为非官方镜像，不自造总分；价格为 OpenRouter 平台报价（$/1M tokens）；Arena 分为 Bradley-Terry 口径，不是 Elo。',
        'zh-TW' => '本頁為非官方鏡像，不自造總分；價格為 OpenRouter 平台報價（$/1M tokens）；Arena 分為 Bradley-Terry 口徑，不是 Elo。',
        'en-US' => 'Unofficial mirror — no self-invented scores. Prices are OpenRouter quotes per million tokens; Arena scores use the Bradley-Terry scale, not Elo.',
        'ja-JP' => '非公式ミラーです。独自スコアは作りません。価格は OpenRouter の報価（$/1M トークン）、Arena スコアは Bradley-Terry 方式で Elo ではありません。',
        'ko-KR' => '비공식 미러이며 자체 점수는 만들지 않습니다. 가격은 OpenRouter 기준($/1M 토큰), Arena 점수는 Bradley-Terry 방식으로 Elo가 아닙니다.',
    ],
    /* 必须与 llm_board_intel_compare 的实际语义一致（includes/llm-board-data.php:55-72）：
       智能指数降序；没有指数的模型排在后面；同分/同缺时以 Arena 综合评分定序。原写「按 Arena 降序」是错的。 */
    'llm.faq_h2' => [
        'zh-CN' => '常见问题',
        'zh-TW' => '常見問題',
        'en-US' => 'FAQ',
        'ja-JP' => 'よくある質問',
        'ko-KR' => '자주 묻는 질문',
    ],
    'llm.faq_q1' => [
        'zh-CN' => '现在哪个大模型最强？',
        'zh-TW' => '現在哪個大模型最強？',
        'en-US' => 'Which LLM is the strongest right now?',
        'ja-JP' => '今いちばん強い LLM は？',
        'ko-KR' => '지금 가장 강한 LLM은?',
    ],
    'llm.faq_a1' => [
        'zh-CN' => '以本表综合排名为准，接近时再看价格和是否开源，没有单一最强。',
        'zh-TW' => '以本表綜合排名為準，接近時再看價格和是否開源，沒有單一最強。',
        'en-US' => 'Go by the overall ranking in this table; when scores are close, weigh price and open weights — there is no single strongest.',
        'ja-JP' => 'この表の総合ランキングを基準にしてください。スコアが接近している場合は価格とオープンソースかどうかで判断します。',
        'ko-KR' => '이 표의 종합 순위를 기준으로 하세요. 점수가 비슷하면 가격과 오픈소스 여부로 판단합니다.',
    ],
    'llm.faq_q2' => [
        'zh-CN' => '开源大模型和闭源有什么区别？',
        'zh-TW' => '開源大模型和閉源有什麼區別？',
        'en-US' => 'What is the difference between open-weight and closed LLMs?',
        'ja-JP' => 'オープンソース LLM とクローズドの違いは？',
        'ko-KR' => '오픈소스 LLM과 클로즈드의 차이는?',
    ],
    'llm.faq_a2' => [
        'zh-CN' => '开源权重可本机或私有部署；闭源一般走 API。开源列表见 {link}。',
        'zh-TW' => '開源權重可本機或私有部署；閉源一般走 API。開源列表見 {link}。',
        'en-US' => 'Open weights can be self-hosted or deployed privately; closed models usually run behind an API. See the {link}.',
        'ja-JP' => 'オープンウェイトは自前・プライベート環境に配置できます。クローズドは通常 API 経由です。一覧は {link}。',
        'ko-KR' => '오픈 웨이트는 자체·사설 배포가 가능하고, 클로즈드는 보통 API로 이용합니다. 목록은 {link}.',
    ],
    'llm.faq_link_open' => [
        'zh-CN' => '开源大模型排行榜',
        'zh-TW' => '開源大模型排行榜',
        'en-US' => 'open-source LLM leaderboard',
        'ja-JP' => 'オープンソース LLM リーダーボード',
        'ko-KR' => '오픈소스 LLM 리더보드',
    ],
    'llm.faq_q3' => [
        'zh-CN' => '写代码该看哪个大模型？',
        'zh-TW' => '寫程式碼該看哪個大模型？',
        'en-US' => 'Which LLM should I look at for coding?',
        'ja-JP' => 'コーディングにはどの LLM を見ればいい？',
        'ko-KR' => '코딩에는 어떤 LLM을 봐야 하나요?',
    ],
    'llm.faq_a3' => [
        'zh-CN' => '专业代码榜和本表不是同一套题。编程向筛选见 {link}，当前按综合分排序。',
        'zh-TW' => '專業程式碼榜和本表不是同一套題。程式向篩選見 {link}，當前按綜合分排序。',
        'en-US' => 'Professional coding benchmarks are a different test. For a coding-oriented filter see the {link}, currently sorted by overall score.',
        'ja-JP' => '専門のコーディングベンチマークはこの表と別の問題セットです。コーディング向けの絞り込みは {link} を参照（現在は総合スコア順）。',
        'ko-KR' => '전문 코딩 벤치마크는 이 표와 다른 문제 세트입니다. 코딩 필터는 {link}를 참고하세요(현재 종합 점수순).',
    ],
    'llm.faq_link_code' => [
        'zh-CN' => '编程大模型排行榜',
        'zh-TW' => '程式大模型排行榜',
        'en-US' => 'coding LLM leaderboard',
        'ja-JP' => 'コーディング LLM リーダーボード',
        'ko-KR' => '코딩 LLM 리더보드',
    ],
    'llm.faq_q4' => [
        'zh-CN' => '现在国产最强的大模型是谁？',
        'zh-TW' => '現在國產最強的大模型是誰？',
        'en-US' => 'Which Chinese LLM is the strongest right now?',
        'ja-JP' => '今いちばん強い中国製 LLM は？',
        'ko-KR' => '지금 가장 강한 중국산 LLM은?',
    ],
    'llm.faq_a4' => [
        'zh-CN' => '没有单一最强。国产开源可看 {link}，接近时再看价格和能否本机部署。',
        'zh-TW' => '沒有單一最強。國產開源可看 {link}，接近時再看價格和能否本機部署。',
        'en-US' => 'There is no single strongest. For open-weight Chinese models see the {link}; when scores are close, weigh price and self-hosting.',
        'ja-JP' => '単一の最強はありません。中国製オープンウェイトは {link} を参照し、接近していれば価格と自前配置の可否で判断します。',
        'ko-KR' => '단일 최강은 없습니다. 중국산 오픈 웨이트는 {link}를 보고, 점수가 비슷하면 가격과 자체 배포 가능 여부로 판단하세요.',
    ],
    'llm.faq_q5' => [
        'zh-CN' => '价格为什么和官网不同？',
        'zh-TW' => '價格為什麼和官網不同？',
        'en-US' => 'Why do prices differ from the official site?',
        'ja-JP' => '価格が公式サイトと違うのはなぜ？',
        'ko-KR' => '가격이 공식 사이트와 다른 이유는?',
    ],
    'llm.faq_a5' => [
        'zh-CN' => '表内是 OpenRouter 美元/百万 token，可能与官方 API 或国内中转不同。',
        'zh-TW' => '表內是 OpenRouter 美元/百萬 token，可能與官方 API 或國內中轉不同。',
        'en-US' => 'Figures here are OpenRouter quotes in USD per million tokens and may differ from official APIs or local resellers.',
        'ja-JP' => 'この表の価格は OpenRouter のドル/100 万トークン換算で、公式 API や国内中継とは異なる場合があります。',
        'ko-KR' => '이 표의 가격은 OpenRouter 기준 USD/백만 토큰으로, 공식 API나 국내 중개와 다를 수 있습니다.',
    ],
// P0 补缺（模板已引用但种子缺失，导致非中文回落到中文内联文案）
    'llm.rank_up_tip' => [
        'zh-CN' => '较 {date} 排名上升 {n} 位',
        'zh-TW' => '較 {date} 排名上升 {n} 位',
        'en-US' => 'Up {n} places vs {date}',
        'ja-JP' => '{date} 比で {n} 位上昇',
        'ko-KR' => '{date} 대비 {n}위 상승',
    ],
    'llm.rank_down_tip' => [
        'zh-CN' => '较 {date} 排名下降 {n} 位',
        'zh-TW' => '較 {date} 排名下降 {n} 位',
        'en-US' => 'Down {n} places vs {date}',
        'ja-JP' => '{date} 比で {n} 位低下',
        'ko-KR' => '{date} 대비 {n}위 하락',
    ],
    'llm.timeline_note' => [
        'zh-CN' => '按上架时间排序，均为已进入排行榜的模型；尚未评分的新模型见「尚未进入 Arena 排名」。',
        'zh-TW' => '按上架時間排序，均為已進入排行榜的模型；尚未評分的新模型見「尚未進入 Arena 排名」。',
        'en-US' => 'Sorted by listing date; all models here already have Arena rankings. Unrated new models are listed in the “Not yet ranked by Arena” section.',
        'ja-JP' => '掲載日順。いずれもArenaランキング入り済みのモデルです。未評価の新モデルは「まだArenaランキングに未掲載」を参照してください。',
        'ko-KR' => '등록일 순이며 모두 Arena 순위에 진입한 모델입니다. 미평가 신규 모델은 “아직 Arena 순위 없음” 섹션을 참고하세요.',
    ],
    'llm.unranked_h2' => [
        'zh-CN' => '尚未进入 Arena 排名',
        'zh-TW' => '尚未進入 Arena 排名',
        'en-US' => 'Not yet ranked by Arena',
        'ja-JP' => 'まだArenaランキングに未掲載',
        'ko-KR' => '아직 Arena 순위 없음',
    ],
    'llm.unranked_note' => [
        'zh-CN' => '以下 {count} 个模型已被本站收录，但 LMArena 尚未给出评分，因此不参与上方排名（有名次才叫排名，不造分）。按上架时间倒序；LMArena 收录后会自动进入排行榜。',
        'zh-TW' => '以下 {count} 個模型已被本站收錄，但 LMArena 尚未給出評分，因此不參與上方排名（有名次才叫排名，不造分）。按上架時間倒序；LMArena 收錄後會自動進入排行榜。',
        'en-US' => 'The {count} models below are indexed here, but LMArena has not scored them yet, so they do not appear in the ranking above (we only show real ranks — no invented scores). Sorted newest first; they join the leaderboard automatically once Arena lists them.',
        'ja-JP' => '以下の {count} モデルは当サイトに収録済みですが、LMArena が未評価のため上のランキングには含まれません（実スコアのみ掲載、独自採点はしません）。掲載日の新しい順。Arena に掲載されると自動的にランキングに反映されます。',
        'ko-KR' => '아래 {count}개 모델은 본 사이트에 수록되어 있지만 LMArena 아직 평가가 없어 위 순위에는 포함되지 않습니다(실제 점수만 표기, 자체 채점 없음). 등록일 최신순이며 Arena 등재 시 자동으로 순위에 반영됩니다.',
    ],
// P2：首屏 summary 卡标签
    'llm.sum_aria' => [
        'zh-CN' => '排行榜摘要',
        'zh-TW' => '排行榜摘要',
        'en-US' => 'Leaderboard highlights',
        'ja-JP' => 'ランキング概要',
        'ko-KR' => '순위 요약',
    ],
    'llm.sum_top' => [
        'zh-CN' => '综合第一',
        'zh-TW' => '綜合第一',
        'en-US' => 'Top overall',
        'ja-JP' => '総合 1 位',
        'ko-KR' => '종합 1위',
    ],
    'llm.sum_new' => [
        'zh-CN' => '最新上榜',
        'zh-TW' => '最新上榜',
        'en-US' => 'Newest',
        'ja-JP' => '新着',
        'ko-KR' => '최신 등재',
    ],
];

