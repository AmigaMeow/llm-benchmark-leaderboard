# 文件分类清单 — llm-benchmark-leaderboard（Phase 1 产出，待确认）

审查对象：私有仓库 `AmigaMeow/17nas.com`（PHP 8.1 原生 + MariaDB，SSR + JSON 快照）。
**本清单确认前不进行任何代码剥离。** 上游数据条款核查见 `docs/UPSTREAM-TOS.md`。

## 一、可开源（直接或极小改动即可入库）

### 核心业务逻辑
| 文件 | 说明 |
|---|---|
| `includes/llm-board-data.php` | 榜单数据整形/排序/变体展开，纯函数 0 外部依赖（有 tests 对拍） |
| `includes/llm-board-views.php` | 多视图渲染（开源/编程/性价比/最新/速度） |
| `includes/og-image.php` | 模型分享卡 slug 规则 + 兜底逻辑，纯函数 |
| `includes/head-meta.php` / `breadcrumb.php` / `language-switcher.php` / `version.php` / `scripts.php` | 通用 chrome 小组件 |
| `config/llm_model_aliases.php` | 模型别名映射，纯数据无密钥 |
| `config/llm_sources.php` | **已实测是加载器**：从 `/etc/17nas/` 或 env 读 key，本身无字面密钥 — 可作为安全模式范例开源 |
| `config/llm_sources.example.php` | 配置模板（站点既有 example 惯例） |

### 前端资源
| 文件 | 说明 |
|---|---|
| `assets/js/llm-leaderboard.js` / `llm-pareto-tip.js` / `llm-share-image.js` / `llm-events.js` | 榜单交互、帕累托提示、分享卡、埋点（llm-events 只记次数不记身份，符合规范） |
| `assets/js/html2canvas.min.js` | MIT，分享卡截图依赖 |
| `assets/css/llm-leaderboard.css` / `llm-leaderboard.v2.css` / `llm-share-image.css` | 样式 |
| 通用 chrome 资源（同 CPU 仓库：`layout.css`、`main.js` 改造版、`mobile-sidebar.*`、`swup-transitions.css`、logo/favicon/og 兜底图） | 与 CPU 仓库共用改造方案 |

### 测试与样例
| 文件 | 说明 |
|---|---|
| `tests/llm-board-data.php` / `llm-board-data.cjs` / `llm-board-http.cjs` / `llm-share-image.cjs` / `llm-chart-exploration.cjs` | 数据整形对拍测试 — 开源后就是现成的测试套件 |
| `tests/llm-board-*-fixture.json` | 测试 fixture，模型公开数据 |
| `.hoplite/fixtures/llm-leaderboard.sample.json`（34KB，58 模型） | 现成示例快照 — **须逐字段确认无内部注释字段后使用** |

## 二、需改造（剥离后可入库）

| 文件 | 需要剥掉什么 |
|---|---|
| `llm-leaderboard.php`（76KB） | chrome 依赖（`admin/utils/Database.php` 间接来自 header/footer/I18n）、登录/VIP 入口、商业链接；保留 i18n 与视图逻辑主体 |
| `llm-model.php` | 同上 |
| `includes/header.php` / `footer.php` / `mobile-nav.php` / `mobile-sidebar.php` / `footer-stats.php` / `I18n.php` / `settings.php` | 与 CPU 仓库同一套改造（剥 auth/VIP/带货/adsense；I18n+settings 需独立模式）。**两个仓库方案应保持一致** |
| `scripts/sync-llm-leaderboard.php` | 改写：默认「缺 key 跳过而非报错」、上游 ToS 未核实源默认禁用（AA 已是 `show_aa=false`）、缓存路径相对化、不要写死站点目录 |
| `scripts/seed-llm-leaderboard-i18n.php` | 依赖 `translations` 表 — 随 i18n 模式决策一起定 |
| `tools/generate-og-image.php` | og 卡生成器，含站点路径假设 → 改造后随附（可选功能） |
| `assets/js/main.js` | 与 CPU 仓库同一改造（删带货 fetch） |
| `sql/schema.sql`（新增） | LLM 榜单当前读 JSON 快照而非 DB — 若开源版引入 i18n/设置表，只开 `translations`/`translation_texts`/`seo_translations`/`site_settings` 四张纯文案表 |
| 示例数据 | 用 `.hoplite` fixture 或 sync 脚本公开源（OpenRouter/HF）跑一份 — **AA/LMArena 条款未核实前不附全量快照**（见 UPSTREAM-TOS.md） |

## 三、禁止开源

与 CPU 仓库同一红线，另加：

| 文件 | 原因 |
|---|---|
| `cache/llm/raw-*.json`（`raw-lmarena.json`、`raw-openrouter.json`、`raw-aa.json`）、`cache/llm/history/` | 上游原始快照 = 第三方数据再分发，ToS 未核实 |
| `cache/llm/llm-leaderboard.json`（生产全量快照） | 合并产物同样含上游数据；只给示例文件 |
| `tools/benchmark-runner.sh`、`cdp-probe.mjs`、`render-page-check.mjs`、`gen_promo_xlsx*.py` | 内部工具 |
| `admin/api/ai/*`、`includes/AiClient.php` | AI 配置与密钥调用 |

## 四、待用户确认

1. **示例数据来源**：用 `.hoplite` 那份 58 模型样例（已存在、已是 fixture 定位）还是只给 10-20 条精简版？
2. **og 分享卡生成器**（`tools/generate-og-image.php` + `assets/images/og/models/` 产物）：开源版带上还是只留兜底卡？
3. **i18n 模式**：与 CPU 仓库同一问题 — 语言文件简化版 vs DB 方案+种子，两仓需统一。
