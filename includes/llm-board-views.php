<?php
// Included by llm-leaderboard.php with its escaped presentation helpers.
$ui = static fn($key) => $esc(__('llm.ui.' . $key));
?>
<div class="llm-toolbar">
    <nav id="llm-views" aria-label="<?php echo $ui('ranking'); ?>">
<?php foreach (['all' => 'ranking', 'pareto' => 'chart', 'speed' => 'chart_speed'] as $mode => $label): $active = $boardState['view'] === $mode; ?>
        <a class="llm-view-btn<?php echo $active ? ' is-active' : ''; ?>" data-view="<?php echo $mode; ?>" href="<?php echo $esc($pillHref($mode)); ?>#llm-views"<?php echo $active ? ' aria-current="page"' : ''; ?>><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="<?php echo $mode === 'all' ? 'M4 6h2m4 0h10M4 12h2m4 0h10M4 18h2m4 0h10' : ($mode === 'speed' ? 'M12 20a8 8 0 1 1 8-8M12 20l4.5-5.5M12 4v2M4.9 7.2l1.4 1.4M4 14h2' : 'M4 3v17h17M7 15l4-6 4 3 5-7'); ?>"/></svg><?php echo $ui($label); ?></a>
<?php endforeach; ?>
    </nav>
    <form id="llm-filters" class="llm-filters" method="get" action="/llm-leaderboard.php#llm-views">
        <input type="hidden" name="view" value="<?php echo $esc($boardState['view']); ?>">
        <input type="hidden" name="lang" value="<?php echo $esc($currentLang); ?>">
        <div class="llm-search-wrap">
            <svg class="llm-search-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="9" cy="9" r="5.5" stroke="currentColor" stroke-width="1.6"/><path d="m13 13 3.5 3.5" stroke="currentColor" stroke-width="1.6"/></svg>
            <input type="search" id="llm-search" name="q" maxlength="120" value="<?php echo $esc($boardState['q']); ?>" placeholder="<?php echo $esc($llm_seo('llm.search_placeholder', '搜索模型名 / 厂商')); ?>" aria-label="<?php echo $esc($llm_seo('llm.search_aria', '搜索模型名或厂商')); ?>">
        </div>
        <label class="llm-select-wrap" for="llm-weights"><span><?php echo $ui('weights'); ?></span>
            <select id="llm-weights" name="weights">
<?php foreach (['all', 'open', 'closed', 'unknown'] as $key): ?>
                <option value="<?php echo $key; ?>"<?php echo $boardState['weights'] === $key ? ' selected' : ''; ?>><?php echo $ui('weights_' . $key); ?></option>
<?php endforeach; ?>
            </select>
        </label>
        <?php /* 排序只作用于表格。图表视图里表格是隐藏的，留着它就是个「点了没反应」的控件 ——
                 与「按模型 / 按档位」同样处理：那类控件一律不出现在图表视图。
                 （实测：在图表视图里改排序，URL 会变，但图表不依赖排序、表格又看不见，界面毫无反应。） */ ?>
<?php if (!$isChartView): ?>
        <label class="llm-select-wrap" for="llm-sort"><span><?php echo $ui('sort'); ?></span>
            <select id="llm-sort" name="sort">
<?php foreach ($sortOptions as $key => $label): ?>
                <option value="<?php echo $esc($key); ?>"<?php echo $sortAlias === $key ? ' selected' : ''; ?>><?php echo $esc($label); ?></option>
<?php endforeach; ?>
            </select>
        </label>
<?php endif; ?>
        <button type="submit" class="llm-filter-submit"><?php echo $ui('apply'); ?></button>
    </form>
    <button type="button" id="llm-share-open" class="llm-share-open" aria-haspopup="dialog" aria-controls="llm-share-modal"<?php echo $snapshot === null || $ssrModels === [] ? ' disabled' : ''; ?>><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 16V3m0 0L7 8m5-5 5 5M5 13v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6"/></svg><span><?php echo $ui('share_image'); ?></span></button>
