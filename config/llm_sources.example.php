<?php
// Copy to config/llm_sources.php (gitignored). Never commit live secrets —
// values can also come from the environment (LLM_AA_API_KEY / LLM_OPENROUTER_KEY / LLM_HF_TOKEN).
//
// 上游数据源：
//   - LMArena (CC-BY-4.0 dataset via HuggingFace datasets-server) — hf_token 可选，无则匿名
//   - OpenRouter — openrouter_key 可选，公开模型列表接口
//   - Artificial Analysis — 默认 show_aa=false。其数据条款需自行阅读
//     docs/UPSTREAM-TOS.md 后，确认允许再分发才把 show_aa 改成 true 并填 aa_api_key。
//     缺 key 或 show_aa=false 时同步脚本自动跳过该源，不报错。
return [
    'aa_api_key'     => getenv('LLM_AA_API_KEY') ?: '',
    'openrouter_key' => getenv('LLM_OPENROUTER_KEY') ?: '',
    'hf_token'       => getenv('LLM_HF_TOKEN') ?: '',
    'show_aa'        => false,
    'aa_endpoint'          => getenv('LLM_AA_ENDPOINT') ?: '',
    'aa_endpoint_fallback' => getenv('LLM_AA_ENDPOINT_FALLBACK') ?: '',
];
