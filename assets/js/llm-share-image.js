/* Local LLM leaderboard poster composer: preview and PNG export stay in the browser. */
(function (root, factory) {
    if (typeof module !== 'undefined' && module.exports) module.exports = factory(globalThis);
    else root.LLMShare = factory(root);
}(typeof window !== 'undefined' ? window : globalThis, function (root) {
    'use strict';

    var MAX_MODELS = 30;
    var iconPromises = Object.create(null);

    function escapeXml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&apos;');
    }

    function scoreField(models) {
        var list = Array.isArray(models) ? models : [];
        for (var i = 0; i < list.length; i++) {
            var value = list[i] ? list[i].aa_intelligence : null;
            if (value !== null && value !== undefined && Number.isFinite(Number(value))) return 'aa_intelligence';
        }
        return 'arena_score';
    }

    function finiteScore(model, field) {
        var key = field || 'arena_score';
        if (!model || model[key] === null || model[key] === '' || typeof model[key] === 'boolean') return null;
        var value = Number(model[key]);
        return Number.isFinite(value) ? value : null;
    }

    function compareScore(a, b, field) {
        return finiteScore(b, field) - finiteScore(a, field) || String(a.id).localeCompare(String(b.id));
    }

    function rankModels(models, count) {
        var field = scoreField(models);
        var list = (Array.isArray(models) ? models : []).filter(function (model) {
            return model && typeof model.id === 'string' && finiteScore(model, field) !== null;
        }).slice().sort(function (a, b) { return compareScore(a, b, field); });
        return list.slice(0, Math.max(1, Math.min(MAX_MODELS, Number(count) || 10)));
    }

    function number(value, digits) {
        var numeric = Number(value);
        if (!Number.isFinite(numeric)) return '—';
        try {
            return new Intl.NumberFormat(root.document ? (root.LLM_VIEW_META || {}).lang || 'zh-CN' : 'en-US', {
                minimumFractionDigits: digits,
                maximumFractionDigits: digits,
            }).format(numeric);
        } catch (error) {
            return numeric.toFixed(digits);
        }
    }

    function price(value) {
        var numeric = Number(value);
        if (!Number.isFinite(numeric)) return '—';
        if (numeric === 0) return '$0';
        var digits = numeric < 1 ? 4 : 2;
        return '$' + number(numeric, digits).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
    }

    function translate(key, params, fallback) {
        var text = (root.LLM_I18N || {})[key] || fallback || key;
        Object.keys(params || {}).forEach(function (name) {
            text = text.replace(new RegExp('\\{' + name.replace(/[.*+?^${}()|[\\]\\]/g, '\\$&') + '\\}', 'g'), String(params[name]));
        });
        return text;
    }

    function trimLabel(value, max) {
        var text = String(value == null ? '' : value);
        var chars = Array.from(text);
        return chars.length > max ? chars.slice(0, max - 1).join('') + '…' : text;
    }

    function wrapText(value, maxChars) {
        var text = String(value == null ? '' : value).trim();
        if (!text) return [''];
        var lines = [];
        var current = '';
        text.split(/\s+/).forEach(function (word) {
            var chars = Array.from(word);
            while (chars.length) {
                var available = maxChars - Array.from(current).length - (current ? 1 : 0);
                if (available <= 0) {
                    lines.push(current);
                    current = '';
                    available = maxChars;
                }
                var take = Math.min(available, chars.length);
                current += (current ? ' ' : '') + chars.splice(0, take).join('');
                if (chars.length) {
                    lines.push(current);
                    current = '';
                }
            }
        });
        if (current) lines.push(current);
        return lines;
    }

    function modelIconUrl(model) {
        var meta = (root.LLM_MODEL_META || {})[model.id] || {};
        var slug = meta.icon || '';
        if (!/^[a-z0-9-]+$/.test(slug)) return '';
        return (root.LLM_ICON_BASE || '/assets/llm/icons/') + slug + '.svg?v=' + encodeURIComponent(root.LLM_ICON_VERSION || '');
    }

    function buildPosterData(models, options, context) {
        options = options || {};
        context = context || {};
        var orientation = options.orientation === 'portrait' ? 'portrait' : 'landscape';
        var theme = options.theme === 'dark' ? 'dark' : 'light';
        var selected = rankModels(models, options.count);
        var primary = scoreField(models);
        var width = orientation === 'portrait' ? 1080 : 1600;
        var rowHeight = orientation === 'portrait' ? (options.showPrices ? 124 : 104) : (options.showPrices ? 82 : 72);
        var headerHeight = orientation === 'portrait' ? 236 : 216;
        var fixtureNote = context.fixtureNote || translate('share_preview_fixture', {}, '本地预览使用合成样本，不代表线上排名或实时价格。');
        var fixtureLines = context.previewFixture ? wrapText(fixtureNote, orientation === 'portrait' ? 72 : 112) : [];
        var footerHeight = context.previewFixture ? 29 + 49 + 18 + fixtureLines.length * 20 + 20 : 104;
        var height = headerHeight + selected.length * rowHeight + footerHeight;
        var scores = selected.map(function (model) { return finiteScore(model, primary); });
        var minScore = scores.length ? Math.min.apply(Math, scores) : 0;
        var maxScore = scores.length ? Math.max.apply(Math, scores) : 1;
        var range = Math.max(1, maxScore - minScore);
        var locale = context.language || 'zh-CN';
        var scope = context.scope || translate('share_scope_filtered', {}, '当前筛选范围');
        var title = translate('share_poster_title', {}, '大模型排行榜');
        var scoreLabel = translate('share_poster_score', {}, '智能指数');
        var sourceLabel = translate('share_poster_source', {}, '评分来源：Artificial Analysis；价格来源：OpenRouter');
        var rows = selected.map(function (model, index) {
            var score = finiteScore(model, primary);
            return {
                model: model,
                rank: index + 1,
                name: trimLabel(model.display_name || model.id, orientation === 'portrait' ? 24 : 31),
                org: trimLabel(model.org || '', orientation === 'portrait' ? 21 : 28),
                score: score,
                scoreText: number(score, 1),
                bar: .12 + .88 * ((score - minScore) / range),
                highlighted: String(model.id) === String(options.highlight || ''),
                icon: (context.iconMap || {})[model.id] || modelIconUrl(model),
                initial: Array.from(model.org || model.display_name || '?')[0].toUpperCase(),
                open: model.open_weights === true,
                lowSample: model.arena_votes != null && Number(model.arena_votes) < 20000,
                inputPrice: price(model.price_in),
                outputPrice: price(model.price_out),
            };
        });
        var dateText = context.generatedAt || '—';
        if (context.generatedAt) {
            var parsed = new Date(context.generatedAt);
            if (!Number.isNaN(parsed.getTime())) {
                try { dateText = parsed.toLocaleDateString(locale, { year: 'numeric', month: '2-digit', day: '2-digit' }); } catch (error) { dateText = context.generatedAt; }
            }
        }
        return {
            orientation: orientation,
            theme: theme,
            width: width,
            height: height,
            headerHeight: headerHeight,
            rowHeight: rowHeight,
            rows: rows,
            count: rows.length,
            total: Array.isArray(models) ? models.length : 0,
            scope: scope,
            title: title,
            scoreLabel: scoreLabel,
            sourceLabel: sourceLabel,
            dateText: dateText,
            showPrices: !!options.showPrices,
            previewFixture: !!context.previewFixture,
            fixtureNote: fixtureNote,
            fixtureLines: fixtureLines,
            empty: rows.length === 0,
        };
    }

    /* 两套配色。深色不是把浅色反相就完事：深底上文字要重新配对比度，所以标题用近白、
       次级文字用中灰、强调蓝提亮一档、警示黄也要提亮。键名与 posterStyles / renderPoster /
       iconMarkup 里的用途一一对应；加颜色时三处一起加，别只改一处。 */
    var POSTER_THEMES = {
        light: {
            canvas: '#ffffff', brand: '#1666d9', title: '#172033', muted: '#667085',
            line: '#e4e8ee', rank: '#344054', barBg: '#eef2f6', bar: '#1666d9',
            barHighlight: '#d97706', open: '#067647', closed: '#667085', low: '#9a6700',
            footStrong: '#344054', notice: '#9a6700', noticeBg: '#fffbeb', noticeLine: '#f6d58a',
            highlightBg: '#fffaf2', rankTopBg: '#eaf1ff', rankBg: '#f5f6f8',
            iconBg: '#f3f5f8', iconText: '#344054',
        },
        dark: {
            canvas: '#0b1220', brand: '#60a5fa', title: '#f2f5f9', muted: '#98a2b3',
            line: '#28324a', rank: '#cbd5e1', barBg: '#1e293b', bar: '#3b82f6',
            barHighlight: '#fbbf24', open: '#4ade80', closed: '#98a2b3', low: '#fbbf24',
            footStrong: '#cbd5e1', notice: '#fbbf24', noticeBg: '#1c1a12', noticeLine: '#5b4a1a',
            highlightBg: '#16233a', rankTopBg: '#1e3a5f', rankBg: '#1a2436',
            iconBg: '#1a2436', iconText: '#cbd5e1',
        },
    };

    function posterPalette(theme) {
        return POSTER_THEMES[theme === 'dark' ? 'dark' : 'light'];
    }

    function posterStyles(p) {
        return '<style>' +
            '.brand{font:800 26px Arial,"Noto Sans",sans-serif;letter-spacing:.08em;fill:' + p.brand + '}' +
            '.title{font:800 48px Arial,"Noto Sans",sans-serif;letter-spacing:-1.5px;fill:' + p.title + '}' +
            '.subtitle{font:400 22px Arial,"Noto Sans",sans-serif;fill:' + p.muted + '}' +
            '.meta{font:600 18px Arial,"Noto Sans",sans-serif;fill:' + p.muted + '}' +
            '.line{stroke:' + p.line + ';stroke-width:2}' +
            '.rank{font:800 19px Arial,"Noto Sans",sans-serif;fill:' + p.rank + '}' +
            '.name{font:750 23px Arial,"Noto Sans",sans-serif;fill:' + p.title + '}' +
            '.org{font:400 17px Arial,"Noto Sans",sans-serif;fill:' + p.muted + '}' +
            '.score{font:800 26px Arial,"Noto Sans",sans-serif;fill:' + p.title + '}' +
            '.score-label{font:600 14px Arial,"Noto Sans",sans-serif;fill:' + p.muted + '}' +
            '.price{font:400 14px Arial,"Noto Sans",sans-serif;fill:' + p.muted + '}' +
            '.bar-bg{fill:' + p.barBg + '}' +
            '.bar{fill:' + p.bar + '}' +
            '.bar-highlight{fill:' + p.barHighlight + '}' +
            '.badge-open{font:700 13px Arial,"Noto Sans",sans-serif;fill:' + p.open + '}' +
            '.badge-closed{font:600 13px Arial,"Noto Sans",sans-serif;fill:' + p.closed + '}' +
            '.badge-low{font:600 12px Arial,"Noto Sans",sans-serif;fill:' + p.low + '}' +
            '.foot{font:400 14px Arial,"Noto Sans",sans-serif;fill:' + p.muted + '}' +
            '.foot-strong{font:700 14px Arial,"Noto Sans",sans-serif;fill:' + p.footStrong + '}' +
            '.notice{font:650 15px Arial,"Noto Sans",sans-serif;fill:' + p.notice + '}' +
            '.empty{font:600 22px Arial,"Noto Sans",sans-serif;fill:' + p.muted + '}' +
        '</style>';
    }

    function iconMarkup(row, x, y, size, p) {
        var radius = size / 2;
        var href = row.icon ? ' href="' + escapeXml(row.icon) + '"' : '';
        return '<circle cx="' + (x + radius) + '" cy="' + (y + radius) + '" r="' + radius + '" fill="' + p.iconBg + '"/>' +
            (href ? '<image x="' + x + '" y="' + y + '" width="' + size + '" height="' + size + '" preserveAspectRatio="xMidYMid meet"' + href + '/>' : '') +
            '<text x="' + (x + radius) + '" y="' + (y + radius + 8) + '" text-anchor="middle" font-family="Arial,sans-serif" font-size="22" font-weight="700" fill="' + p.iconText + '"' + (href ? ' opacity="0"' : '') + '>' + escapeXml(row.initial) + '</text>';
    }

    function renderPoster(data) {
        var w = data.width;
        var h = data.height;
        var p = posterPalette(data.theme);
        var left = 72;
        var right = w - 72;
        var isPortrait = data.orientation === 'portrait';
        var rankX = left + 23;
        var iconX = left + 66;
        var nameX = left + (isPortrait ? 141 : 136);
        var scoreX = right;
        var barX = isPortrait ? nameX : 710;
        var barWidth = isPortrait ? w - barX - 180 : w - barX - 230;
        var out = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 ' + w + ' ' + h + '" width="' + w + '" height="' + h + '" role="img" aria-label="' + escapeXml(data.title) + '">' + posterStyles(p);
        out += '<rect width="' + w + '" height="' + h + '" fill="' + p.canvas + '"/>';
        out += '<text class="brand" x="' + left + '" y="55">' + escapeXml(root.LLM_SITE_NAME || 'LLM Leaderboard') + '</text>';
        out += '<text class="meta" x="' + right + '" y="55" text-anchor="end">' + escapeXml(data.scope) + '</text>';
        out += '<text class="title" x="' + left + '" y="119">' + escapeXml(data.title) + '</text>';
        out += '<text class="subtitle" x="' + left + '" y="159">' + escapeXml(data.scoreLabel) + ' · ' + escapeXml(translate('share_poster_count', { count: data.count }, '前 {count} 名')) + '</text>';
        out += '<line class="line" x1="' + left + '" y1="190" x2="' + right + '" y2="190"/>';
        if (data.empty) {
            out += '<text class="empty" x="' + (w / 2) + '" y="' + (data.headerHeight + 75) + '" text-anchor="middle">' + escapeXml(translate('share_no_models', {}, '当前筛选范围没有可分享的已评分模型')) + '</text>';
        }
        data.rows.forEach(function (row, index) {
            var y = data.headerHeight + index * data.rowHeight;
            var center = y + data.rowHeight / 2;
            var iconSize = isPortrait ? 54 : 50;
            var iconY = center - iconSize / 2;
            var rankRadius = isPortrait ? 19 : 18;
            var barY = isPortrait ? y + 78 : center - 6;
            if (row.highlighted) out += '<rect x="' + (left - 16) + '" y="' + (y + 8) + '" width="' + (right - left + 32) + '" height="' + (data.rowHeight - 16) + '" rx="10" fill="' + p.highlightBg + '"/>';
            out += '<circle cx="' + rankX + '" cy="' + center + '" r="' + rankRadius + '" fill="' + (row.rank <= 3 ? p.rankTopBg : p.rankBg) + '"/>';
            out += '<text class="rank" x="' + rankX + '" y="' + (center + 7) + '" text-anchor="middle">' + row.rank + '</text>';
            out += iconMarkup(row, iconX, iconY, iconSize, p);
            out += '<text class="name" x="' + nameX + '" y="' + (y + (isPortrait ? 29 : 27) ) + '">' + escapeXml(row.name) + '</text>';
            var orgY = y + (isPortrait ? 56 : 51);
            out += '<text class="org" x="' + nameX + '" y="' + orgY + '">' + escapeXml(row.org);
            out += '<tspan class="' + (row.open ? 'badge-open' : 'badge-closed') + '" dx="12">' + escapeXml(row.open ? translate('share_open_weights', {}, '开放权重') : translate('share_closed_weights', {}, '闭源')) + '</tspan>';
            if (row.lowSample) out += '<tspan class="badge-low" dx="12">' + escapeXml(translate('share_low_sample', {}, '低样本')) + '</tspan>';
            out += '</text>';
            out += '<rect class="bar-bg" x="' + barX + '" y="' + barY + '" width="' + Math.max(40, barWidth) + '" height="12" rx="6"/>';
            out += '<rect class="' + (row.highlighted ? 'bar-highlight' : 'bar') + '" x="' + barX + '" y="' + barY + '" width="' + Math.max(28, barWidth * row.bar) + '" height="12" rx="6"/>';
            out += '<text class="score-label" x="' + scoreX + '" y="' + (y + (isPortrait ? 28 : 25)) + '" text-anchor="end">' + escapeXml(data.scoreLabel) + '</text>';
            out += '<text class="score" x="' + scoreX + '" y="' + (y + (isPortrait ? 61 : 55)) + '" text-anchor="end">' + escapeXml(row.scoreText) + '</text>';
            if (data.showPrices) {
                var priceText = translate('share_price_line', { input: row.inputPrice, output: row.outputPrice }, '输入 {input} · 输出 {output}');
                if (row.inputPrice !== '—' || row.outputPrice !== '—') out += '<text class="price" x="' + nameX + '" y="' + (y + data.rowHeight - 9) + '">' + escapeXml(priceText) + '</text>';
            }
            out += '<line class="line" x1="' + left + '" y1="' + (y + data.rowHeight) + '" x2="' + right + '" y2="' + (y + data.rowHeight) + '"/>';
        });
        var footerY = data.headerHeight + data.rows.length * data.rowHeight + 29;
        out += '<text class="foot" x="' + left + '" y="' + footerY + '">' + escapeXml(data.sourceLabel) + '</text>';
        out += '<text class="foot" x="' + right + '" y="' + footerY + '" text-anchor="end">' + escapeXml(translate('share_snapshot', { date: data.dateText }, '数据快照：{date}')) + '</text>';
        out += '<text class="foot-strong" x="' + left + '" y="' + (footerY + 31) + '">' + escapeXml(translate('share_bar_note', {}, '条形仅表示本图内相对位置，不代表百分比。')) + '</text>';
        if (data.previewFixture) {
            var fixtureLines = data.fixtureLines && data.fixtureLines.length ? data.fixtureLines : wrapText(data.fixtureNote, isPortrait ? 72 : 112);
            var noticeHeight = 18 + fixtureLines.length * 20;
            out += '<rect x="' + left + '" y="' + (footerY + 49) + '" width="' + (right - left) + '" height="' + noticeHeight + '" rx="8" fill="' + p.noticeBg + '" stroke="' + p.noticeLine + '"/>';
            fixtureLines.forEach(function (line, index) {
                out += '<text class="notice" x="' + (left + 16) + '" y="' + (footerY + 76 + index * 20) + '">' + escapeXml(line) + '</text>';
            });
        }
        return out + '</svg>';
    }

    function iconDataUrl(svg) {
        return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
    }

    function loadIcon(model) {
        var url = modelIconUrl(model);
        if (!url || typeof root.fetch !== 'function') return Promise.resolve('');
        if (iconPromises[model.id]) return iconPromises[model.id];
        iconPromises[model.id] = root.fetch(url, { credentials: 'same-origin' }).then(function (response) {
            if (!response.ok) throw new Error('icon ' + response.status);
            return response.text();
        }).then(iconDataUrl).catch(function () { return ''; });
        return iconPromises[model.id];
    }

    function loadIcons(models) {
        return Promise.all((Array.isArray(models) ? models : []).map(function (model) {
            return loadIcon(model).then(function (data) { return [model.id, data]; });
        })).then(function (entries) {
            var result = {};
            entries.forEach(function (entry) { if (entry[1]) result[entry[0]] = entry[1]; });
            return result;
        });
    }

    function toPng(svg, width, height) {
        return new Promise(function (resolve, reject) {
            if (!root.XMLSerializer || !root.Image || !root.document || !root.URL || !root.URL.createObjectURL) {
                reject(new Error('PNG export is not supported'));
                return;
            }
            var clone = svg.cloneNode(true);
            clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
            clone.setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
            clone.setAttribute('width', width);
            clone.setAttribute('height', height);
            var source = new root.XMLSerializer().serializeToString(clone);
            var url = root.URL.createObjectURL(new Blob([source], { type: 'image/svg+xml;charset=utf-8' }));
            var image = new root.Image();
            image.onload = function () {
                var canvas = root.document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                var ctx = canvas.getContext('2d');
                if (!ctx) { root.URL.revokeObjectURL(url); reject(new Error('Canvas unavailable')); return; }
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, width, height);
                ctx.drawImage(image, 0, 0, width, height);
                root.URL.revokeObjectURL(url);
                canvas.toBlob(function (blob) {
                    if (blob) resolve(blob); else reject(new Error('PNG conversion failed'));
                }, 'image/png');
            };
            image.onerror = function () { root.URL.revokeObjectURL(url); reject(new Error('SVG rendering failed')); };
            image.src = url;
        });
    }

    var api = {
        rankModels: rankModels,
        buildPosterData: buildPosterData,
        renderPoster: renderPoster,
        formatPrice: price,
        toPng: toPng,
    };

    if (!root.document) return api;

    function init() {
        var modal = root.document.getElementById('llm-share-modal');
        var openButton = root.document.getElementById('llm-share-open');
        var preview = root.document.getElementById('llm-share-preview');
        var countInput = root.document.getElementById('llm-share-count');
        var countOutput = root.document.getElementById('llm-share-count-value');
        var highlight = root.document.getElementById('llm-share-highlight');
        var prices = root.document.getElementById('llm-share-prices');
        var size = root.document.getElementById('llm-share-size');
        var status = root.document.getElementById('llm-share-status');
        var scope = root.document.getElementById('llm-share-scope');
        if (!modal || !openButton || !preview || !countInput || !highlight || !prices) return;

        var state = { orientation: 'landscape', theme: 'light', count: 10, highlight: '', showPrices: false, iconMap: {}, version: 0 };
        var previousFocus = null;
        var savedOverflow = '';
        var ui = root.LLMUI || { visible: (root.LLM_BOARD || {}).models || [], state: {} };
        var language = (root.LLM_VIEW_META || {}).lang || 'zh-CN';
        var board = root.LLM_BOARD || {};
        var copyButton = modal.querySelector('[data-share-action="copy"]');
        var systemButton = modal.querySelector('[data-share-action="system"]');
        var downloadButton = modal.querySelector('[data-share-action="download"]');

        function visibleModels() {
            var list = ui && Array.isArray(ui.visible) ? ui.visible : (board.models || []);
            var field = scoreField(list);
            return list.filter(function (model) { return finiteScore(model, field) !== null; });
        }

        function selectedModels() {
            return rankModels(visibleModels(), state.count);
        }

        function setStatus(message, kind) {
            status.textContent = message || '';
            status.className = 'llm-share-status' + (kind ? ' is-' + kind : '');
        }

        function updateCountLimit() {
            var total = visibleModels().length;
            var max = Math.max(1, Math.min(MAX_MODELS, total || MAX_MODELS));
            countInput.max = String(max);
            state.count = Math.max(1, Math.min(max, Number(state.count) || Math.min(10, max)));
            countInput.value = String(state.count);
            countOutput.textContent = translate('share_count_value', { count: state.count, total: total }, state.count + ' / ' + total);
            modal.querySelectorAll('[data-share-count]').forEach(function (button) {
                var target = Math.min(max, Number(button.dataset.shareCount));
                button.classList.toggle('is-active', target === state.count);
                button.disabled = target > max;
            });
        }

        function updateHighlightOptions() {
            var models = visibleModels();
            var old = state.highlight;
            var selected = selectedModels();
            highlight.innerHTML = '<option value="">' + escapeXml(translate('share_highlight_none', {}, '不高亮')) + '</option>';
            models.forEach(function (model) {
                var option = root.document.createElement('option');
                option.value = model.id;
                option.textContent = model.display_name || model.id;
                highlight.appendChild(option);
            });
            var selectedIds = selected.map(function (model) { return model.id; });
            if (old && selectedIds.indexOf(old) === -1) state.highlight = '';
            highlight.value = state.highlight;
        }

        function buildContext() {
            var currentState = (ui && ui.state) || {};
            var scopeText = translate('share_scope_filtered', {}, '当前筛选范围');
            if (currentState.q) scopeText += ' · ' + translate('share_search_scope', { query: trimLabel(currentState.q, 20) }, '搜索：{query}');
            if (currentState.weights && currentState.weights !== 'all') {
                var weightKey = currentState.weights === 'open' ? 'share_open_weights' : currentState.weights === 'closed' ? 'share_closed_weights' : 'share_unknown_weights';
                scopeText += ' · ' + translate(weightKey, {}, currentState.weights);
            }
            return {
                language: language,
                scope: scopeText,
                generatedAt: board.generated_at,
                previewFixture: board.preview_fixture === true,
                fixtureNote: board.fixture_note || '',
                iconMap: state.iconMap,
            };
        }

        function render() {
            updateCountLimit();
            updateHighlightOptions();
            var models = visibleModels();
            var data = buildPosterData(models, state, buildContext());
            var svgText = renderPoster(data);
            preview.innerHTML = svgText;
            preview.classList.toggle('is-portrait', state.orientation === 'portrait');
            size.textContent = data.width + ' × ' + data.height + ' px';
            scope.textContent = translate('share_scope_count', { count: data.count, total: data.total }, '将导出 {count} 个模型（筛选范围共 {total} 个）');
            var disabled = data.empty;
            copyButton.disabled = disabled;
            downloadButton.disabled = disabled;
            if (systemButton) systemButton.hidden = !(root.navigator && typeof root.navigator.share === 'function');
            if (data.empty) setStatus(translate('share_no_models', {}, '当前筛选范围没有可分享的已评分模型'), 'error');
            else if (!status.classList.contains('is-success')) setStatus('', '');
            var version = ++state.version;
            loadIcons(models).then(function (map) {
                if (version !== state.version) return;
                state.iconMap = Object.assign({}, state.iconMap, map);
                var latest = buildPosterData(visibleModels(), state, buildContext());
                preview.innerHTML = renderPoster(latest);
                preview.classList.toggle('is-portrait', state.orientation === 'portrait');
            });
        }

        function open() {
            if (openButton.disabled || visibleModels().length === 0) return;
            previousFocus = root.document.activeElement;
            updateCountLimit();
            state.count = Math.min(10, Number(countInput.max));
            countInput.value = String(state.count);
            state.highlight = '';
            highlight.value = '';
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            savedOverflow = root.document.body.style.overflow;
            root.document.body.style.overflow = 'hidden';
            render();
            var first = modal.querySelector('button:not([hidden]), input, select');
            if (first) first.focus();
        }

        function close() {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            root.document.body.style.overflow = savedOverflow;
            if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
        }

        function download(blob) {
            var url = root.URL.createObjectURL(blob);
            var link = root.document.createElement('a');
            link.href = url;
            link.download = 'llm-leaderboard-' + state.orientation + '-'
                + (state.theme === 'dark' ? 'dark' : 'light') + '.png';
            root.document.body.appendChild(link);
            link.click();
            link.remove();
            root.setTimeout(function () { root.URL.revokeObjectURL(url); }, 1500);
            setStatus(translate('share_downloaded', {}, '图片已下载'), 'success');
        }

        function exportCurrent() {
            var svg = preview.querySelector('svg');
            var data = buildPosterData(visibleModels(), state, buildContext());
            if (!svg || data.empty) return Promise.reject(new Error('No poster'));
            setStatus(translate('share_generating', {}, '正在生成图片…'), '');
            return loadIcons(selectedModels()).then(function (map) {
                state.iconMap = Object.assign({}, state.iconMap, map);
                data = buildPosterData(visibleModels(), state, buildContext());
                preview.innerHTML = renderPoster(data);
                return api.toPng(preview.querySelector('svg'), data.width, data.height);
            });
        }

        function action(type) {
            if (type === 'download') {
                exportCurrent().then(download).catch(function () { setStatus(translate('share_export_error', {}, '生成图片失败，请重试。'), 'error'); });
            } else if (type === 'copy') {
                exportCurrent().then(function (blob) {
                    if (!root.navigator.clipboard || !root.ClipboardItem) throw new Error('Clipboard image unavailable');
                    return root.navigator.clipboard.write([new root.ClipboardItem({ 'image/png': blob })]);
                }).then(function () { setStatus(translate('share_copied', {}, '图片已复制到剪贴板'), 'success'); }).catch(function () { setStatus(translate('share_copy_error', {}, '当前浏览器不支持复制图片，请改用下载。'), 'error'); });
            } else if (type === 'system') {
                exportCurrent().then(function (blob) {
                    var file = new root.File([blob], 'llm-leaderboard.png', { type: 'image/png' });
                    return root.navigator.share({ title: translate('share_poster_title', {}, '大模型排行榜'), text: translate('share_system_text', {}, 'LLM Leaderboard 大模型排行榜'), files: [file] });
                }).then(function () { setStatus(translate('share_shared', {}, '已打开系统分享'), 'success'); }).catch(function (error) {
                    if (error && error.name === 'AbortError') return;
                    setStatus(translate('share_export_error', {}, '生成图片失败，请重试。'), 'error');
                });
            }
        }

        openButton.addEventListener('click', open);
        modal.querySelectorAll('[data-share-close]').forEach(function (element) { element.addEventListener('click', close); });
        modal.querySelectorAll('[data-share-orientation]').forEach(function (button) {
            button.addEventListener('click', function () {
                state.orientation = button.dataset.shareOrientation === 'portrait' ? 'portrait' : 'landscape';
                modal.querySelectorAll('[data-share-orientation]').forEach(function (item) { item.classList.toggle('is-active', item === button); });
                render();
            });
        });
        modal.querySelectorAll('[data-share-theme]').forEach(function (button) {
            button.addEventListener('click', function () {
                state.theme = button.dataset.shareTheme === 'dark' ? 'dark' : 'light';
                modal.querySelectorAll('[data-share-theme]').forEach(function (item) { item.classList.toggle('is-active', item === button); });
                render();
            });
        });
        countInput.addEventListener('input', function () { state.count = Number(countInput.value); render(); });
        modal.querySelectorAll('[data-share-count]').forEach(function (button) {
            button.addEventListener('click', function () { state.count = Number(button.dataset.shareCount); render(); });
        });
        highlight.addEventListener('change', function () { state.highlight = highlight.value; render(); });
        prices.addEventListener('change', function () { state.showPrices = prices.checked; render(); });
        modal.querySelectorAll('[data-share-action]').forEach(function (button) { button.addEventListener('click', function () { action(button.dataset.shareAction); }); });
        root.document.addEventListener('keydown', function (event) {
            if (modal.hidden) return;
            if (event.key === 'Escape') { event.preventDefault(); close(); return; }
            if (event.key !== 'Tab') return;
            var focusable = Array.from(modal.querySelectorAll('button:not([disabled]):not([hidden]), input:not([disabled]), select:not([disabled]), summary'));
            if (!focusable.length) return;
            var first = focusable[0], last = focusable[focusable.length - 1];
            if (event.shiftKey && root.document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && root.document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
        root.document.addEventListener('llm:change', function () { ui = root.LLMUI || ui; if (!modal.hidden) render(); });
        updateCountLimit();
    }

    if (root.document.readyState === 'loading') root.document.addEventListener('DOMContentLoaded', init);
    else init();
    return api;
}));