</div>
<div id="llm-share-modal" class="llm-share-modal" hidden aria-hidden="true">
    <div class="llm-share-backdrop" data-share-close></div>
    <section class="llm-share-dialog" role="dialog" aria-modal="true" aria-labelledby="llm-share-title">
        <header class="llm-share-header">
            <div>
                <p class="llm-share-kicker"><?php echo $ui('share_kicker'); ?></p>
                <h2 id="llm-share-title"><?php echo $ui('share_title'); ?></h2>
            </div>
            <button type="button" class="llm-share-close" data-share-close aria-label="<?php echo $ui('share_close'); ?>"><span aria-hidden="true">×</span></button>
        </header>
        <div class="llm-share-body">
            <div class="llm-share-preview-column">
                <div class="llm-share-preview-heading"><span><?php echo $ui('share_preview'); ?></span><span id="llm-share-size" class="llm-share-size"></span></div>
                <div id="llm-share-preview" class="llm-share-preview" aria-live="polite"></div>
                <p id="llm-share-status" class="llm-share-status" role="status"></p>
            </div>
            <aside class="llm-share-settings" aria-label="<?php echo $ui('share_settings'); ?>">
                <div class="llm-share-setting">
                    <span class="llm-share-label"><?php echo $ui('share_orientation'); ?></span>
                    <div class="llm-share-segmented" role="group" aria-label="<?php echo $ui('share_orientation'); ?>">
                        <button type="button" class="is-active" data-share-orientation="landscape"><?php echo $ui('share_landscape'); ?></button>
                        <button type="button" data-share-orientation="portrait"><?php echo $ui('share_portrait'); ?></button>
                    </div>
                </div>
                <div class="llm-share-setting">
                    <span class="llm-share-label"><?php echo $ui('share_theme'); ?></span>
                    <div class="llm-share-segmented" role="group" aria-label="<?php echo $ui('share_theme'); ?>">
                        <button type="button" class="is-active" data-share-theme="light"><?php echo $ui('share_theme_light'); ?></button>
                        <button type="button" data-share-theme="dark"><?php echo $ui('share_theme_dark'); ?></button>
                    </div>
                </div>
                <div class="llm-share-setting">
                    <div class="llm-share-label-row"><span class="llm-share-label"><?php echo $ui('share_count'); ?></span><output id="llm-share-count-value" for="llm-share-count"></output></div>
                    <input id="llm-share-count" type="range" min="1" max="20" value="10" step="1">
                    <div class="llm-share-range-labels"><span>1</span><span>20</span></div>
                    <div class="llm-share-quick-counts" role="group" aria-label="<?php echo $ui('share_count'); ?>">
                        <button type="button" data-share-count="5">5</button><button type="button" data-share-count="10">10</button><button type="button" data-share-count="15">15</button><button type="button" data-share-count="20">20</button>
                    </div>
                </div>
                <div class="llm-share-setting">
                    <label class="llm-share-label" for="llm-share-highlight"><?php echo $ui('share_highlight'); ?></label>
                    <select id="llm-share-highlight"><option value=""><?php echo $ui('share_highlight_none'); ?></option></select>
                </div>
                <label class="llm-share-check"><input id="llm-share-prices" type="checkbox"><span><?php echo $ui('share_show_prices'); ?></span></label>
                <p class="llm-share-scope" id="llm-share-scope"></p>
                <details class="llm-share-method"><summary><?php echo $ui('share_data_note'); ?></summary><p><?php echo $ui('share_data_note_body'); ?></p></details>
            </aside>
        </div>
        <footer class="llm-share-footer">
            <span class="llm-share-footer-note"><?php echo $ui('share_footer_note'); ?></span>
            <div class="llm-share-actions">
                <button type="button" class="llm-share-action llm-share-action-secondary" data-share-action="copy" disabled><?php echo $ui('share_copy'); ?></button>
                <button type="button" class="llm-share-action llm-share-action-secondary" data-share-action="system" hidden><?php echo $ui('share_system'); ?></button>
                <button type="button" class="llm-share-action llm-share-action-primary" data-share-action="download" disabled><?php echo $ui('share_download'); ?></button>
            </div>
        </footer>
    </section>
