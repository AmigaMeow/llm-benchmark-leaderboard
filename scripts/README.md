# scripts/

数据采集与同步脚本。

- 每个上游一个独立脚本，互不依赖
- 缺少 API Key 时应**跳过并给出提示**，而不是中断
- 抓取的原始响应写入 `data/raw/`（已被 `.gitignore` 排除）
- ⚠️ 启用任何上游前先读 [../docs/UPSTREAM-TOS.md](../docs/UPSTREAM-TOS.md)
