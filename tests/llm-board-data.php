<?php
require __DIR__ . '/../includes/llm-board-data.php';
$fixtureFile = __DIR__ . '/llm-board-fixture.json';
foreach ($argv as $arg) {
    if (str_starts_with((string)$arg, '--fixture=')) $fixtureFile = __DIR__ . '/' . substr((string)$arg, 10);
}
$models = json_decode(file_get_contents($fixtureFile), true);
$queries = [[], ['view' => 'open'], ['view' => 'cheap'], ['view' => 'new'], ['view' => 'code'], ['view' => 'intel'], ['view' => 'pareto', 'weights' => 'open', 'sort' => 'price'], ['weights' => 'unknown'], ['weights' => 'closed', 'sort' => 'new'], ['q' => 'DEEPSEEK'], ['q' => '中文'], ['q' => '<script>'], ['weights' => 'invalid', 'sort' => 'invalid'], ['view' => 'cheap', 'weights' => 'open', 'sort' => 'new'],
    // 列头排序（本轮新增）：arena 是 score 的列别名，pout 是 price 的列别名，其余为列专有键
    ['sort' => 'arena'], ['sort' => 'speed'], ['sort' => 'ttft'], ['sort' => 'pin'], ['sort' => 'pout'], ['sort' => 'value'], ['weights' => 'open', 'sort' => 'value']];
$output = [];
foreach ($queries as $query) {
    $state = llm_board_state($query);
    $output[] = ['state' => $state, 'ids' => array_column(llm_board_filter($models, $state), 'id')];
}
// 两个轴各有一条前沿。顺序必须与 tests/llm-board-data.cjs 逐位置对齐（整体 deepEqual 对拍）
$output[] = ['frontier_speed' => array_column(llm_board_frontier($models, 'speed'), 'id')];
$output[] = ['frontier' => array_column(llm_board_frontier($models), 'id')];
// 档位行（模型 × 推理强度档位）：与 JS filterRows 逐查询对拍
$variantModels = json_decode(file_get_contents(__DIR__ . '/llm-board-variants-fixture.json'), true);
// Arena 区间专用 fixture：8 个模型里 7 个带 CI（b/f 刻意两侧不对称），用来算「榜级分辨率」
$ciModels = json_decode(file_get_contents(__DIR__ . '/llm-board-ci-fixture.json'), true);
$rowQueries = [[], ['sort' => 'score'], ['sort' => 'price'], ['sort' => 'new'], ['weights' => 'open'], ['weights' => 'unknown'], ['weights' => 'closed'], ['q' => 'xhigh'], ['q' => 'MULTI'], ['q' => '中文'],
    // 列头排序（本轮新增）：档位行与模型级走同一套列比较器
    ['sort' => 'arena'], ['sort' => 'speed'], ['sort' => 'ttft'], ['sort' => 'pin'], ['sort' => 'value']];