</div>
<p class="llm-legacy-note" id="llm-legacy-note"<?php echo in_array($view, ['code', 'intel'], true) ? '' : ' hidden'; ?>><?php echo $ui($view === 'code' ? 'legacy_code' : 'legacy_intel'); ?></p>
<div class="llm-results-meta"><span class="llm-results-count-wrap"><span id="llm-results-count" role="status"><?php echo $esc(__($isChartView ? 'llm.js.results_count' : 'llm.js.results_rows', ['count' => $isChartView ? count(llm_board_plottable($ssrModels, $chartAxis)) : count($ssrRows)])); ?></span><span><?php echo $ui('unit'); ?></span></span>
<?php /* 「按模型/按档位」是表格的分行口径（一个模型一行 / 每个推理档位一行）。
       图表视图里没有表格，这个控件点下去只会刷新页面而什么都不变 ——
       实测 ?view=pareto 与 ?view=pareto&group=tier 渲染完全相同。既然无效就别显示。 */ ?>
<?php if (!$isChartView): ?>
    <span class="llm-group-switch" role="group" aria-label="<?php echo $ui('group'); ?>">
<?php foreach (['model' => 'group_model', 'tier' => 'group_tier'] as $groupKey => $groupLabelKey): $groupOn = $boardState['group'] === $groupKey; ?>
        <a class="llm-group-btn<?php echo $groupOn ? ' is-active' : ''; ?>" data-group="<?php echo $groupKey; ?>" href="<?php echo $esc($groupHref($groupKey)); ?>"<?php echo $groupOn ? ' aria-current="page"' : ''; ?>><?php echo $ui($groupLabelKey); ?></a>
<?php endforeach; ?>
    </span>
<?php endif; ?>
</div>
<section class="llm-pareto" aria-labelledby="llm-chart-title"<?php echo $isChartView ? '' : ' hidden'; ?>>
    <div class="llm-chart-heading"><h2 id="llm-chart-title"><?php echo $ui($chartAxis === 'speed' ? 'chart_title_speed' : 'chart_title'); ?></h2><button id="llm-chart-expand" type="button" aria-expanded="false" hidden><?php echo $esc(__('llm.js.expand_chart')); ?></button></div>
<?php if ($snapshot === null): ?>
    <p class="llm-error"><?php echo $esc($llm_seo('llm.error_unavailable', '数据暂不可用：快照缺失或损坏，请稍后重试。')); ?></p>
<?php elseif ($paretoPts === []): ?>
    <p class="llm-error"><?php echo $ui($chartAxis === 'speed' ? 'chart_empty_speed' : 'chart_empty'); ?></p>
