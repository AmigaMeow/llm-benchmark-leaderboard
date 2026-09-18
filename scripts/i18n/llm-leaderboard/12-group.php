<?php
/**
 * llm-leaderboard.php — 默认口径（一个模型一行）的说明文案。
 *
 * 页面默认一个模型一行：同一模型的多个推理档位不再连着占好几行，行上也不再有档位徽标、
 * 族徽之类的标记。既然没有标记可看，「这一行是哪一档」就必须写在脚注里（llm.group_note），
 * 否则读榜的人只能猜。想看档位明细的人切到「按档位」（group=tier），那一屏另有 llm.effort_note。
 *
 * 用法：php scripts/seed-llm-leaderboard-i18n.php  （薄壳，见脚本头注释）
 */

return [
    'llm.group_note' => [
        'zh-CN' => '本表一个模型一行：每个模型取的是当前排序列里有实测值的最优档位（同值时取更强的一档）；Arena 分与价格是模型级数据，同一模型的各档位相同。想看同一模型的每一个推理档位，切到「按档位」。',
        'zh-TW' => '本表一個模型一行：每個模型取的是當前排序欄裡有實測值的最優檔位（同值時取更強的一檔）；Arena 分與價格是模型級資料，同一模型的各檔位相同。想看同一模型的每一個推理檔位，切到「按檔位」。',
        'en-US' => 'One row per model: each row uses that model’s best tier for the column you sorted by (best of the tiers that have a measurement, ties going to the stronger tier). Arena scores and prices are model-level, so every tier of a model shares them. Switch to “Each tier” to see each reasoning tier on its own row.',
        'ja-JP' => '本表は 1 モデル 1 行です。各行は並べ替えている列に実測値がある段階のうち最良のもの（同値ならより強い段階）を使います。Arena スコアと価格はモデル単位のデータのため、同じモデルの各段階で共通です。各段階をすべて見るには「段階ごと」に切り替えてください。',
        'ko-KR' => '이 표는 모델당 한 행입니다. 각 행은 정렬한 열에 측정값이 있는 단계 중 가장 좋은 단계(동률이면 더 강한 단계)를 사용합니다. Arena 점수와 가격은 모델 단위 데이터라 같은 모델의 모든 단계에서 동일합니다. 각 단계를 모두 보려면 “단계별”로 전환하세요.',
    ],
];