if (in_array('--rows-json', $argv, true)) {
    $rowOutput = [];
    foreach ($rowQueries as $query) {
        $state = llm_board_state($query);
        $rowOutput[] = ['state' => $state, 'rows' => array_column(llm_board_filter_rows($variantModels, $state), 'row_key')];
    }
    echo json_encode($rowOutput);
    exit;
}
if (in_array('--family-json', $argv, true)) {
    $familyOutput = [];
    foreach ($rowQueries as $query) {
        $state = llm_board_state($query);
        $marks = llm_board_family_marks(llm_board_filter_rows($variantModels, $state));
        $familyOutput[] = ['state' => $state, 'marks' => array_map(static fn($row) => [
            'row_key' => (string)$row['row_key'],
            'family_size' => (int)$row['family_size'],
            'family_rank' => (int)$row['family_rank'],
            'family_color' => (int)$row['family_color'],
        ], $marks)];
    }
    echo json_encode($familyOutput);
    exit;
}
if (in_array('--group-json', $argv, true)) {
    $groupOutput = [];
    foreach ($rowQueries as $query) {
        $state = llm_board_state($query);
        $expanded = llm_board_filter_rows($variantModels, $state);
        $sizes = llm_board_family_sizes($expanded);
        $marks = llm_board_family_marks(llm_board_group_models($expanded), $sizes);
        $groupOutput[] = ['state' => $state, 'rows' => array_map(static fn($row) => [
            'row_key' => (string)$row['row_key'],
            'family_size' => (int)$row['family_size'],
            'family_rank' => (int)$row['family_rank'],
            'family_color' => (int)$row['family_color'],
        ], $marks)];
    }
    echo json_encode($groupOutput);
    exit;
}
if (in_array('--ci-json', $argv, true)) {
    // Arena 置信区间：行级取值与 JS filterRows 逐查询对拍（含 model 级字段在档位行上的复制）
    $ciOutput = [];
    foreach ($rowQueries as $query) {
        $state = llm_board_state($query);
        $ciOutput[] = ['state' => $state, 'rows' => array_map(static fn($row) => [
            'row_key' => (string)$row['row_key'],
            'arena_score' => $row['arena_score'],
            'arena_ci_low' => $row['arena_ci_low'],
            'arena_ci_high' => $row['arena_ci_high'],
        ], llm_board_filter_rows($variantModels, $state))];
    }
    echo json_encode($ciOutput);
    exit;
}
if (in_array('--listed-json', $argv, true)) {
    // 「上架」列的相对时间：固定基准 + 一批天偏移，SSR 分档与 JS listedText 逐条对拍。
    // 天偏移覆盖每一档的边界（0/1/2/6/7/13/14/27/28/30/45/60/364/365/730）。
    $listedSeed = require __DIR__ . '/../scripts/i18n/llm-leaderboard/14-listed.php';
    $listedNow = 1800000000; // 固定「现在」（秒），避免测试跨天抖动
    $offsets = [0, 1, 2, 3, 6, 7, 10, 13, 14, 27, 28, 30, 45, 60, 180, 364, 365, 730, 1095, -5];
    $listedOutput = [];
    foreach (['zh-CN', 'zh-TW', 'en-US', 'ja-JP', 'ko-KR'] as $lang) {
        $copy = [];
        foreach (['today', 'yesterday', 'days', 'weeks', 'week', 'months', 'month', 'years', 'year'] as $key) {
            $copy[$key] = $listedSeed['llm.listed_' . $key][$lang];
        }
        $cases = [];
        foreach ($offsets as $offset) {
            $ts = $listedNow - $offset * 86400;
            $cases[] = ['offset' => $offset, 'ts' => $ts, 'text' => llm_board_listed_text($ts, $copy, $listedNow)];
        }
        // 缺失/非法时间戳不编造「今天」（ts 是判断依据，offset 只用来读日志）
        $cases[] = ['offset' => null, 'ts' => null, 'text' => llm_board_listed_text(null, $copy, $listedNow)];
        $cases[] = ['offset' => 0, 'ts' => 0, 'text' => llm_board_listed_text(0, $copy, $listedNow)];
        $listedOutput[] = ['lang' => $lang, 'now' => $listedNow, 'copy' => $copy, 'cases' => $cases];
    }
    echo json_encode($listedOutput);
    exit;
}
if (in_array('--ci-resolution-json', $argv, true)) {
    // 榜级分辨率：与 JS data.ciResolution 对拍。行集合必须与页面渲染的那一份完全一致
    // （按模型视图先折叠、按档位视图保留多行，靠函数内部按模型去重），所以两个 fixture 都跑：
    // ci fixture 有 7 个带区间的模型（真能算出结论），variants fixture 用来证明「一模型多档只算一次」。
    $resolutionQueries = [[], ['sort' => 'arena'], ['sort' => 'price'], ['sort' => 'new'], ['weights' => 'open'], ['weights' => 'closed'], ['weights' => 'unknown'], ['q' => 'ALPHA'], ['q' => '中文'], ['group' => 'tier'], ['group' => 'tier', 'sort' => 'arena'], ['view' => 'pareto', 'weights' => 'open', 'sort' => 'price']];
    $resolutionOutput = [];
    foreach ($resolutionQueries as $query) {
        $state = llm_board_state($query);
        foreach ([['llm-board-ci-fixture.json', $ciModels], ['llm-board-variants-fixture.json', $variantModels]] as [$fixtureName, $fixtureModels]) {
            $expanded = llm_board_filter_rows($fixtureModels, $state);
            $visible = $state['group'] === 'model' ? llm_board_group_models($expanded) : llm_board_family_marks($expanded);
            $resolutionOutput[] = ['state' => $state, 'fixture' => $fixtureName, 'resolution' => llm_board_ci_resolution($visible)];
        }
    }
    echo json_encode($resolutionOutput);
    exit;
}
if (in_array('--facts-json', $argv, true)) {
    // 行内明细的「该出现哪些事实」：与 JS data.rowFacts 逐行对拍。三种榜（缺 CI / 多档 / 基础）
    // × 两种口径 × 五种名次状态（无 / 升 / 降 / 新上榜 / 无变化），把分支全部走一遍。
    $deltaCases = [
        'none' => null,
        'up' => ['d' => 3, 'isNew' => false, 'date' => '2026-09-04'],
        'down' => ['d' => -2, 'isNew' => false, 'date' => '2026-09-04'],
        'new' => ['d' => null, 'isNew' => true, 'date' => '2026-09-04'],
        'flat' => ['d' => 0, 'isNew' => false, 'date' => '2026-09-04'],
    ];
    $factsOutput = ['threshold' => llm_board_low_votes_threshold(), 'cases' => []];
    foreach ([['llm-board-ci-fixture.json', $ciModels], ['llm-board-variants-fixture.json', $variantModels], ['llm-board-fixture.json', $models]] as [$fixtureName, $fixtureModels]) {
        foreach (['model', 'tier'] as $group) {
            $state = llm_board_state(['group' => $group]);
            $expanded = llm_board_filter_rows($fixtureModels, $state);
            $visible = $group === 'model' ? llm_board_group_models($expanded) : llm_board_family_marks($expanded);
            foreach ($visible as $row) {
                $effortRow = $group === 'tier' && ($row['effort'] ?? '') !== '';
                foreach ($deltaCases as $caseName => $delta) {
                    $factsOutput['cases'][] = [
                        'fixture' => $fixtureName,
                        'group' => $group,
                        'row_key' => (string)$row['row_key'],
                        'delta' => $caseName,
                        'facts' => llm_board_row_facts($row, $delta, $effortRow),
                    ];
                }
            }
        }
    }
    echo json_encode($factsOutput);
    exit;
}
if (in_array('--json', $argv, true)) {
    echo json_encode($output);
    exit;
}
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
check($output[0]['ids'][0] === 'free', 'Default ranking must include scored free models');
check($output[2]['ids'][0] === 'free', 'Free models sort first by price');
check(end($output[2]['ids']) === 'unknown', 'Missing prices sort last');
check($output[7]['ids'] === ['unknown', 'c'], 'Unknown weights must not be classified as closed');
check($output[10]['ids'] === ['unicode'], 'Unicode search');
check($output[11]['ids'] === [], 'Unmatched search');
check(end($output)['frontier'] === ['unicode', 'b', 'd', 'a'], 'Frontier handles equal price, score ties and missing prices');
check(llm_board_frontier([]) === [], 'Empty chart');
check(llm_board_frontier([], 'speed') === [], 'Empty speed chart');
check(llm_board_frontier($models, 'speed') === [], '主 fixture 没有测速数据，速度前沿应为空');
check(count(llm_board_frontier([$models[0]])) === 1, 'Single-point chart');
check(llm_board_state(['view' => [], 'q' => [], 'sort' => [], 'weights' => []]) === ['view' => 'all', 'weights' => 'all', 'sort' => 'intel', 'q' => '', 'group' => 'model'], 'Malformed query values');
// 显示口径：缺省 = 一个模型一行（默认视图）；只有显式 group=tier 才展开档位
check(llm_board_state([])['group'] === 'model' && llm_board_state(['group' => 'tier'])['group'] === 'tier' && llm_board_state(['group' => 'model'])['group'] === 'model' && llm_board_state(['group' => 'bogus'])['group'] === 'model', 'Display grouping defaults to one row per model');