<?php else:
    $paretoMetric = llm_board_primary_metric($paretoPts);
    $pScores = array_column($paretoPts, $paretoMetric);
    /* 纵轴下界不许为负：这是分数，负数没有意义。一个 5.8 分的模型
       （Mistral Large）就能让公式算出 -10 —— 图例上出现 -10 会让读者以为分数可以是负的，
       而且白白占掉一截高度。上界不动。JS 侧同公式必须一起改（SSR 与 JS 重绘同形）。 */
    $minS = max(0.0, floor((min($pScores) - 10) / 10) * 10);
    $maxS = ceil((max($pScores) + 10) / 10) * 10;
    /* 横轴口径决定缩放方式，也决定 $px 收什么单位：
       - price：对数刻度（价格跨 3 个数量级，线性画会把便宜模型全挤在左边缘）
       - speed：线性刻度（tok/s 本来就是可比的数量，取对数反而读不出「快一倍」）
       $px 收的是原始值，$pxOf 收整个模型行 —— 调用点因此不必各自判断用哪个字段。 */
    if ($chartAxis === 'price') {
        $pPrices = array_map(static fn($m) => log10((float)$m['price_out']), $paretoPts);
        $minX = floor(min($pPrices) * 2) / 2 - .1; $maxX = ceil(max($pPrices) * 2) / 2 + .1;
        $px = static fn($price) => 60 + (log10((float)$price) - $minX) / max(.1, $maxX - $minX) * 700;
    } else {
        $pSpeeds = array_map(static fn($m) => (float)$m['aa_speed'], $paretoPts);
        $spanPad = max(4.0, (max($pSpeeds) - min($pSpeeds)) * .08);
        $minX = max(0.0, floor((min($pSpeeds) - $spanPad) / 10) * 10);
        $maxX = ceil((max($pSpeeds) + $spanPad) / 10) * 10;
        $px = static fn($speed) => 60 + ((float)$speed - $minX) / max(.1, $maxX - $minX) * 700;
    }
    $pxOf = static fn(array $m) => $px($chartAxis === 'price' ? $m['price_out'] : $m['aa_speed']);
    // 悬浮/读屏里点的横轴值：价格写 $x.xx，速度写 nn/s
    $fmtX = static fn($v) => $chartAxis === 'price' ? $fmtPrice($v) : round((float)$v) . '/s';
    $py = static fn($score) => 35 + (1 - ((float)$score - $minS) / max(1, $maxS - $minS)) * 350;
    $visibleChartIds = array_column(llm_board_plottable($ssrModels, $chartAxis), 'id');
?>
    <div class="llm-pareto-scroll">
        <svg id="llm-chart" class="llm-pareto-svg" viewBox="0 0 800 440" role="group" aria-label="<?php echo $ui($chartAxis === 'speed' ? 'chart_speed' : 'chart'); ?>">
<?php for ($tick = $minS; $tick <= $maxS; $tick += max(10, ceil(($maxS - $minS) / 5 / 10) * 10)): $ty = $py($tick); ?>
                    <line x1="60" y1="<?php echo $ty; ?>" x2="760" y2="<?php echo $ty; ?>" class="llm-pareto-grid"/><text x="50" y="<?php echo $ty + 4; ?>" class="llm-pareto-tick" text-anchor="end"><?php echo $tick; ?></text>
<?php endfor; ?>
<?php if ($chartAxis === 'price'): for ($power = ceil($minX); $power <= floor($maxX); $power++): $price = pow(10, $power); $tx = $px($price); ?>
                    <text x="<?php echo $tx; ?>" y="410" class="llm-pareto-tick" text-anchor="middle"><?php echo $esc($fmtPrice($price)); ?></text>
<?php endfor; else:
    /* 速度轴的刻度按「好看的步长」取（1/2/5×10^n），不用对数刻度那一套 ——
       横轴已经是线性空间，再用十进制幂次取点会得到 1、10、100 这种跟数据无关的刻度。 */
    $spanX = $maxX - $minX;
    $rawStep = $spanX / 6;
    $mag = pow(10, floor(log10(max($rawStep, 1e-9))));
    $normStep = $rawStep / $mag;
    $xStep = ($normStep <= 1 ? 1 : ($normStep <= 2 ? 2 : ($normStep <= 5 ? 5 : 10))) * $mag;
    for ($tickX = ceil($minX / $xStep) * $xStep; $tickX <= $maxX + 1e-9; $tickX += $xStep): ?>
                    <text x="<?php echo round($px($tickX), 1); ?>" y="410" class="llm-pareto-tick" text-anchor="middle"><?php echo $esc((string)round($tickX)); ?></text>
<?php endfor; endif; ?>
                    <text x="60" y="20" class="llm-pareto-tick"><?php echo $esc(__('llm.js.chart_y')); ?></text>
                    <text x="410" y="434" text-anchor="middle" class="llm-pareto-tick"><?php echo $esc(__($chartAxis === 'speed' ? 'llm.js.chart_x_speed' : 'llm.js.chart_x')); ?></text>
