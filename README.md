# LLM Benchmark Leaderboard

> 自托管的大模型排行榜 —— PHP 8.1 + MariaDB，多维度视图，服务端渲染，零框架依赖。

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777bb4.svg)](https://www.php.net/)
[![MariaDB 10.6+](https://img.shields.io/badge/MariaDB-10.6%2B-003545.svg)](https://mariadb.org/)

从**开源 / 编程 / 性价比 / 最新 / 速度**等多个维度对比主流大模型。

> 由 [17nas.com](https://17nas.com/) 开源 —— 一个 NAS 与网络工具站。

---

## Status

🚧 **Early development** — 代码正在从上游单体应用中剥离，**当前尚不可运行**。
本仓库先建立骨架与规范；具体实施由 [Issues](../../issues) 跟踪。

## Planned Features

- 排行榜主视图，多维度切换：开源 / 编程 / 性价比 / 最新 / 速度
- 模型详情与对比
- Pareto 前沿提示
- 分享卡片生成
- ECharts 图表，本地打包，不依赖 CDN

## Data Sources & Terms

⚠️ **本仓库不附带任何上游全量数据** —— 只提供采集脚本与少量示例记录。

采集脚本可能涉及以下上游，**其数据再分发条款须由使用者自行核实**：

| 上游 | 用途 | 条款状态 |
|---|---|---|
| Artificial Analysis | 模型评测分数 | ⚠️ 未核实 |
| LMArena | 人类偏好排名 | ⚠️ 未核实 |
| OpenRouter | 模型列表与定价 | ⚠️ 未核实 |
| Hugging Face | 数据集行 | ⚠️ 未核实 |

详见 [docs/UPSTREAM-TOS.md](docs/UPSTREAM-TOS.md)。

## Architecture

```
public/      Web 根，页面入口
scripts/     数据采集与同步脚本
config/      配置模板（只放 *.example.php）
sql/         建表语句（脱敏）
data/        示例数据
docs/        规范与架构说明
```

| 层 | 技术 |
|---|---|
| 后端 | PHP 8.1（无框架） |
| 数据库 | MariaDB 10.6 |
| 前端 | 原生 JS + ECharts（本地打包） |
| 渲染 | PHP 服务端渲染为主 |

## What this project does NOT include

- 注册 / 登录 / 会话 / 验证码
- 会员与定价逻辑
- 支付与订单
- 后台管理端
- 任何真实密钥或凭据
- 部署与运维脚本、Web 服务器配置

## Getting Started

> 尚不可运行 —— 待 `sql/` 与 `public/` 落地后补全安装步骤。

## License

[MIT](LICENSE) © 2026 17nas.com