// 列头排序：键别名、方向与评分价格比口径
check(llm_board_sort_alias('score') === 'arena' && llm_board_sort_alias('price') === 'pout' && llm_board_sort_alias('value') === 'value', 'Legacy sort keys map onto their column');
check(llm_board_sort_direction('ttft') === 'asc' && llm_board_sort_direction('pin') === 'asc' && llm_board_sort_direction('value') === 'desc' && llm_board_sort_direction('new') === 'desc', 'Column directions are fixed');
check(llm_board_value_score(['arena_score' => 1400, 'price_out' => 2]) === 700.0, 'Value ratio is Arena divided by output price');
check(llm_board_value_score(['arena_score' => 1400, 'price_out' => 0]) === null && llm_board_value_score(['arena_score' => null, 'price_out' => 2]) === null, 'Free and unscored rows have no value ratio');
// arena/pout 是旧键的列别名：同一份顺序，老链接不会因为换键而变榜
$idsFor = static function (array $models, array $query) { return array_column(llm_board_filter($models, llm_board_state($query)), 'id'); };
check($idsFor($models, ['sort' => 'arena']) === $idsFor($models, ['sort' => 'score']), 'Arena column alias keeps the legacy score sort');
check($idsFor($models, ['sort' => 'pout']) === $idsFor($models, ['sort' => 'price']), 'Output price column alias keeps the legacy price sort');
check($idsFor($models, ['sort' => 'pin']) === ['b', 'a', 'free', 'unknown', 'd', 'e', 'c', 'unicode'], 'Input price sorts cheap first and floats missing prices last');
check($idsFor($models, ['sort' => 'value']) === ['unicode', 'b', 'd', 'c', 'e', 'a', 'free', 'unknown'], 'Value ratio sorts best score per dollar first');
check($idsFor($models, ['sort' => 'speed']) === $idsFor($models, ['sort' => 'intel']) && $idsFor($models, ['sort' => 'ttft']) === $idsFor($models, ['sort' => 'intel']), 'Columns without measurements fall back to the default order');

// 榜级主指标：全 Arena 样本回落 arena_score，行为与旧口径完全一致
check(llm_board_primary_metric($models) === 'arena_score', 'Arena-only board keeps Arena primary metric');
foreach (['', 'NaN', 'Infinity', '0x10', true, [], INF, -1] as $value) {
    $entries = [['id' => 'bad-score', 'arena_score' => $value, 'price_out' => 1], ['id' => 'bad-price', 'arena_score' => 1400, 'price_out' => $value]];
    $filtered = llm_board_filter($entries, llm_board_state([]));
    check(array_column($filtered, 'id') === ['bad-price'] && $filtered[0]['price_out'] === null, 'Invalid snapshot numbers');
    check(llm_board_priced($entries) === [], 'Invalid values cannot enter chart');
}