<?php if ($paretoFrontier !== []): ?>
                    <polyline points="<?php echo implode(' ', array_map(static fn($m) => $pxOf($m) . ',' . $py($m[$paretoMetric]), $paretoFrontier)); ?>" class="llm-pareto-line"/>
<?php endif; ?>
<?php foreach ($paretoPts as $pm):
    $cx = $pxOf($pm); $cy = $py($pm[$paretoMetric]);
    $slug = $llmAliases[$pm['id']]['icon'] ?? '';
    $front = in_array($pm['id'], $paretoFrontierIds, true);
    $visible = in_array($pm['id'], $visibleChartIds, true);

    /* 推理档位竖线，只在价格视图画：那边一列档位共享同一个横坐标，竖线本身就是要说的事实。
       速度视图不画 —— 档位间的速度差中位数只占图宽 4%，连线几乎全是竖的，
       既不多说什么，又在气泡之间多出一层说不清的细线（实测过，很乱）。
       另外只在纵轴是智能指数时可画：aa_variants 里只有 intel/coding，没有 Arena 分。
       注意 $paretoMetric 是字段名（aa_intelligence / arena_score），不是 intel 这种别名。 */
    /* 两个视图都要算：档位线虽然不画了，但档位区间是悬浮说明的一部分，
       而 JS 侧是无条件算的（见 llm-pareto-tip.js 的 tiers）——
       只在一个视图里给会让 SSR 与 JS 重绘不同形。 */
    $tierScores = ($paretoMetric === 'aa_intelligence') ? llm_board_tier_scores($pm) : [];
    $tierDraw = llm_board_tier_drawable($tierScores);
    $tierText = $tierDraw ? ' · ' . __('llm.js.effort_range') . ' ' . number_format(min($tierScores), 1) . '–' . number_format(max($tierScores), 1) : '';
    /* 速度只从模型自身读，不从气泡映射读 —— 气泡在两个视图里代表不同的量，
       拿它当「速度」用会让速度视图的读屏文案把价格念成速度。 */
    $speed = isset($pm['aa_speed']) && is_numeric($pm['aa_speed']) ? (float)$pm['aa_speed'] : 0.0;
    $speedText = $speed > 0 ? ' · ' . __('llm.js.speed') . ' ' . round($speed) . '/s' : '';
?>
                    <a href="/llm-model.php?id=<?php echo $esc(rawurlencode($pm['id'])); ?>&amp;lang=<?php echo $esc($currentLang); ?>" class="llm-pareto-pt<?php echo $front ? ' is-front' : ''; ?><?php echo !$visible ? ' is-muted' : ''; ?>"<?php echo !$visible ? ' tabindex="-1" aria-hidden="true"' : ''; ?> aria-label="<?php echo $esc(($pm['display_name'] ?? $pm['id']) . ' · ' . __('llm.js.chart_y') . ' ' . $pm[$paretoMetric] . ' · ' . $fmtX($chartAxis === 'price' ? $pm['price_out'] : $pm['aa_speed']) . $speedText . $tierText); ?>">
<?php
// 气泡半径由 llm-leaderboard.php 统一算好（$bubbleR），图标与字母按同比例缩放，
// 这样 r=14（下界）时与改版前逐像素一致。半径落到字母上要用 style 而非属性 ——
// .llm-point-letter 的 font-size 在样式表里，表现属性压不过它。
$ptR = $bubbleR[$pm['id']] ?? 14.0;
$ptIcon = (int)round($ptR * 1.43);
$ptFont = round($ptR * 0.79, 1);
?>
                        <circle class="llm-point-bg" cx="<?php echo $cx; ?>" cy="<?php echo $cy; ?>" r="<?php echo $ptR; ?>"/>
                        <text class="llm-point-letter" x="<?php echo $cx; ?>" y="<?php echo round($cy + $ptR * 0.29, 1); ?>" text-anchor="middle" style="font-size:<?php echo $ptFont; ?>px"><?php echo $esc(mb_substr($pm['org'] ?? '?', 0, 1)); ?></text>
