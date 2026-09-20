# 上游数据条款核查

> **核查日期**：2026-09-20
> **状态**：✅ 三家上游已完成核查（原先的「待核查」表格已回填结论）
> **处置**：Artificial Analysis 数据**禁止再分发**，故其采集默认关闭；本仓库仍不附带任何上游全量快照。

## 为什么要单独一个文件

本项目的价值很大程度来自聚合多个上游的评测数据。但「能读到数据」和「能再分发数据」是两件事 ——
把上游数据打包进公开仓库，可能违反其服务条款。

**代码开源不受影响**：本仓库只提供采集脚本与少量示例记录，数据由使用者自行采集。

## 核查结果

| 上游 | 采集方式 | 用途 | 条款状态 | 结论 |
|---|---|---|---|---|
| **Artificial Analysis** | API（需 Key） | 模型评测分数 | ✅ **已核实** | ❌ **禁止再分发** → 采集默认关闭（`show_aa=false`） |
| **LMArena** | HF datasets-server | 人类偏好排名 | ✅ **已核实** | ✅ **CC-BY-4.0，允许再分发**（需署名） |
| **OpenRouter** | 公开 API | 模型列表与定价 | ✅ **已核实** | ⚠️ 未发现禁止条款，**但未获书面许可**（证据强度较弱） |
| **Hugging Face** | datasets-server | 数据集行 | ✅ **已核实** | ✅ 作为托管平台，许可取决于各数据集自身（本处为 CC-BY-4.0） |

---

## 逐家结论

### 一、Artificial Analysis —— ❌ 禁止再分发

**条款来源**：<https://artificialanalysis.ai/terms-of-use>（Last revised: September 15 2026）

> **2.1 License.** … limited license to use and access the Site **solely for your own personal, noncommercial use**.
>
> **2.2 (a)** you shall not license, sell, rent, lease, transfer, assign, **distribute, host, or otherwise commercially exploit** the Site, whether in whole or in part, **or any content displayed on the Site**;
>
> **2.2 (d)** … **no part of the Site may be copied, reproduced, distributed, republished, downloaded, displayed, posted or transmitted in any form or by any means**.

**判定**：将 AA 评分写入公开仓库或公开接口，属于条款明令禁止的 distribute / republish。

**本仓库的处置**：

- 采集脚本中 AA 源**默认关闭**（`show_aa=false`），仅在 `show_aa=true` 且填入 `aa_api_key` 时才请求上游
- 仓库**不附带**任何 AA 数据快照
- 使用者若自行启用，需自行承担条款风险

### 二、LMArena —— ✅ CC-BY-4.0

**依据**：采集脚本使用的数据集在 Hugging Face 上的许可证元数据

```
数据集:  lmarena-ai/leaderboard-dataset
license: cc-by-4.0
gated:   False
```

（同组织的 `lmarena-ai/arena-human-preference-140k` 亦为 `cc-by-4.0`）

**CC-BY-4.0 义务**：署名、提供许可证链接、标明是否修改。**允许**包括商业用途在内的复制与再分发。

**本仓库如何履行**：README 与榜单输出的数据说明中均标注来源为 LMArena。

### 三、OpenRouter —— ⚠️ 未见禁止，但未逐条核实

**条款来源**：<https://openrouter.ai/terms>（Last Updated: August 31, 2026）

通读其 ToS，**未发现**禁止再分发平台内容的表述；平台自身提供公开的模型列表接口（`GET /api/v1/models`），
定价与上下文长度属公开商业信息。

**但必须诚实说明**：未找到禁止 ≠ 明确允许；我们**没有**获得书面许可。
**如对方提出异议，应立即停止分发相关字段。**

### 四、Hugging Face（托管平台）

HF datasets-server 仅为访问通道，**数据集的使用许可取决于数据集自身**。
本处使用的 LMArena 数据集为 CC-BY-4.0，因此通过 HF 获取并再分发该数据集内容，符合其许可。

---

## 核查方法（可复现）

```bash
# 读取数据集许可证（权威来源，优于页面爬取）
curl -s https://huggingface.co/api/datasets/lmarena-ai/leaderboard-dataset \
  | python3 -c "import json,sys; print(json.load(sys.stdin)['cardData']['license'])"

# 抓取并检索条款关键词
curl -s https://artificialanalysis.ai/terms-of-use | grep -oiE '.{0,120}distribut.{0,200}'
```

## 当前立场

- 仓库默认**不含**任何上游全量数据
- 使用者运行采集脚本前，须自行确认其使用场景符合各上游条款
- AA 源默认关闭；LMArena 与 OpenRouter 默认启用（后者证据强度较弱，已标注）

> 本文件是工程侧的边界声明，**不构成法律意见**。涉及商业发布时请咨询专业人士。
> 条款可能随时变更，本核查反映 2026-09-20 的状态。