// 档位行：模型 × 推理强度档位展开（AA 同构）
$rowsDefault = llm_board_filter_rows($variantModels, llm_board_state([]));
check(array_column($rowsDefault, 'row_key') === ['multi#max', 'multi#xhigh', 'solo', 'multi#high', 'dirty#medium', 'dirty#max', 'arena_only_multi', 'plain', 'dirty#low'], 'Effort rows interleave by each setting score');
check(count(llm_board_filter($variantModels, llm_board_state([]))) === 5, 'Model-level list still keeps one entry per model');
check(count(llm_board_variant_rows($variantModels)) === 9, 'Variant expansion row count');
check(array_column(llm_board_variant_rows($variantModels), 'row_key') === ['multi#max', 'multi#xhigh', 'multi#high', 'solo', 'plain', 'dirty#max', 'dirty#low', 'dirty#medium', 'arena_only_multi'], 'Expansion keeps model order before sorting');
$rowByKey = [];
foreach ($rowsDefault as $row) { $rowByKey[$row['row_key']] = $row; }
check($rowByKey['multi#xhigh']['aa_intelligence'] === 52.0 && $rowByKey['multi#xhigh']['aa_speed'] === 60.0 && $rowByKey['multi#xhigh']['aa_ttft'] === 0.9, 'Each row carries its own measured setting values');
check($rowByKey['multi#xhigh']['price_out'] === 10.0 && $rowByKey['multi#xhigh']['arena_score'] === 1500.0, 'Price and Arena stay model-level on every row');
check($rowByKey['multi#max']['effort'] === 'max' && $rowByKey['multi#max']['effort_current'] === 'max', 'Effort badge follows the row setting');
check($rowByKey['solo']['effort'] === null && $rowByKey['solo']['row_key'] === 'solo' && $rowByKey['solo']['aa_intelligence'] === 51.0, 'Single-setting model gets no effort badge but keeps its score');
check($rowByKey['arena_only_multi']['aa_intelligence'] === 30.0 && $rowByKey['arena_only_multi']['effort'] === null, 'Single variant fills the model-level score');
check($rowByKey['dirty#low']['aa_intelligence'] === null && $rowByKey['dirty#low']['aa_coding'] === 30.0, 'Variant with only a coding score still gets a row');
check(!isset($rowByKey['dirty#bogus']), 'Unknown effort labels are dropped');
check($rowByKey['dirty#medium']['aa_intelligence'] === 45.5 && $rowByKey['dirty#max']['aa_coding'] === null, 'Variant metrics follow the same numeric rules as model fields');
check(array_column(llm_board_filter_rows($variantModels, llm_board_state(['sort' => 'score'])), 'row_key') === ['multi#max', 'multi#xhigh', 'multi#high', 'solo', 'plain', 'dirty#medium', 'dirty#max', 'dirty#low', 'arena_only_multi'], 'Arena sort keeps a model rows together, strongest setting first');
check(array_column(llm_board_filter_rows($variantModels, llm_board_state(['sort' => 'price'])), 'row_key') === ['arena_only_multi', 'plain', 'solo', 'dirty#medium', 'dirty#max', 'dirty#low', 'multi#max', 'multi#xhigh', 'multi#high'], 'Price sort keeps a model rows together');
check(array_column(llm_board_filter_rows($variantModels, llm_board_state(['weights' => 'unknown'])), 'row_key') === ['dirty#medium', 'dirty#max', 'dirty#low'], 'Weight filter applies to every row of a model');
check(array_column(llm_board_filter_rows($variantModels, llm_board_state(['q' => 'xhigh'])), 'row_key') === ['multi#xhigh'], 'Search matches the effort label');
check(llm_board_filter_rows([], llm_board_state([])) === [], 'Empty board has no rows');
// 列头排序在档位行上同样成立：延迟升序（缺失沉底）、评分价格比是模型级字段（同模型各行同值）
check(array_column(llm_board_filter_rows($variantModels, llm_board_state(['sort' => 'ttft'])), 'row_key') === ['solo', 'multi#high', 'multi#xhigh', 'multi#max', 'dirty#medium', 'dirty#max', 'arena_only_multi', 'plain', 'dirty#low'], 'Latency sort puts the fastest setting first and floats rows without a measurement last');
check(array_column(llm_board_filter_rows($variantModels, llm_board_state(['sort' => 'value'])), 'row_key') === ['plain', 'solo', 'dirty#medium', 'dirty#max', 'dirty#low', 'multi#max', 'multi#xhigh', 'multi#high', 'arena_only_multi'], 'Value ratio sort keeps every effort row of a model together');

