/* Shared data rules mirror includes/llm-board-data.php; no third-party requests. */
(function (root) {
    'use strict';
    var NUMERIC_RE = /^[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?$/i;
    // 推理强度档位白名单（镜像 includes/llm-board-data.php 的 llm_board_effort_labels）
    var EFFORT_KEYS = ['max', 'xhigh', 'high', 'medium', 'low', 'non-reasoning', 'reasoning', 'adaptive', 'thinking'];
    // 排序键白名单与方向，镜像 includes/llm-board-data.php 的 llm_board_state / llm_board_sort_direction
    var SORT_KEYS = ['intel', 'score', 'arena', 'speed', 'ttft', 'pin', 'pout', 'price', 'value', 'new'];
    var SORT_ALIAS = { score: 'arena', price: 'pout' };
    var SORT_DIRECTION = { ttft: 'asc', pin: 'asc', pout: 'asc', price: 'asc' };
    /* 图表视图有两个：pareto（价格轴）和 speed（速度轴），口径必须与 PHP 的 $isChartView 一致。
       JS 这边原来处处写死 'pareto'，后果很隐蔽：readState 把 ?view=speed 归成 'all'，
       紧接着 render() 按 'all' 把图表区块 hidden 掉、把排行榜表格放出来 ——
       用户点「速度与评分图」，看到标题写着「大模型速度图」，底下却是排行榜表格。
       SSR 是对的，坏就坏在首帧之后 JS 又改了回去，所以只查 HTML 的测试完全看不出来。 */
    var CHART_VIEWS = ['pareto', 'speed'];
    function isChartView(view) { return CHART_VIEWS.indexOf(view) >= 0; }
    function numericValue(v) {
        var numeric = typeof v === 'number' || (typeof v === 'string' && NUMERIC_RE.test(v.trim()));
        return numeric && Number.isFinite(Number(v)) && Number(v) >= 0 ? Number(v) : null;
    }
    function normalize(models) {
        return models.filter(function (m) { return m && typeof m.id === 'string' && m.id !== ''; }).map(function (m) {
            var clean = Object.assign({}, m);
            // arena_ci_low/high 镜像 includes/llm-board-data.php：LMArena 评分置信区间，只用于列内 ± 徽标
            ['arena_score', 'arena_ci_low', 'arena_ci_high', 'aa_intelligence', 'aa_coding', 'aa_speed', 'aa_ttft', 'price_in', 'price_out', 'listed_at'].forEach(function (key) {
                clean[key] = numericValue(m[key]);
            });
            return clean;
        });
    }
    function scoreCompare(a, b) {
        return Number(b.arena_score) - Number(a.arena_score) || (a.id < b.id ? -1 : a.id > b.id ? 1 : 0);
    }
    function intelCompare(a, b) {
        if (a.aa_intelligence == null && b.aa_intelligence == null) return scoreCompare(a, b);
        if (a.aa_intelligence == null) return 1;
        if (b.aa_intelligence == null) return -1;
        if (Number(b.aa_intelligence) !== Number(a.aa_intelligence)) return Number(b.aa_intelligence) - Number(a.aa_intelligence);
        if (a.arena_score == null && b.arena_score == null) return a.id < b.id ? -1 : a.id > b.id ? 1 : 0;
        if (a.arena_score == null) return 1;
        if (b.arena_score == null) return -1;
        return scoreCompare(a, b);
    }
    function readState(url) {
        var p = new URL(url, root.location.origin).searchParams;
        var legacy = p.get('view') || 'all';
        var weights = p.get('weights') || (legacy === 'open' ? 'open' : 'all');
        var sort = p.get('sort') || (legacy === 'cheap' ? 'price' : legacy === 'new' ? 'new' : 'intel');
        return {
            view: isChartView(legacy) ? legacy : 'all',
            weights: ['all', 'open', 'closed', 'unknown'].indexOf(weights) >= 0 ? weights : 'all',
            sort: SORT_KEYS.indexOf(sort) >= 0 ? sort : 'intel',
            q: Array.from((p.get('q') || '').trim()).slice(0, 120).join(''),
            // 显示口径：model = 一个模型一行（默认，镜像 llm_board_state）；
            // tier = 每个推理档位一行（「按档位」次要视图）
            group: ['tier', 'model'].indexOf(p.get('group')) >= 0 ? p.get('group') : 'model'
        };
    }
    // 评分/价格比 = Arena 分 ÷ 输出价，镜像 llm_board_value_score（免费或缺失算不出比值）
    function valueScore(row) {
        if (row.arena_score == null || row.price_out == null) return null;
        var score = Number(row.arena_score), price = Number(row.price_out);
        if (!isFinite(score) || !isFinite(price) || price <= 0) return null;
        return score / price;
    }
    // 镜像 llm_board_sort_value：value_score 现算，其余读字段，非数字/负数按缺失处理
    function sortValue(row, field) {
        if (field === 'value_score') return valueScore(row);
        var value = row[field];
        return value == null || !isFinite(Number(value)) || Number(value) < 0 ? null : Number(value);
    }
    // 镜像 llm_board_column_compare：缺失沉底，同值返回 0 交给兜底比较器
    function columnCompare(a, b, field, direction) {
        var av = sortValue(a, field), bv = sortValue(b, field);
        if (av == null && bv == null) return 0;
        if (av == null) return 1;
        if (bv == null) return -1;
        if (av === bv) return 0;
        return direction === 'asc' ? (av < bv ? -1 : 1) : (bv < av ? -1 : 1);
    }
    // 镜像 llm_board_column_sort_compare：只有速度/延迟/输入价/评分价格比走列排序
    var COLUMN_SORTS = { speed: ['aa_speed', 'desc'], ttft: ['aa_ttft', 'asc'], pin: ['price_in', 'asc'], value: ['value_score', 'desc'] };
    function columnSortCompare(a, b, key) {
        var column = COLUMN_SORTS[key];
        return column ? columnCompare(a, b, column[0], column[1]) : 0;
    }
    function filter(models, state) {
        var q = state.q.toLowerCase();
        return normalize(models).filter(function (m) {
            if (m.arena_score == null && m.aa_intelligence == null) return false;
            if (state.weights === 'open' && m.open_weights !== true) return false;
            if (state.weights === 'closed' && m.open_weights !== false) return false;
            if (state.weights === 'unknown' && m.open_weights != null) return false;
            return !q || ((m.display_name || m.id) + ' ' + (m.org || '')).toLowerCase().indexOf(q) !== -1;
        }).sort(function (a, b) {
            if (state.sort === 'score' || state.sort === 'arena') return scoreCompare(a, b);
            if (state.sort === 'price' || state.sort === 'pout') {
                var ap = a.price_out == null ? Infinity : Number(a.price_out);
                var bp = b.price_out == null ? Infinity : Number(b.price_out);
                if (ap !== bp) return ap - bp;
            }
            if (state.sort === 'new' && (a.listed_at || 0) !== (b.listed_at || 0)) {
                return (b.listed_at || 0) - (a.listed_at || 0);
            }
            // 列头排序：同值（或都缺失）返回 0，继续走智能指数兜底，与 PHP 同序
            var column = columnSortCompare(a, b, state.sort);
            if (column) return column;
            return intelCompare(a, b);
        });
    }
    function primaryMetric(models) {
        var list = Array.isArray(models) ? models : [];
        for (var i = 0; i < list.length; i++) {
            var v = list[i] ? list[i].aa_intelligence : null;
            if (v != null && isFinite(Number(v))) return 'aa_intelligence';
        }
        return 'arena_score';
    }
    function metricCompare(a, b, metric) {
        var av = a[metric], bv = b[metric];
        if (av == null && bv == null) return a.id < b.id ? -1 : a.id > b.id ? 1 : 0;
        if (av == null) return 1;
        if (bv == null) return -1;
        return Number(bv) - Number(av) || (a.id < b.id ? -1 : a.id > b.id ? 1 : 0);
    }
    function priced(models) {
        var primary = primaryMetric(models);
        return normalize(models).filter(function (m) {
            return m[primary] != null && m.price_out != null && Number.isFinite(Number(m[primary]))
                && Number.isFinite(Number(m.price_out)) && Number(m.price_out) > 0;
        });
    }
    /* 速度轴上的可画模型：纵轴仍是主指标，横轴换成实测输出速度。
       镜像 includes/llm-board-data.php 的 llm_board_plottable($models, 'speed')：
       两边判定不一致，SSR 与 JS 重绘就会画出不同的点集。 */
    function speeded(models) {
        var primary = primaryMetric(models);
        return normalize(models).filter(function (m) {
            return m[primary] != null && m.aa_speed != null && Number.isFinite(Number(m[primary]))
                && Number.isFinite(Number(m.aa_speed)) && Number(m.aa_speed) > 0;
        });
    }
    /* 前沿 = 「没有替代品同时更好」，两个轴同构，镜像 PHP 的 llm_board_frontier($models,$axis)：
         price 轴 → 最小化输出价、最大化分数 → 按价格升序扫，取分数创新高处
         speed 轴 → 最大化速度、最大化分数 → 按速度降序扫，同样取分数创新高处
       两侧不同口径的话，SSR 画一条线、JS 又重绘成另一条。 */
    function frontier(models, axis) {
        var primary = primaryMetric(models);
        var isSpeed = axis === 'speed';
        var xKey = isSpeed ? 'aa_speed' : 'price_out';
        var pool = isSpeed ? speeded(models) : priced(models);
        var best = -Infinity, lastX = null;
        return pool.sort(function (a, b) {
            var d = Number(a[xKey]) - Number(b[xKey]);
            return (isSpeed ? -d : d) || metricCompare(a, b, primary);
        }).filter(function (m) {
            var s = Number(m[primary]), x = Number(m[xKey]);
            if (s > best || (s === best && x === lastX)) { best = s; lastX = x; return true; }
            return false;
        });
    }
    // 「模型 × 推理强度档位」展开：镜像 includes/llm-board-data.php 的 llm_board_variant_rows
    function variantRows(models) {
        var rows = [];
        normalize(models).forEach(function (m) {
            var current = (typeof m.aa_variant === 'string' && m.aa_variant !== '') ? m.aa_variant : null;
            var variants = [];
            (Array.isArray(m.aa_variants) ? m.aa_variants : []).forEach(function (v) {
                if (!v || typeof v !== 'object') return;
                var label = typeof v.label === 'string' ? v.label : '';
                if (!label || EFFORT_KEYS.indexOf(label) === -1) return;
                var row = { label: label };
                ['intel', 'coding', 'speed', 'ttft'].forEach(function (key) { row[key] = numericValue(v[key]); });
                if (row.intel == null && row.coding == null) return;
                variants.push(row);
            });
            if (variants.length === 0) {
                rows.push(Object.assign({}, m, { effort: null, effort_current: current, row_key: m.id }));
                return;
            }
            // 只有一档时不标强度：上游把 base slug 记成 max，单档模型标出来是误导（与 AA 一致）
            var labeled = variants.length > 1;
            variants.forEach(function (v) {
                var row = Object.assign({}, m, { effort_current: current, row_key: labeled ? m.id + '#' + v.label : m.id });
                // 多档位行只认本档位的实测值：没测就是空，不能继承 max 档的分数
                if (labeled || v.intel != null) row.aa_intelligence = v.intel;
                if (labeled || v.coding != null) row.aa_coding = v.coding;
                if (labeled || v.speed != null) row.aa_speed = v.speed;
                if (labeled || v.ttft != null) row.aa_ttft = v.ttft;
                row.aa_variant = labeled ? v.label : (current || v.label);
                row.effort = labeled ? v.label : null;
                rows.push(row);
            });
        });
        return rows;
    }
    function rowKeyCompare(a, b) {
        var ak = a.row_key == null ? a.id : a.row_key, bk = b.row_key == null ? b.id : b.row_key;
        return ak < bk ? -1 : ak > bk ? 1 : 0;
    }
    function rowMetricCompare(a, b, metric) {
        var av = a[metric], bv = b[metric];
        if (av == null && bv == null) return rowKeyCompare(a, b);
        if (av == null) return 1;
        if (bv == null) return -1;
        if (Number(av) !== Number(bv)) return Number(bv) - Number(av);
        if (metric !== 'aa_intelligence') {
            if (a.aa_intelligence == null && b.aa_intelligence != null) return 1;
            if (b.aa_intelligence == null && a.aa_intelligence != null) return -1;
            if (a.aa_intelligence != null && b.aa_intelligence != null && Number(a.aa_intelligence) !== Number(b.aa_intelligence)) {
                return Number(b.aa_intelligence) - Number(a.aa_intelligence);
            }
        }
        return rowKeyCompare(a, b);
    }
    // 旧表口径的兜底排序：智能指数降序（两边都没有则回落 Arena），再 Arena，最后 row_key
    function rowIntelCompare(a, b) {
        if (a.aa_intelligence == null && b.aa_intelligence == null) return rowMetricCompare(a, b, 'arena_score');
        if (a.aa_intelligence == null) return 1;
        if (b.aa_intelligence == null) return -1;
        if (Number(a.aa_intelligence) !== Number(b.aa_intelligence)) return Number(b.aa_intelligence) - Number(a.aa_intelligence);
        return rowMetricCompare(a, b, 'arena_score');
    }
    // 与 filter 同规则，但作用在「模型 × 档位」行上（榜表专用；图表与分享图仍用模型级 filter）
    function filterRows(models, state) {
        var q = state.q.toLowerCase();
        return variantRows(models).filter(function (m) {
            if (m.arena_score == null && m.aa_intelligence == null) return false;
            if (state.weights === 'open' && m.open_weights !== true) return false;
            if (state.weights === 'closed' && m.open_weights !== false) return false;
            if (state.weights === 'unknown' && m.open_weights != null) return false;
            return !q || ((m.display_name || m.id) + ' ' + (m.org || '') + ' ' + (m.effort || '')).toLowerCase().indexOf(q) !== -1;
        }).sort(function (a, b) {
            if (state.sort === 'score' || state.sort === 'arena') return rowMetricCompare(a, b, 'arena_score');
            if (state.sort === 'price' || state.sort === 'pout') {
                var ap = a.price_out == null ? Infinity : Number(a.price_out);
                var bp = b.price_out == null ? Infinity : Number(b.price_out);
                if (ap !== bp) return ap - bp;
            }
            if (state.sort === 'new' && (a.listed_at || 0) !== (b.listed_at || 0)) {
                return (b.listed_at || 0) - (a.listed_at || 0);
            }
            // 列头排序：同值（或都缺失）返回 0，继续走智能指数兜底，与 PHP 同序
            var column = columnSortCompare(a, b, state.sort);
            if (column) return column;
            return rowIntelCompare(a, b);
        });
    }
    function stateUrl(state, language) {
        var p = new URLSearchParams(), view = state.view;
        if (!isChartView(view)) {
            view = state.sort === 'price' ? 'cheap' : state.sort === 'new' ? 'new' : state.weights === 'open' ? 'open' : 'all';
        }
        if (view !== 'all') p.set('view', view);
        var defaultWeights = view === 'open' ? 'open' : 'all';
        var defaultSort = view === 'cheap' ? 'price' : view === 'new' ? 'new' : 'intel';
        if (state.weights !== defaultWeights) p.set('weights', state.weights);
        if (state.sort !== defaultSort) p.set('sort', state.sort);
        var group = state.group || 'model';
        if (group !== 'model') p.set('group', group);
        if (state.q) p.set('q', state.q);
        if (language && language !== 'zh-CN') p.set('lang', language);
        return '/llm-leaderboard.php' + (p.size ? '?' + p.toString() : '') + '#llm-views';
    }
    // 同族标记：镜像 includes/llm-board-data.php 的 llm_board_family_marks ——
    // 族内名次按当前顺序数，族色按模型 id 稳定（djb2，与 PHP 逐位一致），相邻多档族错色。
    // 放在 data 区（早返回之前）：Node 里的测试与页面共用同一份实现。
    var FAMILY_COLORS = 6;
    function familyHash(id) {
        var hash = 5381;
        for (var i = 0; i < id.length; i++) hash = (hash * 33 ^ id.charCodeAt(i)) >>> 0;
        return hash;
    }
    // 每族（同一模型）的行数：折叠视图要沿用展开时的档位数，族徽才写得对（镜像 llm_board_family_sizes）
    function familySizes(rows) {
        var sizes = {};
        rows.forEach(function (r) { sizes[r.id] = (sizes[r.id] || 0) + 1; });
        return sizes;
    }
    // 按模型折叠：同一个模型只留一行。传入的已是当前排序后的行，所以「第一次出现的那一行」就是
    // 当前列最优的那一档（缺失沉底，同值由 intel 兜底把更强的一档排前面）—— 镜像 llm_board_group_models。
    function groupModels(rows) {
        var seen = {};
        return rows.filter(function (r) {
            if (seen[r.id]) return false;
            seen[r.id] = true;
            return true;
        });
    }
    function markFamilies(rows, sizes) {
        sizes = sizes || familySizes(rows);
        var seen = {}, colors = {}, runs = [], neighbors = {};
        // 相邻族邻接表：按行序去掉连续重复（只有多档族参与，单档族不画色条）
        rows.forEach(function (r) {
            if (sizes[r.id] < 2) return;
            if (runs.length && runs[runs.length - 1] === r.id) return;
            runs.push(r.id);
        });
        runs.forEach(function (id, index) {
            neighbors[id] = neighbors[id] || {};
            if (index > 0) neighbors[id][runs[index - 1]] = true;
            if (index + 1 < runs.length) neighbors[id][runs[index + 1]] = true;
        });
        // 起点按模型 id 稳定，再按首次出现顺序躲开已定色的相邻族（后出现的躲得开先出现的）
        rows.forEach(function (r) {
            var id = r.id;
            if (colors[id]) return;
            var taken = {};
            Object.keys(neighbors[id] || {}).forEach(function (other) { if (colors[other]) taken[colors[other]] = true; });
            var color = familyHash(id) % FAMILY_COLORS + 1;
            for (var step = 0; step < FAMILY_COLORS && taken[color]; step++) color = color % FAMILY_COLORS + 1;
            colors[id] = color;
        });
        return rows.map(function (r) {
            var size = sizes[r.id] || 1;
            seen[r.id] = (seen[r.id] || 0) + 1;
            return Object.assign({}, r, { family_size: size, family_rank: seen[r.id], family_color: size > 1 ? colors[r.id] : 0 });
        });
    }
    // 「上架」列的相对时间分档（镜像 includes/llm-board-data.php 的 llm_board_listed_text，
    // tests/llm-board-data.cjs 用同一批时间戳对拍）：≤0 天 今天 / 1 天 昨天 / 2–6 天 {n} 天前 /
    // 7–27 天 {n} 周前 / 28–364 天 {n} 个月前 / ≥365 天 {n} 年前。$copy 是 9 个已翻译键，
    // $now 便于测试固定基准（秒）。天档不会出现 1（1 天归「昨天」），只有周/月/年需要单数键。
    var LISTED_SINGULAR = { weeks: 'week', months: 'month', years: 'year' };
    function listedText(listedAt, copy, now) {
        copy = copy || {};
        var ts = Number(listedAt);
        if (!isFinite(ts) || ts <= 0) return '';
        var base = now == null ? Date.now() / 1000 : Number(now);
        var days = Math.floor((base - ts) / 86400);
        if (days <= 0) return copy.today || '';
        if (days === 1) return copy.yesterday || '';
        var unit, count;
        if (days < 7) { unit = 'days'; count = days; }
        else if (days < 28) { unit = 'weeks'; count = Math.round(days / 7); }
        else if (days < 365) { unit = 'months'; count = Math.round(days / 30); }
        else { unit = 'years'; count = Math.round(days / 365); }
        count = Math.max(1, count);
        var key = count === 1 && LISTED_SINGULAR[unit] ? LISTED_SINGULAR[unit] : unit;
        return String(copy[key] == null ? '' : copy[key]).replace('{n}', String(count));
    }
    // Arena 置信区间半宽：max(high - score, score - low)，镜像 includes/llm-board-data.php 的
    // llm_board_ci_half。三项齐全且 > 0 才算数，缺 CI 返回 null（绝不回落 ±0）。
    function ciHalf(row) {
        if (!row || row.arena_score == null || row.arena_ci_low == null || row.arena_ci_high == null) return null;
        var score = Number(row.arena_score), low = Number(row.arena_ci_low), high = Number(row.arena_ci_high);
        var half = Math.max(high - score, score - low);
        return isFinite(half) && half > 0 ? half : null;
    }
    // 中位数（偶数个取中间两个的平均），镜像 llm_board_median：空集返回 null
    function median(values) {
        var clean = (values || []).filter(function (v) { return v != null && isFinite(Number(v)); }).map(Number);
        if (!clean.length) return null;
        clean.sort(function (a, b) { return a - b; });
        var mid = Math.floor(clean.length / 2);
        return clean.length % 2 === 1 ? clean[mid] : (clean[mid - 1] + clean[mid]) / 2;
    }
    // 榜级分辨率：这一屏的 Arena 分能分辨多大差距（镜像 includes/llm-board-data.php 的
    // llm_board_ci_resolution，逐档行按模型去重；样本不足返回 null，页面据此什么都不说）
    function ciResolution(rows) {
        var halves = [], scored = [], seen = {};
        (rows || []).forEach(function (row) {
            if (!row || typeof row !== 'object') return;
            var id = String(row.id == null ? (row.row_key == null ? '' : row.row_key) : row.id);
            if (id === '' || seen[id]) return;
            seen[id] = true;
            var half = ciHalf(row);
            if (half != null) halves.push(half);
            if (row.arena_score == null || !isFinite(Number(row.arena_score))) return;
            scored.push({ score: Number(row.arena_score), row: row });
        });
        if (halves.length < 5) return null;
        scored.sort(function (a, b) { return b.score - a.score; });
        var pairs = 0, overlap = 0, gaps = [];
        for (var i = 1; i < scored.length; i++) {
            var prev = scored[i - 1], cur = scored[i];
            gaps.push(prev.score - cur.score);
            if (ciHalf(prev.row) == null || ciHalf(cur.row) == null) continue;
            pairs++;
            if (Number(prev.row.arena_ci_low) <= Number(cur.row.arena_ci_high) && Number(cur.row.arena_ci_low) <= Number(prev.row.arena_ci_high)) overlap++;
        }
        if (pairs < 3) return null;
        return { models: halves.length, pairs: pairs, overlap: overlap, half: median(halves), gap: median(gaps) };
    }
    // 「低样本」阈值，镜像 includes/llm-board-data.php 的 llm_board_low_votes_threshold
    var LOW_VOTES_THRESHOLD = 20000;
    // 行内明细里该出现哪些事实，镜像 llm_board_row_facts（只判断「有什么」，
    // 数字格式化与句子留给渲染层：SSR 与 JS 各用自己的本地化数字）
    function rowFacts(row, delta, effortRow) {
        if (!row || typeof row !== 'object') return null;
        var votes = row.arena_votes == null ? null : Number(row.arena_votes);
        votes = votes != null && isFinite(votes) && votes >= 0 ? Math.round(votes) : null;
        var change = delta && !delta.isNew && Number(delta.d || 0) !== 0 ? Number(delta.d) : 0;
        var facts = {
            date: typeof row.listed_at_iso === 'string' && row.listed_at_iso !== '' ? row.listed_at_iso : null,
            ci_low: null,
            ci_high: null,
            votes: votes,
            low_votes: votes != null && votes < LOW_VOTES_THRESHOLD,
            rank_delta: change,
            rank_date: change === 0 ? null : String(delta && delta.date != null ? delta.date : ''),
            effort: !!effortRow
        };
        if (ciHalf(row) != null) {
            facts.ci_low = Number(row.arena_ci_low);
            facts.ci_high = Number(row.arena_ci_high);
        }
        if (facts.date === null && facts.ci_low === null && facts.votes === null && change === 0 && !effortRow) return null;
        return facts;
    }
    var data = { normalize: normalize, readState: readState, filter: filter, priced: priced, speeded: speeded, frontier: frontier, primaryMetric: primaryMetric, stateUrl: stateUrl, variantRows: variantRows, filterRows: filterRows, valueScore: valueScore, columnCompare: columnCompare, sortKeys: SORT_KEYS, familyHash: familyHash, markFamilies: markFamilies, familySizes: familySizes, groupModels: groupModels, listedText: listedText, ciHalf: ciHalf, ciResolution: ciResolution, rowFacts: rowFacts, lowVotesThreshold: LOW_VOTES_THRESHOLD };
    if (typeof module !== 'undefined' && module.exports) module.exports = data;
    /* 译文查找与漏译记录放在 DOM 装配之前：这样 Node 环境（没有 document，会在这行之后
       提前 return）也能直接测这段逻辑。它是「键取不到就静默显示键名」的唯一运行期护栏，
       不能被测到的护栏等于没有护栏。挂在 data 上供测试调用，不改动已有导出形状。 */
    var texts = root.LLM_I18N || {};
    var i18nMisses = [];
    /* 取不到的键会「原样返回键名」—— 页面上出现 zoom_in 这种字面垃圾，却不抛错、不打日志，
       用户看得见而我们看不见。所以在这里记一笔（去重），并在显式开启调试时告警。
       返回值仍走 raw || key，与改动前逐字节一致：这是加护栏，不是改行为。
       用 root.LLM_I18N_STRICT 而不是无条件告警：生产控制台不该有噪声，
       而这个开关让测试和排查可以按需打开。 */
    function t(key, params) {
        var raw = texts[key];
        if (!raw && i18nMisses.indexOf(key) < 0) {
            i18nMisses.push(key);
            if (root.LLM_I18N_STRICT && root.console && root.console.warn) {
                root.console.warn('[i18n] 缺少译文，将原样显示键名: ' + key);
            }
        }
        var text = raw || key;
        Object.keys(params || {}).forEach(function (name) { text = text.replaceAll('{' + name + '}', String(params[name])); });
        return text;
    }
    data.t = t;
    data.i18nMisses = i18nMisses;

    if (typeof document === 'undefined') return;
    root.LLMData = data;

    var board = root.LLM_BOARD;
    if (!board || !Array.isArray(board.models)) return;
    var meta = root.LLM_VIEW_META || {}, labels = root.LLM_COL_LABELS || {};
    var state = readState(location.href), language = meta.lang || 'zh-CN';
    var tbody = document.getElementById('llm-table-body');
    var form = document.getElementById('llm-filters');
    var search = document.getElementById('llm-search');
    var weights = document.getElementById('llm-weights');
    var sort = document.getElementById('llm-sort');
    var groupSwitch = document.querySelector('.llm-group-switch');
    var groupNote = document.getElementById('llm-group-note');
    var effortNote = document.getElementById('llm-effort-note');
    var hasIntel = board.models.some(function (m) { return m.aa_intelligence != null; });

    function esc(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function price(value) {
        if (value == null) return '—';
        return '$' + Number(value).toLocaleString(language, { maximumFractionDigits: Number(value) < 1 ? 4 : 2 });
    }
    function modelUrl(m) {
        return '/llm-model.php?id=' + encodeURIComponent(m.id) + (language !== 'zh-CN' ? '&lang=' + encodeURIComponent(language) : '');
    }
    function icon(m) {
        var slug = ((root.LLM_MODEL_META || {})[m.id] || {}).icon || '';
        return /^[a-z0-9-]+$/.test(slug) ? (root.LLM_ICON_BASE || '/assets/llm/icons/') + slug + '.svg?v=' + encodeURIComponent(root.LLM_ICON_VERSION || '') : '';
    }
    function iconHtml(m) {
        var url = icon(m), letter = Array.from(m.org || m.display_name || '?')[0];
        return '<span class="llm-logo">' + (url ? '<img class="llm-org-icon" src="' + esc(url) + '" width="26" height="26" alt="">' : '')
            + '<span class="llm-org-mark"' + (url ? ' hidden' : '') + '>' + esc(letter) + '</span></span>';
    }
    function bindIcons(container) {
        container.querySelectorAll('.llm-logo img').forEach(function (img) {
            img.addEventListener('error', function () { img.hidden = true; img.nextElementSibling.hidden = false; }, { once: true });
            if (img.complete && !img.naturalWidth) { img.hidden = true; img.nextElementSibling.hidden = false; }
        });
    }
    function weightText(m) { return t(m.open_weights === true ? 'weights_open' : m.open_weights === false ? 'weights_closed' : 'weights_unknown'); }
    // 许可徽标：只在数据明确时画（开放权重 / 闭源）；open_weights 为 null 不画「未注明」
    // （镜像 SSR 的 $weightBadge：拿不到就说没有，不占一个没有信息量的位）
    function weightBadge(m) {
        if (m.open_weights !== true && m.open_weights !== false) return '';
        return '<span class="llm-badge ' + (m.open_weights === true ? 'llm-badge-open' : 'llm-badge-closed') + '">'
            + esc(m.open_weights === true ? t('weights_open') : t('badge_closed')) + '</span>';
    }
    // 主指标列：有智能指数就突出智能指数，否则突出 Arena（与 SSR 的 $primaryColumn 同一判断）
    var primaryColumn = hasIntel ? 'intel' : 'arena';
    function isPrimary(column) { return column === primaryColumn; }
    function primaryMark(column) { return isPrimary(column) ? ' is-primary' : ''; }
    // 对比条：按当前表内主指标最高值归一（SSR 同一口径），缺失不画
    function meterWidth(value, max) {
        if (value == null || !isFinite(Number(value)) || !(max > 0)) return '0';
        return String(Math.max(0, Math.min(100, Math.round(Number(value) / max * 100))));
    }
    function primaryCell(value, text, meterMax) {
        return '<span class="llm-primary"><span class="llm-primary-label">' + esc(labels[primaryColumn] || '') + '</span>'
            + '<span class="llm-primary-value' + (value == null ? ' is-empty' : '') + '">' + esc(text) + '</span>'
            + '<span class="llm-meter" aria-hidden="true"><i class="llm-meter-fill" style="width:' + meterWidth(value, meterMax) + '%"></i></span></span>';
    }
    // Arena 置信区间（LMArena rating_lower / rating_upper）：± 徽标只在三项齐全且半宽 > 0 时出现，
    // 缺 CI 的模型显示空白而不是 ±0；悬浮说明与 SSR 共用 window.LLM_CI 一份模板
    var ciMeta = root.LLM_CI || {};
    // 半宽口径在 data 区（data.ciHalf ↔ llm_board_ci_half）：± 徽标、榜级分辨率、明细行共用同一条
    var ciHalf = data.ciHalf;
    function ciNumber(value) { return value.toLocaleString(language, { minimumFractionDigits: 1, maximumFractionDigits: 1 }); }
    // 纯文本形态（移动端格子把它接在分数后面，一格放不下第二个元素）
    function ciText(m) {
        var half = ciHalf(m);
        return half == null ? '' : '±' + ciNumber(half);
    }
    // 桌面列里的徽标：给出完整区间，说明名次差异常常落在区间内
    function ciBadge(m) {
        var half = ciHalf(m);
        if (half == null) return '';
        var tip = fill(ciMeta.tip, { low: ciNumber(Number(m.arena_ci_low)), high: ciNumber(Number(m.arena_ci_high)), half: ciNumber(half) });
        return '<span class="llm-ci"' + (tip ? ' title="' + esc(tip) + '"' : '') + '>' + esc(ciText(m)) + '</span>';
    }
    // 推理强度档位徽标（文案来自 window.LLM_EFFORT，与 SSR 同一份 i18n）。
    // 只在「按档位」视图里画：那一屏一档一行，标出是哪一档才有意义；
    // 默认的「按模型」视图一行就是一个模型，挤一个徽标只是噪音。
    var effortMeta = root.LLM_EFFORT || { labels: {}, tip: '' };
    function effortBadge(m) {
        var label = typeof m.effort === 'string' ? m.effort : '';
        if (!label || state.group !== 'tier') return '';
        var text = (effortMeta.labels || {})[label] || label;
        var tip = String(effortMeta.tip || '').replaceAll('{label}', text);
        return '<span class="llm-effort-badge"' + (tip ? ' title="' + esc(tip) + '"' : '') + '>' + esc(text) + '</span>';
    }
    // 上架时间：正文给相对时间，精确日期进 title。文案来自 window.LLM_LISTED（一份种子），
    // 分档规则与 SSR 共用 data 区的 listedText（Node 测试里对拍过）
    var listedMeta = root.LLM_LISTED || {};
    // 移动端指标面板的一格：标签在上、数值在下，缺值传 '—'（与 SSR 的 $metricTile 同一套 class）
    function metricTile(label, value) {
        var text = value == null ? '—' : String(value);
        return '<span class="llm-mobile-metric"><span class="llm-mobile-metric-label">' + esc(label) + '</span>'
            + '<span class="llm-mobile-metric-value' + (text === '—' ? ' is-empty' : '') + '">' + esc(text) + '</span></span>';
    }
    // -----------------------------------------------------------------------
    // 行内明细（窄屏 / 触屏按需展开）：精确上架日期、Arena 95% 区间原文、票数、名次变动与
    // 档位说明原先只挂在 title 上，而 title 在触屏上永远不出现。事实清单由 data.rowFacts 决定
    // （与 SSR 的 llm_board_row_facts 同一条规则），这里只做本地化与拼句子；按钮只在窄屏/
    // 触屏显示（见 .llm-row-more 的媒体查询）。展开状态按明细行 id 记着：换排序/筛选重渲染后，
    // 原来展开的行仍然是展开的。
    // -----------------------------------------------------------------------
    var detailMeta = root.LLM_DETAIL || {};
    var openDetails = {};
    function detailId(m) { return 'llm-detail-' + String(m.row_key == null ? m.id : m.row_key).replace(/[^A-Za-z0-9_-]+/g, '-'); }
    function detailItem(label, value) {
        return '<span class="llm-detail-item"><span class="llm-detail-label">' + esc(label) + '</span><span class="llm-detail-value">' + esc(value) + '</span></span>';
    }
    function rowDetail(m) {
        var facts = data.rowFacts(m, rowDeltaOf(m), state.group === 'tier' && !!m.effort);
        if (!facts) return '';
        var id = detailId(m), html = '';
        if (facts.date) html += detailItem(labels.date, facts.date);
        if (facts.ci_low != null) html += detailItem(detailMeta.ci || '95%', ciNumber(facts.ci_low) + '–' + ciNumber(facts.ci_high));
        if (facts.votes != null) html += detailItem(detailMeta.votes || 'Arena', Number(facts.votes).toLocaleString(language));
        if (facts.rank_delta) html += detailItem(detailMeta.move || 'Rank', String(facts.rank_date || '') + ' ' + (facts.rank_delta > 0 ? '↑' : '↓') + Math.abs(facts.rank_delta));
        // 提醒句沿用各自的 tip 文案：低样本与 Arena 格的悬浮说明同一句，档位说明与档位徽标同一句
        if (facts.low_votes) html += '<span class="llm-detail-note">' + esc(t('low_votes_tip', { votes: Number(facts.votes).toLocaleString(language) })) + '</span>';
        if (facts.effort) {
            var effortText = (effortMeta.labels || {})[m.effort] || m.effort;
            html += '<span class="llm-detail-note">' + esc(String(effortMeta.tip || '').replaceAll('{label}', effortText)) + '</span>';
        }
        return '<tr class="llm-detail-row" id="' + esc(id) + '"' + (openDetails[id] ? '' : ' hidden') + '><td colspan="' + (hasIntel ? 10 : 7) + '">' + html + '</td></tr>';
    }
    function detailToggle(m) {
        if (!data.rowFacts(m, rowDeltaOf(m), state.group === 'tier' && !!m.effort)) return '';
        var id = detailId(m), open = !!openDetails[id];
        return '<button type="button" class="llm-row-more" aria-expanded="' + (open ? 'true' : 'false') + '" aria-controls="' + esc(id) + '" aria-label="' + esc(open ? (detailMeta.less || '') : (detailMeta.more || '')) + '">i</button>';
    }
    function toggleDetail(button) {
        var row = document.getElementById(button.getAttribute('aria-controls'));
        if (!row) return;
        var open = row.hasAttribute('hidden');
        if (open) row.removeAttribute('hidden'); else row.setAttribute('hidden', 'hidden');
        openDetails[button.getAttribute('aria-controls')] = open;
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        button.setAttribute('aria-label', open ? (detailMeta.less || '') : (detailMeta.more || ''));
    }
    function rankMove(m) {
        if (state.sort !== 'intel' || state.weights !== 'all' || state.q) return '';
        var delta = rowDeltaOf(m);
        // 「新上榜」不再画标记（排名数字下面多一行碎字），只留真的升/降
        if (!delta || delta.isNew || !delta.d) return '';
        return '<span class="llm-rank-move ' + (delta.d > 0 ? 'is-up' : 'is-down') + '" title="'
            + esc(t(delta.d > 0 ? 'rank_up_tip' : 'rank_down_tip', { date: delta.date || '', n: Math.abs(delta.d) })) + '">'
            + (delta.d > 0 ? '↑' : '↓') + Math.abs(delta.d) + '</span>';
    }
    // 名次变动的「原始」取值（不含视图条件）：箭头紧挨着表内行号，所以排序/筛选时它不该出现；
    // 行内明细里的那句自带基线日期（「较上期名次 2026-09-04 ↑3」），换排序也不会被误读成表内行号，
    // 所以明细用这一条 —— 与 SSR 的 $rowDelta 完全同口径（只算模型本体那一行）。
    function rowDeltaOf(m) {
        if (m.effort && m.effort !== m.effort_current) return null;
        return (root.LLM_RANK_DELTA || {})[m.id] || null;
    }
    // 占位符替换：一份文案多处用（CI 悬浮说明、相对时间），{name} 逐个顶掉
    function fill(text, params) {
        var out = String(text == null ? '' : text);
        Object.keys(params || {}).forEach(function (name) { out = out.replaceAll('{' + name + '}', String(params[name])); });
        return out;
    }
    function row(m, index, meterMax) {
        // 与 SSR 的 $valueScore 同一口径：Arena 分为空时不能算出 0（0 会被读成「最差性价比」）
        var ratio = m.price_out == null || m.arena_score == null || Number(m.price_out) <= 0 ? '—' : (Number(m.arena_score) / Number(m.price_out)).toLocaleString(language, { maximumFractionDigits: 0 });
        if (m.price_out != null && Number(m.price_out) === 0) ratio = labels.valueFree;
        // Arena 格的悬浮说明：票数少的那句并进来（原先是一个几乎每行都挂的「低样本」徽标）
        var arenaTip = root.LLM_ARENA_TIP || t('arena_tip');
        if (m.arena_votes != null && Number(m.arena_votes) < data.lowVotesThreshold) {
            arenaTip += ' ' + t('low_votes_tip', { votes: Number(m.arena_votes).toLocaleString(language) });
        }
        // 没有可用时间戳时回落原 ISO 串（与 SSR 的 $listedCell 同一口径），空值不编造「今天」
        var listed = listedText(m.listed_at, listedMeta) || m.listed_at_iso || '';
        // 移动端面板与桌面列同源同序（Arena / 智能指数 / 速度 / 延迟 / 输入价 / 输出价 / 评分·价格比 / 上架）
        var mobileMetrics = '<td class="llm-mobile-metrics">'
            + metricTile(labels.arena || 'Arena', m.arena_score == null ? '—' : Number(m.arena_score).toLocaleString(language, { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + (ciText(m) ? ' ' + ciText(m) : ''))
            + (hasIntel
                ? metricTile(labels.intel || 'AA', m.aa_intelligence == null ? '—' : m.aa_intelligence)
                  + metricTile(labels.speed || '速度', m.aa_speed == null ? '—' : Math.round(Number(m.aa_speed)) + '/s')
                  + metricTile(labels.ttft || '延迟', m.aa_ttft == null ? '—' : Number(m.aa_ttft).toFixed(2) + 's')
                : '')
            + metricTile(labels.pin, price(m.price_in))
            + metricTile(labels.pout, price(m.price_out))
            + metricTile(labels.value, ratio)
            + metricTile(labels.date, listed === '' ? '—' : listed)
            + '</td>';
        var familyColor = Number(m.family_color || 0);
        // 同族行的左侧色条：只在「按档位」视图里画（一档一行时它把同模型的行连起来）；
        // 按模型视图一行一个模型，色条没有信息量
        var familyAttr = state.group === 'tier' && familyColor > 0 ? ' class="is-family" style="--llm-family:var(--llm-family-' + familyColor + ')"' : '';
        return '<tr data-id="' + esc(m.id) + '" data-row-key="' + esc(m.row_key == null ? m.id : m.row_key) + '"' + (m.effort ? ' data-effort="' + esc(m.effort) + '"' : '') + familyAttr + '><td class="llm-col-rank"><span class="llm-rank-dot' + (index < 3 ? ' r' + (index + 1) : '') + '">' + (index + 1) + '</span>' + rankMove(m) + detailToggle(m) + '</td>'
            + '<td class="llm-col-model"><div class="llm-model-cell">' + iconHtml(m) + '<span class="llm-model-text"><span class="llm-model-name"><a href="' + esc(modelUrl(m)) + '">' + esc(m.display_name || m.id) + '</a>' + effortBadge(m) + ' <span class="llm-model-org">' + esc(m.org || '') + (weightBadge(m) ? ' ' + weightBadge(m) : '') + '</span></span></span></div></td>'
            + '<td class="llm-col-arena' + primaryMark('arena') + '" data-label="' + esc(labels.arena || 'Arena') + '">'
            + '<div class="llm-arena-cell"' + (arenaTip ? ' title="' + esc(arenaTip) + '"' : '') + '>'
            + (isPrimary('arena')
                ? primaryCell(m.arena_score, m.arena_score == null ? '—' : Number(m.arena_score).toLocaleString(language, { minimumFractionDigits: 1, maximumFractionDigits: 1 }), meterMax)
                : (m.arena_score == null
                    ? '<span class="llm-arena-score is-empty">—</span>'
                    : '<span class="llm-arena-score">' + Number(m.arena_score).toLocaleString(language, { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '</span>'))
            + ciBadge(m)
            + '</div></td>'
            + (hasIntel ? '<td class="llm-col-intel' + primaryMark('intel') + '" data-label="' + esc(labels.intel || 'AA') + '">'
            + (isPrimary('intel')
                ? primaryCell(m.aa_intelligence, m.aa_intelligence == null ? '—' : String(m.aa_intelligence), meterMax)
                : (m.aa_intelligence == null ? '—' : esc(m.aa_intelligence)))
            + '</td>'
            + '<td class="llm-col-speed" data-label="' + esc(labels.speed || '速度') + '">' + (m.aa_speed == null ? '—' : esc(Math.round(Number(m.aa_speed)))) + '</td>'
            + '<td class="llm-col-ttft" data-label="' + esc(labels.ttft || '延迟') + '">' + (m.aa_ttft == null ? '—' : esc(Number(m.aa_ttft).toFixed(2)) + 's') + '</td>' : '')
            + '<td class="llm-col-pin" data-label="' + esc(labels.pin) + '"><span class="llm-price-num">' + esc(price(m.price_in)) + '</span></td>'
            + '<td class="llm-col-pout" data-label="' + esc(labels.pout) + '"><span class="llm-price-num">' + esc(price(m.price_out)) + '</span></td>'
            + '<td class="llm-col-value" data-label="' + esc(labels.value) + '"><span class="llm-value-num">' + esc(ratio) + '</span></td>'
            + '<td class="llm-col-date" data-label="' + esc(labels.date) + '"' + (m.listed_at_iso ? ' title="' + esc(m.listed_at_iso) + '"' : '') + '>' + esc(listed === '' ? '—' : listed) + '</td>'
            + mobileMetrics + '</tr>';
    }
    function updateMeta() {
        var p = new URL(location.href).searchParams, view = p.get('view') || 'all';
        if (!meta.h1 || !meta.h1[view]) view = 'all';
        document.querySelector('.page-title h1').textContent = meta.h1[view];
        document.getElementById('llm-intro').textContent = meta.intro[view];
        document.title = meta.title[view];
        var legacy = document.getElementById('llm-legacy-note');
        legacy.hidden = view !== 'code' && view !== 'intel';
        if (!legacy.hidden) legacy.textContent = (root.LLM_LEGACY_NOTES || {})[view] || legacy.textContent;
        var canonical = document.querySelector('link[rel="canonical"]');
        var filtered = Array.from(p.keys()).some(function (key) { return key !== 'view' && key !== 'lang'; });
        var noindex = filtered || view === 'intel';
        if (canonical) {
            var target = new URL('/llm-leaderboard.php', root.location.origin);
            if (!noindex && view !== 'all') target.searchParams.set('view', view);
            if (language !== 'zh-CN') target.searchParams.set('lang', language);
            canonical.href = target.href;
        }
        var robots = document.querySelector('meta[name="robots"]');
        if (robots) robots.content = noindex ? 'noindex,follow' : 'index,follow';
    }
    // 表头排序：<th> 里是真链接（无 JS 也能按该列排序），JS 拦截后原地重排；箭头与 aria-sort 只标当前列
    function syncSortHeaders() {
        var active = SORT_ALIAS[state.sort] || state.sort;
        // 老快照没有智能指数列时，intel 排序实际按 Arena 兜底：高亮落到 Arena 列
        if (!hasIntel && active === 'intel') active = 'arena';
        document.querySelectorAll('#llm-table thead th.is-sortable').forEach(function (th) {
            var link = th.querySelector('a.llm-sort');
            if (!link) return;
            var key = link.dataset.sort, direction = SORT_DIRECTION[key] || 'desc', on = key === active;
            th.setAttribute('aria-sort', on ? (direction === 'asc' ? 'ascending' : 'descending') : 'none');
            link.classList.toggle('is-active', on);
            var arrow = link.querySelector('.llm-sort-arrow');
            if (arrow) arrow.textContent = on ? (direction === 'asc' ? '↑' : '↓') : '↕';
            link.href = stateUrl(Object.assign({}, state, { sort: key }), language);
        });
    }
    // 点击列头时同步算出目标 view，避免「URL 的 view 与内存里的 state.view 不一致」（与 stateUrl 同规则）
    function sortView(key, weightsValue, current) {
        if (isChartView(current)) return current;
        return key === 'price' ? 'cheap' : key === 'new' ? 'new' : weightsValue === 'open' ? 'open' : 'all';
    }
    function render() {
        var list = filter(board.models, state);
        // 默认（按模型）口径：同一个模型只留一行 —— 「当前排序列最优的那一档」；
        // 按档位口径（group=tier）一行一档，只在那一屏画族色条把同模型的行连起来
        var allRows = filterRows(board.models, state);
        var rows = state.group === 'model' ? groupModels(allRows) : markFamilies(allRows);
        // 对比条基准：当前表内主指标最高值（SSR 的 $primaryMax 同一口径，过滤/排序后重新归一）
        var meterMax = 0;
        var primaryField = hasIntel ? 'aa_intelligence' : 'arena_score';
        rows.forEach(function (m) {
            var value = m[primaryField];
            if (value != null && isFinite(Number(value))) meterMax = Math.max(meterMax, Number(value));
        });
        document.getElementById('llm-table-wrap').hidden = isChartView(state.view);
        document.querySelector('.llm-pareto').hidden = !isChartView(state.view);
        document.querySelector('.llm-leaderboard-page').classList.toggle('llm-page-wide', isChartView(state.view));
        document.querySelectorAll('#llm-views a').forEach(function (a) {
            var active = a.dataset.view === state.view;
            a.classList.toggle('is-active', active);
            if (active) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
            a.href = stateUrl(Object.assign({}, state, { view: a.dataset.view }), language);
        });
        // 表内按「模型 × 档位」计数（条数）；图表按模型计数（图表一个模型一个点）。
        // 图表有两个轴：价格轴按 priced（有正输出价），速度轴按 speeded（有实测速度）。
        // 必须与 SSR 的 llm_board_plottable($models,$axis) 同口径，否则同一屏会同时出现
        // 「显示 N 个模型」与「视窗内 M / M」两个互相矛盾的数字（速度轴 10 ≠ 价格轴 13）。
        var axisIsSpeed = (typeof window !== 'undefined' && window.LLM_CHART_AXIS === 'speed');
        document.getElementById('llm-results-count').textContent = isChartView(state.view)
            ? t('results_count', { count: (axisIsSpeed ? speeded(list) : priced(list)).length })
            : t('results_rows', { count: rows.length });
        if (tbody) {
            tbody.innerHTML = rows.length ? rows.map(function (m, i) { return row(m, i, meterMax) + rowDetail(m); }).join('') : '<tr><td colspan="' + (hasIntel ? 10 : 7) + '" class="llm-error">' + esc(t('no_matches')) + '</td></tr>';
            bindIcons(tbody);
        }
        // 榜级分辨率（脚注里那一句）：与 SSR 同一份模板、同一套口径（data.ciResolution ↔
        // llm_board_ci_resolution），换筛选/排序后重算；样本不够就把这句收起来，什么都不说
        var resolutionNote = document.getElementById('llm-ci-resolution');
        if (resolutionNote) {
            var resolution = ciMeta.resolution ? ciResolution(rows) : null;
            resolutionNote.textContent = resolution ? fill(ciMeta.resolution, {
                half: ciNumber(resolution.half), gap: ciNumber(resolution.gap),
                overlap: resolution.overlap, pairs: resolution.pairs
            }) : '';
            resolutionNote.hidden = !resolution;
        }
        if (document.activeElement !== search) search.value = state.q;
        weights.value = state.weights;
        // 老口径 score/price 在下拉框里显示成同义的列名（Arena / 输出价）
        // 排序只作用于表格：图表视图里服务端不再渲染这个控件，所以必须判空。
        // （曾经这里直接取 sort.value，图表视图下抛 TypeError，init 在
        //  LLMUI.visible = list 之前中断 —— 后果是整张图 30 个点全部 is-muted、
        //  一个 role=button 都没有，气泡既不能悬浮也不能点，状态行还显示 0/0。）
        if (sort) sort.value = SORT_ALIAS[state.sort] || state.sort;
        if (groupSwitch) {
            // 两个选项都是真链接：显示口径与 stateUrl 同规则，当前项标 aria-current
            groupSwitch.querySelectorAll('a[data-group]').forEach(function (a) {
                var on = a.dataset.group === state.group;
                a.classList.toggle('is-active', on);
                if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
                a.href = stateUrl(Object.assign({}, state, { group: a.dataset.group }), language);
            });
        }
        // 折叠视图的脚注与展开视图的脚注互斥：说清楚「一个模型一行」和「一档一行」的差别
        if (groupNote) groupNote.hidden = state.group !== 'model';
        if (effortNote) effortNote.hidden = state.group === 'model';
        syncSortHeaders();
        form.elements.view.value = state.view;
        updateMeta();
        root.LLMUI.state = state;
        root.LLMUI.visible = list;
        root.LLMUI.rows = rows;
        document.dispatchEvent(new CustomEvent('llm:change'));
    }
    function change(next, push) {
        state = Object.assign({}, state, next);
        var url = stateUrl(state, language);
        /* 视图切换会换掉图表的轴与点集，而轴由服务端决定（window.LLM_CHART_AXIS）。
           图表模块在加载时就把轴写死，客户端无法等价重绘 —— 只推 URL 的话，
           tab 高亮和地址栏都变了、图还是旧的那张，用户看到的就是「点了没反应」。
           所以视图切换必须整页导航；weights/sort/group 只影响表格与变灰，
           仍走原地重绘。 */
        if (next.view !== undefined) { location.href = url; return; }
        if (url !== location.pathname + location.search + location.hash) history[push ? 'pushState' : 'replaceState'](null, '', url);
        render();
    }
    root.LLMUI = { t: t, esc: esc, price: price, modelUrl: modelUrl, icon: icon, iconHtml: iconHtml, bindIcons: bindIcons, weightText: weightText, state: state, visible: [], i18nMisses: i18nMisses };
    form.classList.add('is-enhanced');
    form.querySelector('.llm-filter-submit').hidden = true;
    form.addEventListener('submit', function (e) { e.preventDefault(); change({ q: search.value.trim(), weights: weights.value, sort: sort ? sort.value : state.sort }, true); });
    function searchChanged(e) {
        if (!e.isComposing) change({ q: Array.from(search.value.trim()).slice(0, 120).join('') }, false);
    }
    search.addEventListener('input', searchChanged);
    search.addEventListener('compositionend', searchChanged);
    weights.addEventListener('change', function () { change({ weights: this.value }, true); });
    if (sort) sort.addEventListener('change', function () { change({ sort: this.value }, true); });
    if (groupSwitch) {
        groupSwitch.addEventListener('click', function (e) {
            var a = e.target.closest('a[data-group]');
            if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button !== 0) return;
            e.preventDefault();
            change({ group: a.dataset.group }, true);
        });
    }
    document.getElementById('llm-views').addEventListener('click', function (e) {
        var a = e.target.closest('a[data-view]');
        if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button !== 0) return;
        e.preventDefault();
        change({ view: a.dataset.view }, true);
    });
    var boardTable = document.getElementById('llm-table');
    if (boardTable) {
        // 表内点击：列头是真链接（无 JS 也能排序），JS 拦下来原地重渲染，不整页刷新
        boardTable.addEventListener('click', function (e) {
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button !== 0) return;
            // 行内明细的展开/收起（窄屏/触屏的按钮）：展开状态记在 openDetails 里，重渲染后不回弹
            var more = e.target.closest('button.llm-row-more');
            if (more) {
                e.preventDefault();
                toggleDetail(more);
                return;
            }
            var link = e.target.closest('a.llm-sort');
            if (!link) return;
            var key = link.dataset.sort;
            if (!key || SORT_KEYS.indexOf(key) === -1) return;
            e.preventDefault();
            change({ sort: key, view: sortView(key, state.weights, state.view) }, true);
        });
    }
    root.addEventListener('popstate', function () { state = readState(location.href); search.value = state.q; render(); });
    var info = document.querySelector('.llm-info-tip');
    if (info) {
        var help = document.getElementById(info.getAttribute('aria-controls'));
        function closeHelp() { info.setAttribute('aria-expanded', 'false'); help.setAttribute('aria-hidden', 'true'); help.classList.remove('is-open'); }
        info.addEventListener('click', function () {
            var open = info.getAttribute('aria-expanded') !== 'true';
            info.setAttribute('aria-expanded', String(open)); help.setAttribute('aria-hidden', String(!open)); help.classList.toggle('is-open', open);
        });
        document.addEventListener('click', function (e) { if (!e.target.closest('.llm-heading-with-tip')) closeHelp(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeHelp(); });
    }
    var tableWrap = document.getElementById('llm-table-wrap');
    if (tableWrap) {
        var drag = { active: false, x: 0, left: 0, moved: false };
        function syncDragHint() { tableWrap.classList.toggle('can-drag', tableWrap.scrollWidth > tableWrap.clientWidth + 1); }
        tableWrap.addEventListener('pointerdown', function (e) {
            if (e.pointerType !== 'mouse' || e.button !== 0) return;
            if (e.target.closest('a, button, summary, input, select, label')) return;
            drag.active = true; drag.x = e.clientX; drag.left = tableWrap.scrollLeft; drag.moved = false;
            tableWrap.classList.add('is-dragging');
            if (tableWrap.setPointerCapture) { try { tableWrap.setPointerCapture(e.pointerId); } catch (err) { /* non-pointer devices */ } }
        });
        tableWrap.addEventListener('pointermove', function (e) {
            if (!drag.active) return;
            var dx = e.clientX - drag.x;
            if (Math.abs(dx) > 4) drag.moved = true;
            if (drag.moved) tableWrap.scrollLeft = drag.left - dx;
        });
        function endDrag(e) {
            if (!drag.active) return;
            drag.active = false;
            tableWrap.classList.remove('is-dragging');
            if (drag.moved && e.cancelable) e.preventDefault();
        }
        tableWrap.addEventListener('pointerup', endDrag);
        tableWrap.addEventListener('pointercancel', endDrag);
        tableWrap.addEventListener('click', function (e) {
            if (drag.moved) { e.preventDefault(); e.stopPropagation(); drag.moved = false; }
        }, true);
        window.addEventListener('resize', syncDragHint);
        syncDragHint();
    }
    render();
})(typeof window !== 'undefined' ? window : globalThis);
