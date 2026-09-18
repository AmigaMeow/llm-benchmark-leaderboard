/**
 * LLM 页面事件埋点通用层（仅输出到 gtag/dataLayer，无自研后端）。
 * - GA4 在位（window.gtag）→ gtag('event', name, params)
 * - 否则 → dataLayer push（GA4 未来接入后历史事件也在）→ 再兜底 console.debug
 * 全部用事件委托，不依赖页面 JS 内部结构；SPA/swup 换页也照常工作。
 */
(function () {
  'use strict';
  window.llmTrack = function (name, params) {
    params = params || {};
    if (typeof window.gtag === 'function') {
      window.gtag('event', name, params);
      return;
    }
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(Object.assign({ event: name }, params));
    if (window.console && window.console.debug) {
      window.console.debug('[llm-track]', name, params);
    }
  };

  var track = window.llmTrack;

  function onReady(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  onReady(function () {
    var page = document.querySelector('.llm-leaderboard-page') ? 'leaderboard' :
               (document.querySelector('.llm-model-page') ? 'model' : null);

    // 页面浏览：进入榜单/模型页即算一次
    if (page === 'leaderboard') {
      var view = new URLSearchParams(location.search).get('view') || 'all';
      var lang = new URLSearchParams(location.search).get('lang') || 'zh-CN';
      track('leaderboard_view', { view: view, language: lang });
    } else if (page === 'model') {
      var mId = new URLSearchParams(location.search).get('id') || '';
      track('model_view', { model_id: mId });
    }

    // 视图胶囊（filter_change）——真实 <a>，点完再记
    document.addEventListener('click', function (ev) {
      var a = ev.target.closest ? ev.target.closest('a') : null;
      if (!a) return;

      var pill = a.closest ? a.closest('#llm-views a[data-view]') : null;
      if (pill) {
        track('filter_change', { view: pill.getAttribute('data-view') });
        return;
      }

      var modelLink = a.closest ? a.closest('.llm-model-name a, .llm-detail-link') : null;
      if (modelLink) {
        track('model_open', { target_id: modelLink.getAttribute('href') || '' });
        return;
      }

      if (a.classList.contains('llm-method-link')) {
        track('methodology_open', { from: page || 'other' });
        return;
      }

      if (a.closest && a.closest('.llm-sources-list, .llm-source-list')) {
        track('source_open', { from: page || 'other' });
        return;
      }

      var href = a.getAttribute('href') || '';
      if (/[?&]lang=/.test(href) && page) {
        var m2 = href.match(/[?&]lang=([a-zA-Z-]+)/);
        track('language_change', { to: m2 ? m2[1] : '', from_page: page });
      }
    }, true);

    document.addEventListener('change', function (ev) {
      var control = ev.target;
      if (control && (control.id === 'llm-weights' || control.id === 'llm-sort')) {
        track('filter_change', { filter: control.name, value: control.value });
      }
    }, true);

    // 搜索（防抖 800ms，有输入才记）
    var searchEl = document.getElementById('llm-search');
    if (searchEl) {
      var t = null;
      searchEl.addEventListener('input', function () {
        if (t) clearTimeout(t);
        t = setTimeout(function () {
          var q = searchEl.value.trim();
          if (q.length >= 2) track('search_model', { query_length: q.length });
        }, 800);
      });
    }
  });
})();