// 同族标记：族内名次按当前顺序数、族色按模型 id 稳定、相邻多档族不同色、单档不标
$familyRows = llm_board_family_marks($rowsDefault);
$familyByKey = [];
foreach ($familyRows as $familyRow) { $familyByKey[(string)$familyRow['row_key']] = $familyRow; }
check($familyByKey['multi#max']['family_size'] === 3 && $familyByKey['multi#max']['family_rank'] === 1, 'Family size counts every row of the model');
check($familyByKey['multi#high']['family_rank'] === 3, 'Family rank follows the current table order');
check($familyByKey['dirty#medium']['family_rank'] === 1 && $familyByKey['dirty#max']['family_rank'] === 2 && $familyByKey['dirty#low']['family_rank'] === 3, 'Second family counts its own rows');
check($familyByKey['multi#xhigh']['family_color'] === $familyByKey['multi#max']['family_color'] && $familyByKey['multi#high']['family_color'] === $familyByKey['multi#max']['family_color'], 'Every tier of a model shares one family colour');
check($familyByKey['multi#max']['family_color'] >= 1 && $familyByKey['multi#max']['family_color'] <= 6, 'Family colour stays inside the palette');
check($familyByKey['multi#max']['family_color'] !== $familyByKey['dirty#max']['family_color'], 'Adjacent families never share a colour');
check($familyByKey['solo']['family_size'] === 1 && $familyByKey['solo']['family_color'] === 0 && $familyByKey['plain']['family_size'] === 1 && $familyByKey['arena_only_multi']['family_size'] === 1, 'Single-tier models carry no family mark');
check(array_column($familyRows, 'row_key') === array_column($rowsDefault, 'row_key'), 'Family marks never reorder or drop rows');
check(llm_board_family_marks([]) === [], 'Empty board has no families');
// 纯函数：不改输入（同一份输入跑两次结果一致，且原数组不带 family_* 字段）
check(llm_board_family_marks($rowsDefault) === $familyRows, 'Family marks are deterministic');
check(!array_key_exists('family_size', $rowsDefault[0]), 'Family marks do not mutate their input');
// 颜色规则：一个族一个色，且当前排序下相邻的两个族一定不同色（色条能读出来就靠这条）。
// 注意「相邻」是行序上的相邻族，不是先出现的族 —— 真实榜上族是交错的，只看上一个不同的族会漏。
foreach ([[], ['sort' => 'ttft'], ['sort' => 'value'], ['sort' => 'new'], ['sort' => 'arena'], ['sort' => 'speed']] as $familyQuery) {
    $ordered = llm_board_family_marks(llm_board_filter_rows($variantModels, llm_board_state($familyQuery)));
    $colorById = [];
    $runs = [];
    foreach ($ordered as $row) {
        $id = (string)$row['id'];
        if ((int)$row['family_size'] < 2) continue;
        if (isset($colorById[$id])) {
            check($colorById[$id] === (int)$row['family_color'], 'A family keeps one colour inside a sort order');
        } else {
            $colorById[$id] = (int)$row['family_color'];
        }
        if ($runs === [] || end($runs) !== $id) $runs[] = $id;
    }
    for ($i = 1; $i < count($runs); $i++) {
        check($colorById[$runs[$i - 1]] !== $colorById[$runs[$i]], 'Adjacent families never share a colour');
    }
}
// 交错排列 A-B-A-C，且 C 的起点色故意和 A 撞：只看「上一个不同的族」的写法会漏掉这种相邻
$weaveIds = ['weave-a', 'weave-b'];
foreach (['weave-c1', 'weave-c2', 'weave-c3', 'weave-c4', 'weave-c5', 'weave-c6', 'weave-c7', 'weave-c8'] as $candidate) {
    if (llm_board_family_hash($candidate) % 6 === llm_board_family_hash('weave-a') % 6) { $weaveIds[] = $candidate; break; }
}
check(count($weaveIds) === 3, 'Woven fixture needs two ids whose starting colours collide');
$weave = [];
foreach ([0, 1, 0, 2, 0, 1, 2] as $which) {
    $weave[] = ['id' => $weaveIds[$which], 'row_key' => $weaveIds[$which] . '#' . count($weave)];
}
$weaveColors = [];
foreach (llm_board_family_marks($weave) as $row) { $weaveColors[(string)$row['id']] = (int)$row['family_color']; }
check($weaveColors[$weaveIds[0]] !== $weaveColors[$weaveIds[2]], 'A colliding colour is re-picked when the families end up next to each other');
check($weaveColors[$weaveIds[1]] !== $weaveColors[$weaveIds[2]] && $weaveColors[$weaveIds[0]] !== $weaveColors[$weaveIds[1]], 'Every woven neighbour stays distinguishable');

// 按模型折叠（group=model）：一个模型一行，代表档位 = 当前排序列最优的那一档（默认口径）
check(llm_board_state(['group' => 'tier'])['group'] === 'tier' && llm_board_state(['group' => 'bogus'])['group'] === 'model' && llm_board_state([])['group'] === 'model', 'Group accepts exactly the two known modes');
$groupDefault = llm_board_group_models($rowsDefault);
check(count($groupDefault) === 5, 'Collapsing leaves one row per model');
check(array_column($groupDefault, 'row_key') === ['multi#max', 'solo', 'dirty#medium', 'arena_only_multi', 'plain'], 'Collapsed rows keep the table order and the first row of each model');
// 代表档位跟着排序列走：智能指数排序取 multi#max，延迟排序取 multi#high（0.5s 是这族最快的）
$repFor = static function (array $query) use ($variantModels) {
    $rows = llm_board_group_models(llm_board_filter_rows($variantModels, llm_board_state($query)));
    foreach ($rows as $row) { if ((string)$row['id'] === 'multi') return (string)$row['row_key']; }
    return '';
};
check($repFor([]) === 'multi#max' && $repFor(['sort' => 'ttft']) === 'multi#high' && $repFor(['sort' => 'speed']) === 'multi#max', 'The representative tier follows the sorted column');
// 折叠不丢模型：模型数 = 折叠后的行数，且每行的族内名次都是 1（只剩一行）
$groupSizes = llm_board_family_sizes($rowsDefault);
$groupMarks = llm_board_family_marks($groupDefault, $groupSizes);
check(count($groupMarks) === 5 && count(array_filter($groupMarks, static fn($r) => (int)$r['family_rank'] === 1)) === 5, 'Collapsed rows are the first tier of their family');
$groupByKey = [];
foreach ($groupMarks as $groupRow) { $groupByKey[(string)$groupRow['row_key']] = $groupRow; }
check($groupByKey['multi#max']['family_size'] === 3 && $groupByKey['multi#max']['family_color'] >= 1, 'Collapsed rows keep the expanded tier count and a family colour');
check($groupByKey['solo']['family_size'] === 1 && $groupByKey['solo']['family_color'] === 0, 'Single-tier models stay unmarked when collapsed');
check(llm_board_group_models([]) === [], 'Empty board collapses to nothing');

