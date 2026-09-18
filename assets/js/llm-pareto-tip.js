/* Responsive SVG chart with zoom/pan and an on-demand, touch-accessible details panel. */
(function () {
    'use strict';
    var ui = window.LLMUI, data = window.LLMData;
    var svg = document.getElementById('llm-chart');
    if (!ui || !data || !svg) return;
    var section = document.querySelector('.llm-pareto');
    var wrap = document.querySelector('.llm-pareto-scroll');
    var list = document.querySelector('.llm-pareto-list');
    var detail = document.getElementById('llm-chart-detail');
    var expand = document.getElementById('llm-chart-expand');
    var legend = document.querySelector('.llm-pareto-legend');
    /* 横轴口径由服务端定（window.LLM_CHART_AXIS）：price = 对数价格轴，speed = 线性速度轴。
       SSR 与这里的重绘必须同轴、同点集，否则同一次加载会先出 SSR 的图、再被这里换成另一张。 */
    var AXIS = window.LLM_CHART_AXIS === 'speed' ? 'speed' : 'price';
    var isSpeed = AXIS === 'speed';
    var all = (isSpeed ? data.speeded : data.priced)(window.LLM_BOARD.models || []);
    /* 前沿 = 「没有替代品同时更好」。两个轴各有一条，语义同构：
         价格轴 → 没有谁同时更便宜且更强；速度轴 → 没有谁同时更快且更强。
       必须与 PHP 的 llm_board_frontier($models,$axis) 同口径，否则 SSR 一条、重绘另一条。 */
    var front = data.frontier(all, AXIS);
    var primary = data.primaryMetric ? data.primaryMetric(window.LLM_BOARD.models || []) : 'arena_score';
    /* 气泡半径由服务端算好（见 llm-leaderboard.php 的 $bubbleR）：SSR 与这里必须同形，
       所以只读同一份数字，不在这边重算公式。缺值时回落到 14（落在 10.5–16.5 的中位附近）。 */
    var bubbleR = (window.LLM_CHART_BUBBLE || {}).r || {};
    function pointRadius(model) {
        var r = Number(bubbleR[model.id]);
        return Number.isFinite(r) && r > 0 ? r : 14;
    }
    /* 推理档位分数。必须与 PHP 的 llm_board_tier_scores() 完全一致（含排序），
       否则 SSR 与 JS 重绘会画出两条不同的竖线。
       只在价格视图用：那边一列档位共享同一个横坐标，竖线本身就是要说的事实。
       速度视图不画档位 —— 实测档位间速度差中位数只占图宽 4%，连线几乎全是竖的，
       既不多说什么，又在气泡之间多出一层说不清的细线。
       另外只在纵轴是智能指数时可画：aa_variants 里只有 intel/coding，没有 Arena 分。 */
    function tierScores(model, primary) {
        // 注意 primary 是字段名（'aa_intelligence' / 'arena_score'），不是 'intel' 这种别名
        if (primary !== 'aa_intelligence') return [];
        var rows = Array.isArray(model.aa_variants) ? model.aa_variants : [];
        var scores = [];
        rows.forEach(function (v) {
            if (!v || !Number.isFinite(Number(v.intel))) return;
            scores.push(Number(v.intel));
        });
        scores.sort(function (a, b) { return a - b; });
        return scores;
    }
    /* 画不画得出来：至少两个档位分且分数有跨度（同分连出来是零长度线段，等于没画）。 */
    function tierDrawable(scores) {
        return scores.length >= 2 && Math.max.apply(Math, scores) > Math.min.apply(Math, scores);
    }
    var frontIds = new Set(front.map(function (m) { return m.id; }));
    var selected = null, points = [], visible = [], lastWidth = 0, returnFocus = null;
    var forcedLabels = [];   /* 强制标注的元素（搜索命中 / 正打开详情的那个），重绘时清空 */
    var pinned = false;
    var t = ui.t, esc = ui.esc;
    var base = null, view = null;
    var zoomAxis = 'both';
    var suppressClickUntil = 0;
    svg.setAttribute('tabindex', '0');
    svg.setAttribute('aria-label', t('chart_controls'));
    var caption = document.createElement('p');
    caption.className = 'llm-axis-caption';
    caption.textContent = t(isSpeed ? 'chart_x_speed' : 'chart_x');
    wrap.after(caption);
    var controls = document.createElement('div');
    controls.className = 'llm-chart-controls';
    controls.setAttribute('role', 'group');
    controls.setAttribute('aria-label', t('chart_controls'));
    controls.innerHTML = '<label class="llm-chart-axis"><span>' + esc(t('zoom_axis')) + '</span><select aria-label="' + esc(t('zoom_axis')) + '"><option value="both">' + esc(t('zoom_both')) + '</option><option value="x">' + esc(t(isSpeed ? 'zoom_x_speed' : 'zoom_x')) + '</option><option value="y">' + esc(t('zoom_y')) + '</option></select></label>'
        + '<div class="llm-chart-zoom-buttons"><button type="button" data-chart-zoom="in" aria-label="' + esc(t('zoom_in')) + '">+</button><button type="button" data-chart-zoom="out" aria-label="' + esc(t('zoom_out')) + '">−</button><button type="button" data-chart-zoom="reset">' + esc(t('zoom_reset')) + '</button><button type="button" data-chart-touch aria-pressed="false">' + esc(t('touch_explore')) + '</button></div>'
        + '<p class="llm-chart-status" aria-live="polite"></p>';
    wrap.before(controls);
    var touchHint = document.createElement('p');
    touchHint.className = 'llm-touch-hint';
    touchHint.textContent = t('touch_hint');
    controls.after(touchHint);
    expand.hidden = false;
    if (legend) {
        var hint = document.createElement('span');
        hint.className = 'llm-zoom-hint';
        hint.textContent = t('zoom_hint');
        legend.appendChild(hint);
    }

    function resetDetail() {
        selected = null; pinned = false;
        detail.hidden = true;
        detail.innerHTML = '';
        highlight();
    }
    function closeDetail() {
        var previous = selected;
        resetDetail();
        var point = points.find(function (p) { return p.m.id === previous; });
        var focusTarget = returnFocus || (point && point.el);
        returnFocus = null;
        if (focusTarget && focusTarget.focus) focusTarget.focus({ preventScroll: true });
    }
/* 「点开详情的那个模型」也必须有名有姓：标签预算与避让都是尽力而为，
   而用户此刻正盯着它。搜索命中那一路在 draw() 里已经强制标注，这里补点击/悬浮这一路。 */
function ensureLabel(id) {
    var p = null;
    for (var i = 0; i < points.length; i++) { if (points[i].m && points[i].m.id === id) { p = points[i]; break; } }
    if (!p || !p.inView) return;
    var group = svg.querySelector('g');
    if (!group) return;
    var safe = (window.CSS && window.CSS.escape) ? window.CSS.escape(id) : id;
    if (group.querySelector('[data-label-for="' + safe + '"]')) return;
    var L2 = layout();
    var chars = Array.from(p.m.display_name || p.m.id);
    var text = chars.length > 24 ? chars.slice(0, 23).join('') + '…' : chars.join('');
    var width = Array.from(text).reduce(function (sum, c) { return sum + (c.charCodeAt(0) > 255 ? 11 : 6); }, 0);
    var lx = Math.max(L2.left, Math.min(p.x + pointRadius(p.m) + 6, L2.width - L2.right - width));
    var el = document.createElementNS('http://www.w3.org/2000/svg', 'text');
    el.setAttribute('class', 'llm-pareto-label is-forced');
    el.setAttribute('data-label-for', id);
    el.setAttribute('x', lx);
    el.setAttribute('y', p.y + 4);
    el.textContent = text;
    group.appendChild(el);
    forcedLabels.push(el);
    while (forcedLabels.length > 4) { var old = forcedLabels.shift(); if (old && old.parentNode) old.parentNode.removeChild(old); }
}

    function highlight() {
        section.querySelectorAll('[data-model]').forEach(function (el) {
            var active = el.dataset.model === selected;
            el.classList.toggle('is-selected', active);
            if (el.matches('button, [role="button"]')) el.setAttribute('aria-pressed', String(active));
        });
    }
    function show(m, focusDetail) {
        selected = m.id;
        if (focusDetail && !detail.contains(document.activeElement)) returnFocus = document.activeElement;
        detail.hidden = false;
        detail.innerHTML = '<div class="llm-detail-title">' + ui.iconHtml(m)
            + '<div><strong>' + esc(m.display_name || m.id) + '</strong><small>' + esc(m.org || '') + ' · ' + esc(ui.weightText(m)) + '</small></div>'
            + '<button type="button" class="llm-detail-close" aria-label="' + esc(t('close_detail')) + '">×</button></div>'
            + '<dl class="llm-detail-metrics"><div><dt>' + esc(t('score')) + '</dt><dd>' + Number(m[primary]).toFixed(1) + '</dd></div>'
            + '<div><dt>' + esc(t('price_in')) + '</dt><dd>' + esc(ui.price(m.price_in)) + '</dd></div>'
            + '<div><dt>' + esc(t('price_out')) + '</dt><dd>' + esc(ui.price(m.price_out)) + '</dd></div>'
            + (isSpeed && Number.isFinite(Number(m.aa_speed)) ? '<div><dt>' + esc(t('speed')) + '</dt><dd>' + Math.round(Number(m.aa_speed)) + '/s</dd></div>' : '')
            + '<div><dt>' + esc(t('ratio')) + '</dt><dd>' + (Number(m[primary]) / Number(m.price_out)).toLocaleString(undefined, { maximumFractionDigits: 0 }) + '</dd></div></dl>'
            + (frontIds.has(m.id) ? '<span class="llm-front-tag">' + esc(t(isSpeed ? 'frontier_speed' : 'frontier')) + '</span>' : '')
            + '<a class="llm-detail-link" href="' + esc(ui.modelUrl(m)) + '">' + esc(t('detail')) + ' <span aria-hidden="true">↗</span></a>';
        ui.bindIcons(detail);
        detail.querySelector('.llm-detail-close').addEventListener('click', closeDetail);
        highlight();
        ensureLabel(m.id);   /* 正在看的那一个，名字必须出现 */
        if (focusDetail) {
            detail.querySelector('.llm-detail-link').focus({ preventScroll: true });
            detail.scrollIntoView({ block: 'nearest', behavior: 'instant' });
        }
    }
    function choose(candidates, trigger) {
        pinned = true;
        if (candidates.length === 1) { show(candidates[0].m, true); return; }
        selected = null;
        returnFocus = trigger || document.activeElement;
        detail.hidden = false;
        detail.innerHTML = '<div class="llm-detail-title"><p>' + esc(t('overlap', { count: candidates.length })) + '</p><button type="button" class="llm-detail-close" aria-label="' + esc(t('close_detail')) + '">×</button></div><div class="llm-detail-options">'
            + candidates.map(function (p, i) { return '<button type="button" data-choice="' + i + '">' + ui.iconHtml(p.m) + '<span>' + esc(p.m.display_name || p.m.id) + '</span></button>'; }).join('') + '</div>';
        ui.bindIcons(detail);
        detail.querySelector('.llm-detail-close').addEventListener('click', closeDetail);
        detail.querySelectorAll('[data-choice]').forEach(function (button) {
            button.addEventListener('click', function () { show(candidates[Number(button.dataset.choice)].m, true); });
        });
        detail.querySelector('[data-choice]').focus({ preventScroll: true });
        detail.scrollIntoView({ block: 'nearest', behavior: 'instant' });
    }
    function renderList() {
        list.innerHTML = visible.length ? visible.map(function (m) {
            return '<li class="llm-pareto-row' + (frontIds.has(m.id) ? ' is-front' : '') + '"><button type="button" class="llm-chart-select" data-model="' + esc(m.id) + '">'
                + ui.iconHtml(m) + '<span class="llm-pareto-name">' + esc(m.display_name || m.id)
                + (frontIds.has(m.id) ? '<small class="llm-front-tag">' + esc(t(isSpeed ? 'frontier_speed' : 'frontier')) + '</small>' : '') + '</span>'
                + '<span class="llm-pareto-mval"><strong>' + Number(m[primary]).toFixed(1) + '</strong><small>' + esc(isSpeed ? Math.round(Number(m.aa_speed)) + '/s' : ui.price(m.price_out)) + '</small></span></button></li>';
        }).join('') : '<li class="llm-error">' + esc(t('no_matches')) + '</li>';
        ui.bindIcons(list);
        list.querySelectorAll('button').forEach(function (button) {
            button.addEventListener('click', function () {
                pinned = true;
                show(visible.find(function (m) { return m.id === button.dataset.model; }), true);
            });
        });
    }
    function layout() {
        var width = Math.max(240, Math.floor(wrap.clientWidth));
        var isMobile = window.matchMedia('(max-width: 600px)').matches;
        var expanded = section.classList.contains('is-expanded');
        var height = isMobile ? 300 : (expanded ? Math.min(760, Math.max(560, Math.round(width * .62))) : Math.min(520, Math.round(width * .59)));
        return { width: width, height: height, isMobile: isMobile, left: isMobile ? 46 : 54, right: 26, top: 42, bottom: 36,
                 iw: width - (isMobile ? 46 : 54) - 26, ih: height - 42 - 36 };
    }
    /* 横轴原始值（z 轴本身，未变换）：价格取 log10（跨数量级），速度取原值（可比）。 */
    function xValue(m) { return isSpeed ? Number(m.aa_speed) : Math.log10(Number(m.price_out)); }
    function baseDomain() {
        if (!all.length) return { minX: -1, maxX: 1, minY: 0, maxY: 100 };
        var scores = all.map(function (m) { return Number(m[primary]); });
        /* 与 PHP 的 $minS 同公式、同样不许为负 —— 见 llm-board-views.php 的注释 */
        var minY = Math.max(0, Math.floor((Math.min.apply(null, scores) - 10) / 10) * 10);
        var maxY = Math.ceil((Math.max.apply(null, scores) + 10) / 10) * 10;
        var xs = all.map(xValue);
        var loX = Math.min.apply(null, xs), hiX = Math.max.apply(null, xs);
        var minX, maxX;
        if (isSpeed) {
            /* 速度轴：线性，两端各留一点余量并取整到 10，与 SSR 的算法一致。
               下界不取负 —— 输出速度没有负值，坐标轴上出现 -10 是没意义的刻度。 */
            var pad = Math.max(4, (hiX - loX) * .08);
            minX = Math.max(0, Math.floor((loX - pad) / 10) * 10);
            maxX = Math.ceil((hiX + pad) / 10) * 10;
        } else {
            var padding = Math.max(.15, (hiX - loX) * .06);
            minX = loX - padding; maxX = hiX + padding;
        }
        return { minX: minX, maxX: maxX, minY: minY, maxY: maxY };
    }
    function clampView() {
        var rx = base.maxX - base.minX, ry = base.maxY - base.minY;
        var spanX = Math.min(rx, Math.max(view.maxX - view.minX, rx / 40));
        var spanY = Math.min(ry, Math.max(view.maxY - view.minY, ry / 40));
        var cx = Math.max(base.minX + spanX / 2, Math.min(base.maxX - spanX / 2, (view.minX + view.maxX) / 2));
        var cy = Math.max(base.minY + spanY / 2, Math.min(base.maxY - spanY / 2, (view.minY + view.maxY) / 2));
        view = { minX: cx - spanX / 2, maxX: cx + spanX / 2, minY: cy - spanY / 2, maxY: cy + spanY / 2 };
    }
    function zoomAt(cx, cy, factor, axis) {
        if (!view) return;
        axis = axis || zoomAxis;
        view = {
            minX: axis === 'y' ? view.minX : cx - (cx - view.minX) * factor,
            maxX: axis === 'y' ? view.maxX : cx + (view.maxX - cx) * factor,
            minY: axis === 'x' ? view.minY : cy - (cy - view.minY) * factor,
            maxY: axis === 'x' ? view.maxY : cy + (view.maxY - cy) * factor
        };
        clampView();
        draw();
    }
    function panBy(dx, dy) {
        if (!view) return;
        var L = layout();
        var rx = view.maxX - view.minX, ry = view.maxY - view.minY;
        view = {
            minX: view.minX - dx / L.iw * rx,
            maxX: view.maxX - dx / L.iw * rx,
            minY: view.minY + dy / L.ih * ry,
            maxY: view.maxY + dy / L.ih * ry
        };
        clampView();
        draw();
    }
    function resetView() {
        if (base) view = { minX: base.minX, maxX: base.maxX, minY: base.minY, maxY: base.maxY };
        draw();
    }
    function updateStatus() {
        if (!view) return;
        var inView = visible.filter(function (m) {
            var xv = xValue(m), score = Number(m[primary]);
            return xv >= view.minX && xv <= view.maxX && score >= view.minY && score <= view.maxY;
        }).length;
        var models = window.LLM_BOARD.models || [];
        var excluded = models.length - all.length;
        controls.querySelector('.llm-chart-status').innerHTML = '<strong>' + esc(t('chart_range')) + '</strong> ' + (isSpeed ? esc(t('chart_speed_range', { min: Math.round(view.minX), max: Math.round(view.maxX) })) : esc(t('chart_price_range', { min: ui.price(Math.pow(10, view.minX)), max: ui.price(Math.pow(10, view.maxX)) }))) + ' · ' + esc(t('chart_score_range', { min: Number(view.minY.toFixed(1)), max: Number(view.maxY.toFixed(1)) }))
            + '<span>' + esc(t('chart_visible', { visible: inView, eligible: visible.length })) + '</span>'
            + (excluded ? '<small>' + esc(t('chart_excluded', { count: excluded })) + '</small>' : '');
    }
    function draw() {
        if (section.hidden) return;
        var L = layout();
        lastWidth = L.width;
        if (!base) base = baseDomain();
        if (!view) view = { minX: base.minX, maxX: base.maxX, minY: base.minY, maxY: base.maxY };
        var minX = view.minX, maxX = view.maxX, minY = view.minY, maxY = view.maxY;
        function xv(value) { return L.left + (value - minX) / (maxX - minX) * L.iw; }
        function x(m) { return xv(xValue(m)); }
        function y(score) { return L.top + (maxY - score) / (maxY - minY) * L.ih; }
        svg.setAttribute('viewBox', '0 0 ' + L.width + ' ' + L.height);
        var html = '<text x="' + L.left + '" y="21" class="llm-pareto-axis">' + esc(t('chart_y')) + '</text>';
        var maxYIntervals = Math.max(3, Math.floor(L.ih / (L.isMobile ? 72 : 56)));
        function niceStep(range, count) {
            var raw = range / count, exponent = Math.pow(10, Math.floor(Math.log10(raw))), fraction = raw / exponent;
            return (fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 5 ? 5 : 10) * exponent;
        }
        function scoreTick(score, step) {
            var decimals = Math.max(0, Math.ceil(-Math.log10(step)));
            return Number(score.toFixed(decimals)).toLocaleString(undefined, { maximumFractionDigits: decimals });
        }
        var step = niceStep(maxY - minY, maxYIntervals), firstScore = Math.ceil(minY / step) * step;
        for (var score = firstScore, scoreIndex = 0; score <= maxY + step * .001 && scoreIndex <= maxYIntervals + 2; score += step, scoreIndex++) {
            var cy = y(score);
            html += '<line x1="' + L.left + '" y1="' + cy + '" x2="' + (L.width - L.right) + '" y2="' + cy + '" class="llm-pareto-grid"/>'
                + '<text x="' + (L.left - 9) + '" y="' + (cy + 4) + '" text-anchor="end" class="llm-pareto-tick">' + scoreTick(score, step) + '</text>';
        }
        function priceTick(price) { return '$' + (price < .0001 ? price.toExponential(3) : price.toLocaleString(undefined, { maximumSignificantDigits: 4 })); }
        /* 刻度值始终以「原始横轴量」表示（价格写美元、速度写 tok/s），
           再由 tickX() 换算到像素 —— 这样两套刻度共用下面同一段去重/间距逻辑。 */
        var tickValues = [], xTickCount = Math.max(2, Math.floor(L.iw / 74));
        var tickX, tickLabel;
        if (isSpeed) {
            var speedStep = niceStep(maxX - minX, Math.max(3, Math.floor(L.iw / 96)));
            for (var speedTick = Math.ceil(minX / speedStep) * speedStep; speedTick <= maxX + 1e-9; speedTick += speedStep) {
                tickValues.push(speedTick);
            }
            tickX = function (speed) { return xv(speed); };
            tickLabel = function (speed) { return String(Math.round(speed)); };
        } else {
            if (maxX - minX >= .7) {
                for (var power = Math.floor(minX); power <= Math.ceil(maxX); power++) {
                    [1, 2, 5].forEach(function (multiplier) { tickValues.push(Math.pow(10, power) * multiplier); });
                }
            } else {
                var lowPrice = Math.pow(10, minX), highPrice = Math.pow(10, maxX);
                var priceStep = niceStep(highPrice - lowPrice, xTickCount);
                for (var tickIndex = 0, firstPrice = Math.ceil(lowPrice / priceStep) * priceStep; tickIndex <= xTickCount + 2; tickIndex++) {
                    var tickPrice = firstPrice + priceStep * tickIndex;
                    if (tickPrice > highPrice) break;
                    tickValues.push(tickPrice);
                }
            }
            tickX = function (price) { return xv(Math.log10(price)); };
            tickLabel = priceTick;
        }
        var tickLabels = {}, lastTickX = -Infinity;
        tickValues.forEach(function (value) {
            var cx = tickX(value), tick = tickLabel(value);
            if (cx < L.left || cx > L.width - L.right || cx - lastTickX < 58 || tickLabels[tick]) return;
            tickLabels[tick] = true; lastTickX = cx;
            html += '<line x1="' + cx + '" y1="' + L.top + '" x2="' + cx + '" y2="' + (L.height - L.bottom) + '" class="llm-pareto-grid"/>'
                + '<text x="' + cx + '" y="' + (L.height - 13) + '" text-anchor="middle" class="llm-pareto-tick">' + esc(tick) + '</text>';
        });
        html += '<defs><clipPath id="llm-chart-plot-clip"><rect x="' + L.left + '" y="' + L.top + '" width="' + L.iw + '" height="' + L.ih + '"/></clipPath></defs><g clip-path="url(#llm-chart-plot-clip)">'
            + (front.length ? '<polyline points="' + front.map(function (m) { return x(m) + ',' + y(Number(m[primary])); }).join(' ') + '" class="llm-pareto-line"/>' : '');
        var visibleIds = new Set(visible.map(function (m) { return m.id; }));
        points = all.map(function (m) {
            var px = x(m), py = y(Number(m[primary]));
            return { m: m, x: px, y: py, visible: visibleIds.has(m.id), inView: px >= L.left && px <= L.width - L.right && py >= L.top && py <= L.height - L.bottom };
        }).sort(function (a, b) { return Number(a.visible) - Number(b.visible); });
        var labels = [];
        points.forEach(function (p, i) {
            var m = p.m, icon = ui.icon(m);
            if (!p.inView) return;
            var r = pointRadius(m), iconSize = Math.round(r * 1.43), fontSize = Math.round(r * 0.79 * 10) / 10;
            var speed = Number(m.aa_speed);
            /* 两个视图都不再画档位线，所以这边的档位区间是唯一的出口 —— 不能只在价格视图给 */
            var tiers = tierScores(m, primary);
            var tierText = '';
            if (tierDrawable(tiers)) {
                tierText = ' · ' + t('effort_range') + ' ' + Math.min.apply(Math, tiers).toFixed(1) + '–' + Math.max.apply(Math, tiers).toFixed(1);
            }
            var speedText = Number.isFinite(speed) && speed > 0 ? ' · ' + t('speed') + ' ' + Math.round(speed) + '/s' : '';
            html += '<g class="llm-pareto-pt' + (frontIds.has(m.id) ? ' is-front' : '') + (!p.visible ? ' is-muted' : '') + '" data-point="' + i + '" data-model="' + esc(m.id) + '"'
                + (p.visible ? ' role="button" tabindex="0" aria-label="' + esc((m.display_name || m.id) + ' · ' + t('chart_y') + ' ' + Number(m[primary]).toFixed(1) + ' · ' + ui.price(m.price_out) + speedText + tierText) + '"' : ' aria-hidden="true"') + '>'
                + '<title>' + esc((m.display_name || m.id) + ' · ' + t('score') + ': ' + Number(m[primary]).toFixed(1) + ' · ' + t('price_out') + ': ' + ui.price(m.price_out) + speedText + tierText) + '</title>'
                + '<circle class="llm-point-bg" cx="' + p.x + '" cy="' + p.y + '" r="' + r + '"/>'
                + '<text class="llm-point-letter" x="' + p.x + '" y="' + (p.y + r * 0.29) + '" text-anchor="middle" style="font-size:' + fontSize + 'px">' + esc(Array.from(m.org || m.display_name || '?')[0]) + '</text>'
                + (icon ? '<image class="llm-point-icon" x="' + (p.x - iconSize / 2) + '" y="' + (p.y - iconSize / 2) + '" width="' + iconSize + '" height="' + iconSize + '" href="' + esc(icon) + '"/>' : '') + '</g>';
            if (p.visible && p.x >= L.left && p.x <= L.width - L.right && p.y >= L.top && p.y <= L.height - L.bottom) labels.push(p);
        });
        /* Labels are only added when their estimated boxes can remain distinct. */
        labels.sort(function (a, b) { return Number(frontIds.has(b.m.id)) - Number(frontIds.has(a.m.id)); });
        var placed = [];
        var zoomLevel = Math.max((base.maxX - base.minX) / (maxX - minX), (base.maxY - base.minY) / (maxY - minY));
        var labelLimit = Math.max(3, Math.floor(L.iw / (L.isMobile ? 96 : 72) * Math.min(3, 1 + Math.log2(zoomLevel) * .5)));
        /* 用户在搜索框里找的模型必须无条件标注：预算和避让都是"尽力而为"，
           对用户明确在找的那一个不能省 —— 否则就成了「图上明明有它，却看不见它」。 */
        var query = String(((ui.state || {}).q) || '').trim().toLowerCase();
        function matchesQuery(m) {
            if (query === '') return false;
            return String(m.display_name || '').toLowerCase().indexOf(query) >= 0
                || String(m.id || '').toLowerCase().indexOf(query) >= 0;
        }
        /* 标签不只躲别的标签，也要躲别的气泡 —— 按那个气泡自己的半径判定，
           而不是写死一个 18：半径收小后，写死的距离会把标签推到离自己点很远的地方。 */
        function labelBlocked(box, self) {
            return points.some(function (q) {
                if (!q.inView || q === self) return false;
                var nx = Math.max(box.left, Math.min(q.x, box.right));
                var ny = Math.max(box.top, Math.min(q.y, box.bottom));
                return Math.hypot(q.x - nx, q.y - ny) < pointRadius(q.m) + 3;
            });
        }
        labels.forEach(function (p) {
            var forced = matchesQuery(p.m);
            if (!forced && placed.length >= labelLimit) return;
            var chars = Array.from(p.m.display_name || p.m.id), limit = zoomLevel > 1.5 ? 36 : 20;
            var text = chars.length > limit ? chars.slice(0, limit - 1).join('') + '…' : chars.join('');
            var width = Array.from(text).reduce(function (sum, c) { return sum + (c.charCodeAt(0) > 255 ? 11 : 6); }, 0);
            var gap = pointRadius(p.m) + 6;
            var candidates = [[p.x + gap, p.y + 4], [p.x - gap - width, p.y + 4], [p.x - width / 2, p.y - 22], [p.x - width / 2, p.y + 32]];
            var done = candidates.some(function (position) {
                var lx = position[0], ly = position[1];
                var box = { left: lx, right: lx + width, top: ly - 11, bottom: ly + 4 };
                if (box.left < L.left || box.right > L.width - L.right || box.top < L.top || box.bottom > L.height - L.bottom) return false;
                if (!forced) {
                    if (placed.some(function (b) { return box.left < b.right + 7 && box.right + 7 > b.left && box.top < b.bottom + 5 && box.bottom + 5 > b.top; })) return false;
                    if (labelBlocked(box, p)) return false;
                }
                placed.push(box);
                html += '<text class="llm-pareto-label' + (forced ? ' is-forced' : '') + '" x="' + lx + '" y="' + ly + '">' + esc(text) + '</text>';
                return true;
            });
            /* 强制标注却四个位置都放不下（贴边或被挤）：退回点右侧，允许轻微越界。
               对"用户正在找的那一个"，有名字比不越界重要。 */
            if (!done && forced) {
                var fx = Math.max(L.left, Math.min(p.x + gap, L.width - L.right - width));
                placed.push({ left: fx, right: fx + width, top: p.y - 7, bottom: p.y + 8 });
                html += '<text class="llm-pareto-label is-forced" x="' + fx + '" y="' + (p.y + 4) + '">' + esc(text) + '</text>';
            }
        });
        html += '</g>';
        svg.innerHTML = html;
        forcedLabels = [];   /* 上一轮的强制标注随 innerHTML 一起没了 */
        svg.querySelectorAll('[data-point]').forEach(function (el) {
            var p = points[Number(el.dataset.point)];
            p.el = el;
            var image = el.querySelector('image');
            if (image) {
                image.addEventListener('load', function () { el.querySelector('text').style.visibility = 'hidden'; });
                image.addEventListener('error', function () { image.remove(); el.querySelector('text').style.visibility = 'visible'; });
            }
        });
        highlight();
        updateStatus();
    }
    svg.addEventListener('click', function (e) {
        if (Date.now() < suppressClickUntil) { e.preventDefault(); e.stopPropagation(); return; }
        var rect = svg.getBoundingClientRect(), vb = svg.viewBox.baseVal;
        var x = (e.clientX - rect.left) * vb.width / rect.width;
        var y = (e.clientY - rect.top) * vb.height / rect.height;
        var close = points.filter(function (p) { return p.visible && p.inView && Math.hypot(p.x - x, p.y - y) <= 24; });
        close.sort(function (a, b) { return Math.hypot(a.x - x, a.y - y) - Math.hypot(b.x - x, b.y - y); });
        if (close.length) choose(close, e.target.closest('[data-point]'));
    });
    svg.addEventListener('keydown', function (e) {
        if (e.target === svg) {
            var panStep = .12;
            if (e.key === 'ArrowLeft') { e.preventDefault(); panBy(-layout().iw * panStep, 0); return; }
            if (e.key === 'ArrowRight') { e.preventDefault(); panBy(layout().iw * panStep, 0); return; }
            if (e.key === 'ArrowUp') { e.preventDefault(); panBy(0, -layout().ih * panStep); return; }
            if (e.key === 'ArrowDown') { e.preventDefault(); panBy(0, layout().ih * panStep); return; }
            if (e.key === '+' || e.key === '=') { e.preventDefault(); zoomAt((view.minX + view.maxX) / 2, (view.minY + view.maxY) / 2, 1 / 1.35); return; }
            if (e.key === '-' || e.key === '_') { e.preventDefault(); zoomAt((view.minX + view.maxX) / 2, (view.minY + view.maxY) / 2, 1.35); return; }
            if (e.key === 'Home') { e.preventDefault(); resetView(); return; }
        }
        var el = e.target.closest('[data-point]');
        if (!el || (e.key !== 'Enter' && e.key !== ' ')) return;
        e.preventDefault(); pinned = true;
        var point = points[Number(el.dataset.point)];
        var close = points.filter(function (p) { return p.visible && p.inView && Math.hypot(p.x - point.x, p.y - point.y) <= 24; });
        choose(close, el);
    });
    section.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDetail(); });
    expand.addEventListener('click', function () {
        var expanded = section.classList.toggle('is-expanded');
        expand.setAttribute('aria-expanded', String(expanded));
        expand.textContent = t(expanded ? 'collapse_chart' : 'expand_chart');
        draw();
    });
    controls.querySelector('select').addEventListener('change', function (e) { zoomAxis = e.target.value; });
    controls.querySelectorAll('[data-chart-zoom]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!view) return;
            var action = button.dataset.chartZoom;
            if (action === 'reset') { resetView(); return; }
            zoomAt((view.minX + view.maxX) / 2, (view.minY + view.maxY) / 2, action === 'in' ? 1 / 1.35 : 1.35);
        });
    });
    controls.querySelector('[data-chart-touch]').addEventListener('click', function (e) {
        var exploring = wrap.classList.toggle('is-touch-exploring');
        e.currentTarget.setAttribute('aria-pressed', String(exploring));
        e.currentTarget.textContent = t(exploring ? 'touch_explore_stop' : 'touch_explore');
    });
    var pointers = {}, dragStart = null, pinchStart = null, panMoved = 0;
    svg.addEventListener('wheel', function (e) {
        if (section.hidden || !view) return;
        e.preventDefault();
        var L = layout();
        var rect = svg.getBoundingClientRect();
        var px = (e.clientX - rect.left) * L.width / rect.width;
        var py = (e.clientY - rect.top) * L.height / rect.height;
        var rx = view.maxX - view.minX, ry = view.maxY - view.minY;
        var cx = view.minX + (px - L.left) / L.iw * rx;
        var cy = view.maxY - (py - L.top) / L.ih * ry;
        zoomAt(cx, cy, e.deltaY < 0 ? 1 / 1.2 : 1.2);
    }, { passive: false });
    svg.addEventListener('pointerdown', function (e) {
        if (e.button !== 0) return;
        if (e.pointerType === 'mouse') svg.setPointerCapture(e.pointerId);
        pointers[e.pointerId] = { x: e.clientX, y: e.clientY, type: e.pointerType };
        var ids = Object.keys(pointers);
        if (ids.length === 1) {
            dragStart = { x: e.clientX, y: e.clientY, px: 0, py: 0, type: e.pointerType, horizontal: e.pointerType !== 'touch' };
            pinchStart = null;
            panMoved = 0;
        } else if (ids.length === 2) {
            dragStart = null;
            var a = pointers[ids[0]], b = pointers[ids[1]];
            pinchStart = { d: Math.hypot(a.x - b.x, a.y - b.y), mx: (a.x + b.x) / 2, my: (a.y + b.y) / 2 };
        }
    });
    svg.addEventListener('pointermove', function (e) {
        if (!(e.pointerId in pointers)) return;
        pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
        var ids = Object.keys(pointers);
        if (ids.length === 2 && pinchStart) {
            var a = pointers[ids[0]], b = pointers[ids[1]];
            var d = Math.hypot(a.x - b.x, a.y - b.y);
            var mx = (a.x + b.x) / 2, my = (a.y + b.y) / 2;
            var factor = pinchStart.d ? d / pinchStart.d : 1;
            if (factor <= .99 || factor >= 1.01) {
                panMoved = 7;
                var L = layout();
                var rect = svg.getBoundingClientRect();
                var px = (mx - rect.left) * L.width / rect.width;
                var py = (my - rect.top) * L.height / rect.height;
                var rx = view.maxX - view.minX, ry = view.maxY - view.minY;
                var cx = view.minX + (px - L.left) / L.iw * rx;
                var cy = view.maxY - (py - L.top) / L.ih * ry;
                zoomAt(cx, cy, 1 / factor);
            }
            var panX = mx - pinchStart.mx, panY = my - pinchStart.my;
            if (Math.abs(panX) > 1 || Math.abs(panY) > 1) { panMoved = 7; panBy(panX, panY); }
            pinchStart.d = d; pinchStart.mx = mx; pinchStart.my = my;
        } else if (dragStart) {
            var dx = e.clientX - dragStart.x, dy = e.clientY - dragStart.y;
            if (dragStart.type === 'touch' && !wrap.classList.contains('is-touch-exploring') && !dragStart.horizontal && Math.abs(dx) + Math.abs(dy) > 7) {
                dragStart.horizontal = Math.abs(dx) > Math.abs(dy);
            }
            if (dragStart.type === 'touch' && !wrap.classList.contains('is-touch-exploring') && !dragStart.horizontal) return;
            if (Math.hypot(dx, dy) > 4) panMoved = Math.max(panMoved, Math.hypot(dx, dy));
            if (panMoved > 4) panBy(dx - dragStart.px, dragStart.type === 'touch' && !wrap.classList.contains('is-touch-exploring') ? 0 : dy - dragStart.py);
            dragStart.px = dx; dragStart.py = dy;
        }
    });
    function endPointer(e) {
        delete pointers[e.pointerId];
        if (svg.hasPointerCapture && svg.hasPointerCapture(e.pointerId)) svg.releasePointerCapture(e.pointerId);
        var ids = Object.keys(pointers);
        if (ids.length < 2) pinchStart = null;
        if (ids.length === 0) {
            if (panMoved > 6) suppressClickUntil = Date.now() + 450;
            dragStart = null; panMoved = 0;
        }
    }
    svg.addEventListener('pointerup', endPointer);
    svg.addEventListener('pointercancel', endPointer);
    svg.addEventListener('dblclick', function () { resetView(); });
    function refresh() {
        /* 可见集合必须用与 all 同一个口径：速度轴上「没有价格」不影响该不该画，
           这里若仍按 priced 过滤，缺报价的模型会被误判成「被筛掉」而变灰。 */
        visible = (isSpeed ? data.speeded : data.priced)(ui.visible);
        if (!visible.some(function (m) { return m.id === selected; })) resetDetail();
        base = null; view = null;
        renderList(); draw();
    }
    document.addEventListener('llm:change', refresh);
    if (window.ResizeObserver) {
        new ResizeObserver(function () { if (!section.hidden && Math.floor(wrap.clientWidth) !== lastWidth) draw(); }).observe(wrap);
    } else window.addEventListener('resize', draw);
    refresh();
})();