<?php if ($slug !== ''): ?>
                        <image class="llm-point-icon" x="<?php echo round($cx - $ptIcon / 2, 1); ?>" y="<?php echo round($cy - $ptIcon / 2, 1); ?>" width="<?php echo $ptIcon; ?>" height="<?php echo $ptIcon; ?>" href="<?php echo $esc(asset_url_auto(LLM_ICON_BASE . $slug . '.svg')); ?>" onload="this.previousElementSibling.style.visibility='hidden'" onerror="this.remove()"/>
<?php endif; ?>
                        <title><?php echo $esc(($pm['display_name'] ?? $pm['id']) . $speedText . $tierText); ?></title>
                    </a>
<?php endforeach; ?>
                </svg>
            </div>
            <div class="llm-pareto-legend"><?php if ($paretoFrontier !== []): ?><span class="llm-front-tag"><?php echo $ui($chartAxis === 'speed' ? 'frontier_speed' : 'frontier'); ?></span><?php endif; ?><span class="llm-bubble-tag"><?php echo $esc(__($chartAxis === 'speed' ? 'llm.ui.bubble_price' : 'llm.js.bubble_speed')); ?><?php if ($bubbleLegend !== null): ?> <small><?php echo $esc($chartAxis === 'speed' ? $fmtPrice($bubbleLegend['lo']) . '–' . $fmtPrice($bubbleLegend['hi']) : round($bubbleLegend['lo']) . '–' . round($bubbleLegend['hi']) . '/s'); ?></small><?php endif; ?></span></div>
            <div class="llm-chart-info-grid">
                <div class="llm-chart-note">
                    <h3 class="llm-chart-note-title"><?php echo $esc($ui('chart_reading')); ?></h3>
                    <p><?php echo $esc($ui($chartAxis === 'speed' ? 'chart_help_speed' : 'chart_help')); ?></p>
                    <?php /* 前沿线只在价格视图画，读图说明也必须跟着消失 —— 解释一条不存在的线比不解释更糟 */ ?>
<?php if ($paretoFrontier !== []): ?>
                    <p><?php echo $esc($ui($chartAxis === 'speed' ? 'frontier_help_speed' : 'frontier_help')); ?></p>
<?php endif; ?>
                    <p class="llm-pareto-note"><?php echo $esc($ui($chartAxis === 'speed' ? 'chart_note_speed' : 'chart_note')); ?></p>
                </div>
                <div class="llm-chart-note">
                    <h3 class="llm-chart-note-title"><?php echo $esc(__('llm.ui.method')); ?></h3>
                    <p><?php echo $esc(__('llm.ui.method_body')); ?> <a class="llm-method-link" href="#llm-sources"><?php echo $esc($llm_seo('llm.sources_h2', '数据来源')); ?></a></p>
                </div>
                <details class="llm-chart-list-details"><summary><?php echo $ui('chart_list'); ?></summary><ol class="llm-pareto-list">
<?php foreach (llm_board_plottable($ssrModels, $chartAxis) as $pm): ?>
            <li class="llm-pareto-row"><a class="llm-chart-select" href="/llm-model.php?id=<?php echo $esc(rawurlencode($pm['id'])); ?>&amp;lang=<?php echo $esc($currentLang); ?>"><span class="llm-pareto-name"><?php echo $esc($pm['display_name'] ?? $pm['id']); ?></span><span class="llm-pareto-mval"><strong><?php echo $esc(number_format((float)$pm[$paretoMetric], 1)); ?></strong><small><?php echo $esc($fmtX($chartAxis === 'price' ? $pm['price_out'] : $pm['aa_speed'])); ?></small></span></a></li>
<?php endforeach; ?>
<?php if ($visibleChartIds === []): ?><li class="llm-error"><?php echo $esc(__('llm.js.no_matches')); ?></li><?php endif; ?>
                </ol></details>
            </div>
            <div id="llm-chart-detail" class="llm-chart-detail" aria-live="polite" hidden></div>
<?php endif; ?>
</section>