// 档位徽标：AA 英文档位名，五语同一份（只有悬浮说明与脚注本地化）
$effortSeed = require __DIR__ . '/../scripts/i18n/llm-leaderboard/09-variant.php';
foreach (llm_board_effort_labels() as $label) {
    $key = 'llm.effort_' . str_replace('-', '_', $label);
    check(isset($effortSeed[$key]) && count($effortSeed[$key]) === 5, "Effort copy must cover five languages: {$key}");
    check(count(array_unique($effortSeed[$key])) === 1, "Effort badge must not be translated: {$key}");
    check($effortSeed[$key]['en-US'] === llm_board_effort_label_en($label), "Effort badge falls back to the English label: {$key}");
}
check(isset($effortSeed['llm.effort_badge_tip']) && count($effortSeed['llm.effort_badge_tip']) === 5, 'Effort tip covers five languages');
foreach ($effortSeed['llm.effort_badge_tip'] as $lang => $text) {
    check(substr_count($text, '{label}') === 1, "Effort tip keeps exactly one {label} placeholder [{$lang}]");
}
check(count($effortSeed['llm.effort_note'] ?? []) === 5, 'Effort footnote covers five languages');
// 默认口径脚注：五语齐全、不留占位符（行上没有标记可看，口径全写在这句里）
$groupSeed = require __DIR__ . '/../scripts/i18n/llm-leaderboard/12-group.php';
check(isset($groupSeed['llm.group_note']) && count($groupSeed['llm.group_note']) === 5, 'Default-view note covers five languages');
foreach ($groupSeed['llm.group_note'] as $lang => $text) {
    check(trim($text) !== '', "Default-view note must not be blank [{$lang}]");
    check(substr_count($text, '{') === 0, "Default-view note takes no placeholders [{$lang}]");
}
// 上架时间的相对时间文案：五语齐全；每个 {n} 恰好一次（多一个就会原样露到页面上）；
// 天档不会出现 1，所以只有周/月/年需要单数键（英语 1 week / 1 month / 1 year ago）
$listedSeed = require __DIR__ . '/../scripts/i18n/llm-leaderboard/14-listed.php';
foreach (['today' => 0, 'yesterday' => 0, 'days' => 1, 'weeks' => 1, 'week' => 1, 'months' => 1, 'month' => 1, 'years' => 1, 'year' => 1] as $listedKey => $placeholders) {
    $key = 'llm.listed_' . $listedKey;
    check(isset($listedSeed[$key]) && count($listedSeed[$key]) === 5, "Listed-at copy must cover five languages: {$key}");
    foreach ($listedSeed[$key] as $lang => $text) {
        check(substr_count($text, '{n}') === $placeholders, "{$key} keeps {$placeholders} {{n}} [{$lang}]");
        check(trim($text) !== '', "Listed-at copy must not be blank: {$key} [{$lang}]");
    }
}
// Arena 置信区间：只做数值清洗与透传，缺/坏值必须是 null（而不是 0 —— 0 会被读成「区间为零」）
$ciById = [];
foreach (llm_board_models($models) as $model) $ciById[(string)$model['id']] = $model;
check($ciById['a']['arena_ci_low'] === 1490.5 && $ciById['a']['arena_ci_high'] === 1512.3, 'Arena CI passes through as numbers');
check($ciById['b']['arena_ci_low'] === null && $ciById['b']['arena_ci_high'] === null, 'Missing CI stays null');
check($ciById['d']['arena_ci_low'] === null && $ciById['d']['arena_ci_high'] === null, 'Negative and non-numeric CI are dropped');
// 档位行是模型级字段的复制：同一个模型的每一档拿到的区间完全相同
$ciRows = llm_board_filter_rows($variantModels, llm_board_state([]));
$ciRowByKey = [];
foreach ($ciRows as $row) $ciRowByKey[(string)$row['row_key']] = $row;
foreach (['multi#max', 'multi#xhigh', 'multi#high'] as $key) {
    check(isset($ciRowByKey[$key]), "CI fixture row must exist: {$key}");
    check($ciRowByKey[$key]['arena_ci_low'] === 1489.5 && $ciRowByKey[$key]['arena_ci_high'] === 1510.5, "Model-level CI is copied to every tier row: {$key}");
}
check($ciRowByKey['plain']['arena_ci_low'] === null, 'Rows of a model without CI stay null');
// CI 文案：五语齐全、{low}/{high}/{half} 各一个占位符
$ciSeed = require __DIR__ . '/../scripts/i18n/llm-leaderboard/13-ci.php';
check(isset($ciSeed['llm.ci_tip']) && count($ciSeed['llm.ci_tip']) === 5, 'CI copy must cover five languages');
foreach ($ciSeed['llm.ci_tip'] as $lang => $text) {
    foreach (['{low}', '{high}', '{half}'] as $token) {
        check(substr_count($text, $token) === 1, "llm.ci_tip keeps exactly one {$token} [{$lang}]");
    }
    check(trim($text) !== '', "CI copy must not be blank [{$lang}]");
}
// 榜级分辨率：把「本屏 Arena 分只能分辨多大差距」算成一句话挂在脚注里。
// 关键口径（画面上一句都不说，而不是给个假结论）：带区间模型 < 5 或相邻可比对 < 3 → null；
// 半宽取 max(high-score, score-low)（fixture 里 b/f 刻意不对称）；逐档行按模型只算一次。
$ciModels = json_decode(file_get_contents(__DIR__ . '/llm-board-ci-fixture.json'), true);
$ciVisible = llm_board_filter_rows($ciModels, llm_board_state([]));
check(llm_board_ci_resolution($ciVisible) === ['models' => 7, 'pairs' => 5, 'overlap' => 3, 'half' => 5.0, 'gap' => 8.0], 'Board resolution reads the CI fixture');
check(llm_board_ci_resolution(llm_board_group_models($ciVisible)) === llm_board_ci_resolution($ciVisible), 'Folding to one row per model does not change the resolution');
check(llm_board_ci_resolution(array_merge($ciVisible, $ciVisible)) === llm_board_ci_resolution($ciVisible), 'Tier rows of the same model only count once');
check(llm_board_ci_resolution($ciRows) === null, 'A board without enough intervals says nothing');
check(llm_board_ci_resolution([]) === null, 'An empty board says nothing');
check(llm_board_ci_resolution($ciVisible[0]) === null, 'A single row says nothing');
$ciSynthetic = static function (int $count): array {
    $rows = [];
    for ($i = 0; $i < $count; $i++) {
        $rows[] = ['id' => 'm' . $i, 'arena_score' => 1500 - $i, 'arena_ci_low' => 1490.0 - $i, 'arena_ci_high' => 1510.0 - $i];
    }
    return $rows;
};
check(llm_board_ci_resolution($ciSynthetic(5)) !== null, 'Five models with intervals are enough to speak');
check(llm_board_ci_resolution($ciSynthetic(4)) === null, 'Four models with intervals are not enough to speak');
check(llm_board_ci_resolution($ciSynthetic(6)) === ['models' => 6, 'pairs' => 5, 'overlap' => 5, 'half' => 10.0, 'gap' => 1.0], 'Even samples average the two middle values');
check(llm_board_ci_half(['arena_score' => 1500, 'arena_ci_low' => 1495, 'arena_ci_high' => 1508]) === 8.0, 'Half width takes the wider side');
check(llm_board_ci_half(['arena_score' => 1500, 'arena_ci_low' => null, 'arena_ci_high' => 1508]) === null, 'Half width needs all three values');
check(llm_board_ci_half(['arena_score' => 1500, 'arena_ci_low' => 1500, 'arena_ci_high' => 1500]) === null, 'Zero-width intervals do not count as an interval');
check(llm_board_median([3, 1, 2]) === 2.0 && llm_board_median([4, 1, 3, 2]) === 2.5 && llm_board_median([]) === null, 'Median handles odd, even and empty samples');
// 两处新增文案：五语齐全、占位符不多不少（多一个就会原样露到页面上）
$detailSeed = require __DIR__ . '/../scripts/i18n/llm-leaderboard/15-row-detail.php';
foreach (['llm.ci_resolution', 'llm.detail_more', 'llm.detail_less', 'llm.detail_ci', 'llm.detail_votes', 'llm.detail_move'] as $detailKey) {
    check(isset($detailSeed[$detailKey]) && count($detailSeed[$detailKey]) === 5, "Row copy must cover five languages: {$detailKey}");
    foreach ($detailSeed[$detailKey] as $lang => $text) {
        check(trim($text) !== '', "Row copy must not be blank: {$detailKey} [{$lang}]");
        if ($detailKey !== 'llm.ci_resolution') check(substr_count($text, '{') === 0, "Row copy takes no placeholders: {$detailKey} [{$lang}]");
    }
}
foreach ($detailSeed['llm.ci_resolution'] as $lang => $text) {
    check(substr_count($text, '{half}') === 2, "Resolution copy repeats {half} on purpose [{$lang}]");
    foreach (['{gap}', '{overlap}', '{pairs}'] as $token) check(substr_count($text, $token) === 1, "llm.ci_resolution keeps exactly one {$token} [{$lang}]");
}
// 图表横轴两套口径 + 气泡尺寸映射。这几个函数是 SSR 与 JS 共用的判定源，
// 任何一处与 assets/js/llm-leaderboard.js / llm-pareto-tip.js 的镜像函数不一致，
// 都会让同一次加载出现「SSR 画一套、JS 重绘换成另一套」。
$axisModels = [
    ['id' => 'both', 'aa_intelligence' => 50, 'aa_speed' => 80, 'price_out' => 2],
    ['id' => 'noprice', 'aa_intelligence' => 45, 'aa_speed' => 40],
    ['id' => 'nospeed', 'aa_intelligence' => 60, 'price_out' => 3],
    ['id' => 'free', 'aa_intelligence' => 55, 'aa_speed' => 100, 'price_out' => 0],
];
$priceAxis = array_column(llm_board_plottable($axisModels, 'price'), 'id');
$speedAxis = array_column(llm_board_plottable($axisModels, 'speed'), 'id');
check($priceAxis === ['both', 'nospeed'], 'Price axis keeps models with a positive output price only');
// 免费（$0）模型上不了对数价格轴，但它有测速，必须能上速度轴
check($speedAxis === ['both', 'noprice', 'free'], 'Speed axis needs a measured speed and does not care about price');
check(llm_board_plottable($axisModels) === llm_board_plottable($axisModels, 'price'), 'Default axis stays the price axis');

// 档位分数：只取有实测智能指数的档位，按分数升序（竖线的两端由它决定）
$tierModel = ['aa_variants' => [
    ['label' => 'max', 'intel' => 50, 'speed' => 60],
    ['label' => 'low', 'intel' => 40, 'speed' => 150],
    ['label' => 'mid', 'intel' => 30],
    ['label' => 'noscore', 'speed' => 200],
]];
check(llm_board_tier_scores($tierModel) === [30.0, 40.0, 50.0], 'Tier scores keep only scored variants, ascending');
check(llm_board_tier_scores(['aa_variants' => [['label' => 'a', 'speed' => 9]]]) === [], 'A variant with no score contributes nothing');
check(llm_board_tier_scores([]) === [] && llm_board_tier_scores(['aa_variants' => null]) === [], 'A model with no variants has no tier scores');
check(llm_board_tier_drawable([40.0, 50.0]), 'Two distinct tier scores are a ladder');
check(!llm_board_tier_drawable([50.0, 50.0]), 'Equal tier scores are a zero-length line');
check(!llm_board_tier_drawable([40.0]), 'A single tier is not a ladder');
check(!llm_board_tier_drawable([]), 'No tier scores means no ladder');

// 气泡：价格为尺寸时，免费（$0）必须拿到最大半径。
// 这里踩过坑：判定写成「> 0」会把免费模型当成缺数据落到最小半径，图例写着「越大越便宜」，
// 图上却把最便宜的画成最小的一点 —— 正好说反。
$bubblePrice = llm_board_bubble_radii($axisModels, 'price');
check($bubblePrice['r']['free'] === $bubblePrice['rMax'], 'A free model gets the largest bubble when size means price');
check($bubblePrice['r']['both'] < $bubblePrice['r']['free'], 'A paid model is smaller than a free one when size means price');
check($bubblePrice['r']['noprice'] === $bubblePrice['rMin'], 'A model with no price falls back to the minimum radius');
check($bubblePrice['lo'] === 0.0, 'The size range starts at zero when a free model is present');

$bubbleSpeed = llm_board_bubble_radii($axisModels, 'speed');
check($bubbleSpeed['r']['free'] === $bubbleSpeed['rMax'], 'The fastest model gets the largest bubble when size means speed');
check($bubbleSpeed['r']['noprice'] < $bubbleSpeed['rMax'] && $bubbleSpeed['r']['nospeed'] === $bubbleSpeed['rMin'], 'No measured speed means the minimum radius, never a fake fast bubble');
check($bubbleSpeed['count'] === 3 && $bubblePrice['count'] === 3, 'Only models with a usable size value are counted');
check(llm_board_bubble_radii([], 'price')['count'] === 0, 'An empty board counts no size values, so the legend range is suppressed');
/* 面积映射：半径取平方根。这里用「尺寸＝速度」那条（线性映射，不受价格对数改动影响），
   取一个 ratio≈0.25 的点：sqrt 映射给到区间的 1/2 处，按半径线性只给到 1/4 处。
   原来这条用的是价格（0/5/10），价格改成对数映射后那组数的算术中点不再是对数中点，会假红。 */
$mid = llm_board_bubble_radii([['id' => 'a', 'aa_speed' => 1], ['id' => 'b', 'aa_speed' => 25], ['id' => 'c', 'aa_speed' => 100]], 'speed');
check($mid['r']['b'] > $mid['rMin'] + ($mid['rMax'] - $mid['rMin']) * 0.4, 'Radius uses a square-root (area) mapping, not a linear one（sqrt 给 0.5，线性只给 0.25）');
check($mid['r']['a'] === $mid['rMin'] && $mid['r']['c'] === $mid['rMax'], 'Range endpoints sit exactly on rMin / rMax');

/* 价格跨数量级，必须按对数映射：线性映射把绝大多数模型挤到「最便宜」那一端，
   实测线上 29 个气泡里 25 个顶到最大半径，尺寸编码等于没编码、图标全被撑满。 */
$manyPrices = [];
for ($i = 0; $i < 20; $i++) { $manyPrices[] = ['id' => 'p' . $i, 'price_out' => pow(10, -1 + $i * 0.15)]; }
$logBubble = llm_board_bubble_radii($manyPrices, 'price');
$logRadii = array_values($logBubble['r']);
$topped = count(array_filter($logRadii, static fn($r) => $r >= $logBubble['rMax'] - 1.5));
check($topped <= count($manyPrices) / 2, '对数映射下不该有过半气泡顶到最大半径，实际 ' . $topped . '/' . count($manyPrices));

echo "Leaderboard PHP data tests passed\n";
