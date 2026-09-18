<?php
/**
 * LLM model alias table: canonical id -> upstream source names.
 * Consumed by scripts/sync-llm-leaderboard.php; add an entry per model.
 * Key 'aa' takes the Artificial Analysis slug (matched exactly by slug/UUID).
 * Entries with an empty alias list fall back to the canonical display name.
 */

return array (
  'anthropic/claude-opus-4.5' =>
  array (
    'display_name' => 'Claude Opus 4.5',
    'org' => 'Anthropic',
    'icon' => 'anthropic',
    'lmarena' =>
    array (
      0 => 'claude-opus-4-5-high',
      0 => 'claude-opus-4-5',
    ),
    'openrouter' =>
    array (
      0 => 'anthropic/claude-opus-4.5',
    ),
    'aa' =>
    array (
      0 => 'claude-opus-4-5',
    ),
  ),
  'google/gemini-2.5-pro' =>
  array (
    'display_name' => 'Gemini 2.5 Pro',
    'org' => 'Google',
    'icon' => 'gemini',
    'lmarena' =>
    array (
      0 => 'gemini-2.5-pro',
    ),
    'openrouter' =>
    array (
      0 => 'google/gemini-2.5-pro',
    ),
    'aa' =>
    array (
      0 => 'gemini-2-5-pro',
    ),
  ),
  'deepseek/deepseek-v3.2' =>
  array (
    'display_name' => 'DeepSeek V3.2',
    'org' => 'DeepSeek',
    'icon' => 'deepseek',
    'lmarena' =>
    array (
      0 => 'deepseek-v3-2',
    ),
    'openrouter' =>
    array (
      0 => 'deepseek/deepseek-v3.2',
    ),
    'aa' =>
    array (
      0 => 'deepseek-v3-2',
    ),
  ),
  'z-ai/glm-5' =>
  array (
    'display_name' => 'GLM-5',
    'org' => 'Z AI',
    'icon' => 'zhipu',
    'lmarena' =>
    array (
      0 => 'glm-5',
    ),
    'openrouter' =>
    array (
      0 => 'z-ai/glm-5',
    ),
    'aa' =>
    array (
      0 => 'glm-5',
    ),
  ),
  'z-ai/glm-4.6' =>
  array (
    'display_name' => 'GLM-4.6',
    'org' => 'Z AI',
    'icon' => 'zhipu',
    'lmarena' =>
    array (
      0 => 'glm-4-6',
    ),
    'openrouter' =>
    array (
      0 => 'z-ai/glm-4.6',
    ),
    'aa' =>
    array (
      0 => 'glm-4-6',
    ),
  ),
  'moonshotai/kimi-k2.5' =>
  array (
    'display_name' => 'Kimi K2.5',
    'org' => 'Moonshot AI',
    'icon' => 'moonshot',
    'lmarena' =>
    array (
      0 => 'kimi-k2-5',
    ),
    'openrouter' =>
    array (
      0 => 'moonshotai/kimi-k2.5',
    ),
    'aa' =>
    array (
      0 => 'kimi-k2-5',
    ),
  ),
  'openai/gpt-5.1' =>
  array (
    'display_name' => 'GPT-5.1',
    'org' => 'OpenAI',
    'icon' => 'openai',
    'lmarena' =>
    array (
      0 => 'gpt-5-1',
    ),
    'openrouter' =>
    array (
      0 => 'openai/gpt-5.1',
    ),
    'aa' =>
    array (
      0 => 'gpt-5-1',
    ),
  ),
  'openai/gpt-5.2-chat' =>
  array (
    'display_name' => 'GPT-5.2 Chat',
    'org' => 'OpenAI',
    'icon' => 'openai',
    'lmarena' =>
    array (
    ),
    'openrouter' =>
    array (
      0 => 'openai/gpt-5.2-chat',
    ),
    'aa' =>
    array (
    ),
  ),
  'x-ai/grok-4.20' =>
  array (
    'display_name' => 'Grok 4.20',
    'org' => 'xAI',
    'icon' => 'xai',
    'lmarena' =>
    array (
      0 => 'grok-4-20',
    ),
    'openrouter' =>
    array (
      0 => 'x-ai/grok-4.20',
    ),
    'aa' =>
    array (
      0 => 'grok-4-20',
    ),
  ),
  'qwen/qwen3-max' =>
  array (
    'display_name' => 'Qwen3 Max',
    'org' => 'Alibaba',
    'icon' => 'qwen',
    'lmarena' =>
    array (
      0 => 'qwen3-max',
    ),
    'openrouter' =>
    array (
      0 => 'qwen/qwen3-max',
    ),
    'aa' =>
    array (
      0 => 'qwen3-max',
    ),
  ),
  'mistralai/mistral-large' =>
  array (
    'display_name' => 'Mistral Large',
    'org' => 'Mistral',
    'icon' => 'mistral',
    'lmarena' =>
    array (
    ),
    'openrouter' =>
    array (
      0 => 'mistralai/mistral-large-2512',
      0 => 'mistralai/mistral-large',
    ),
    'aa' =>
    array (
      0 => 'mistral-large-3',
      0 => 'mistral-large',
    ),
  ),
  'mistralai/mistral-medium-3.5' =>
  array (
    'display_name' => 'Mistral Medium 3.5',
    'org' => 'Mistral',
    'icon' => 'mistral',
    'lmarena' =>
    array (
    ),
    'openrouter' =>
    array (
      0 => 'mistralai/mistral-medium-3-5',
    ),
    'aa' =>
    array (
      0 => 'mistral-medium-3-5',
    ),
  ),
  'anthropic/claude-opus-5' => 
  array (
    'display_name' => 'Claude Opus 5',
    'org' => 'Anthropic',
    'icon' => 'anthropic',
    'lmarena' => 
    array (
      0 => 'claude-opus-5-max',
      1 => 'claude-opus-5-high',
    ),
    'openrouter' => 
    array (
      0 => 'anthropic/claude-opus-5',
    ),
    'aa' => 
    array (
      0 => 'claude-opus-5',
    ),
  ),
  'anthropic/claude-fable-5' => 
  array (
    'display_name' => 'Claude Fable 5',
    'org' => 'Anthropic',
    'icon' => 'anthropic',
    'lmarena' => 
    array (
      0 => 'claude-fable-5',
    ),
    'openrouter' => 
    array (
      0 => 'anthropic/claude-fable-5',
    ),
    'aa' => 
    array (
      0 => 'claude-fable-5',
    ),
  ),
  'anthropic/claude-fable-5.1' => 
  array (
    'display_name' => 'Claude Fable 5.1',
    'org' => 'Anthropic',
    'icon' => 'anthropic',
    'lmarena' => 
    array (
      0 => 'claude-fable-5.1',
      1 => 'claude-fable-5-1',
      2 => 'claude-fable-5.1-max',
    ),
    'openrouter' => 
    array (
      0 => 'anthropic/claude-fable-5.1',
    ),
    'aa' => 
    array (
      0 => 'claude-fable-5-1',
    ),
  ),
  'anthropic/claude-opus-4.8' => 
  array (
    'display_name' => 'Claude Opus 4.8',
    'org' => 'Anthropic',
    'icon' => 'anthropic',
    'lmarena' => 
    array (
      0 => 'claude-opus-4-8-high',
      1 => 'claude-opus-4-8',
    ),
    'openrouter' => 
    array (
      0 => 'anthropic/claude-opus-4.8',
    ),
    'aa' => 
    array (
      0 => 'claude-opus-4-8',
    ),
  ),
  'anthropic/claude-opus-4.7' => 
  array (
    'display_name' => 'Claude Opus 4.7',
    'org' => 'Anthropic',
    'icon' => 'anthropic',
    'lmarena' => 
    array (
      0 => 'claude-opus-4-7-high',
      1 => 'claude-opus-4-7',
    ),
    'openrouter' => 
    array (
      0 => 'anthropic/claude-opus-4.7',
    ),
    'aa' => 
    array (
      0 => 'claude-opus-4-7',
    ),
  ),
  'anthropic/claude-opus-4.6' => 
  array (
    'display_name' => 'Claude Opus 4.6',
    'org' => 'Anthropic',
    'icon' => 'anthropic',
    'lmarena' => 
    array (
      0 => 'claude-opus-4-6-high',
      1 => 'claude-opus-4-6',
    ),
    'openrouter' => 
    array (
      0 => 'anthropic/claude-opus-4.6',
    ),
    'aa' => 
    array (
      0 => 'claude-opus-4-6-adaptive',
      1 => 'claude-opus-4-6',
    ),
  ),
  'anthropic/claude-sonnet-5' => 
  array (
    'display_name' => 'Claude Sonnet 5',
    'org' => 'Anthropic',
    'icon' => 'anthropic',
    'lmarena' => 
    array (
      0 => 'claude-sonnet-5-high',
    ),
    'openrouter' => 
    array (
      0 => 'anthropic/claude-sonnet-5',
    ),
    'aa' => 
    array (
      0 => 'claude-sonnet-5',
    ),
  ),
  'anthropic/claude-sonnet-4.6' => 
  array (
    'display_name' => 'Claude Sonnet 4.6',
    'org' => 'Anthropic',
    'icon' => 'anthropic',
    'lmarena' => 
    array (
      0 => 'claude-sonnet-4-6',
    ),
    'openrouter' => 
    array (
      0 => 'anthropic/claude-sonnet-4.6',
    ),
    'aa' => 
    array (
      0 => 'claude-sonnet-4-6',
    ),
  ),
  'openai/gpt-5.6-sol' => 
  array (
    'display_name' => 'GPT-5.6 Sol',
    'org' => 'OpenAI',
    'icon' => 'openai',
    'lmarena' => 
    array (
      0 => 'gpt-5.6-sol-xhigh',
    ),
    'openrouter' => 
    array (
      0 => 'openai/gpt-5.6-sol',
    ),
    'aa' => 
    array (
      0 => 'gpt-5-6-sol',
    ),
  ),
  'openai/gpt-5.6-terra' => 
  array (
    'display_name' => 'GPT-5.6 Terra',
    'org' => 'OpenAI',
    'icon' => 'openai',
    'lmarena' => 
    array (
      0 => 'gpt-5.6-terra-xhigh',
    ),
    'openrouter' => 
    array (
      0 => 'openai/gpt-5.6-terra',
    ),
    'aa' => 
    array (
      0 => 'gpt-5-6-terra',
    ),
  ),
  'openai/gpt-5.6-luna' => 
  array (
    'display_name' => 'GPT-5.6 Luna',
    'org' => 'OpenAI',
    'icon' => 'openai',
    'lmarena' => 
    array (
      0 => 'gpt-5.6-luna-xhigh',
    ),
    'openrouter' => 
    array (
      0 => 'openai/gpt-5.6-luna',
    ),
    'aa' => 
    array (
      0 => 'gpt-5-6-luna',
    ),
  ),
  'openai/gpt-5.5' => 
  array (
    'display_name' => 'GPT-5.5',
    'org' => 'OpenAI',
    'icon' => 'openai',
    'lmarena' => 
    array (
      0 => 'gpt-5.5-high',
      1 => 'gpt-5.5',
    ),
    'openrouter' => 
    array (
      0 => 'openai/gpt-5.5',
    ),
    'aa' => 
    array (
      0 => 'gpt-5-5',
    ),
  ),
  'openai/gpt-5.4' => 
  array (
    'display_name' => 'GPT-5.4',
    'org' => 'OpenAI',
    'icon' => 'openai',
    'lmarena' => 
    array (
      0 => 'gpt-5.4-high',
      1 => 'gpt-5.4',
    ),
    'openrouter' => 
    array (
      0 => 'openai/gpt-5.4',
    ),
    'aa' => 
    array (
      0 => 'gpt-5-4',
    ),
  ),
  'openai/gpt-5.2' => 
  array (
    'display_name' => 'GPT-5.2',
    'org' => 'OpenAI',
    'icon' => 'openai',
    'lmarena' => 
    array (
      0 => 'gpt-5.2-high',
      1 => 'gpt-5.2',
    ),
    'openrouter' => 
    array (
      0 => 'openai/gpt-5.2',
    ),
    'aa' => 
    array (
      0 => 'gpt-5-2',
    ),
  ),
  'google/gemini-3.7-flash' => 
  array (
    'display_name' => 'Gemini 3.7 Flash',
    'org' => 'Google',
    'icon' => 'gemini',
    'lmarena' => 
    array (
      0 => 'gemini-3.7-flash-high',
    ),
    'openrouter' => 
    array (
      0 => 'google/gemini-3.7-flash',
    ),
    'aa' => 
    array (
      0 => 'gemini-3-7-flash',
    ),
  ),
  'google/gemini-3.1-pro-preview' => 
  array (
    'display_name' => 'Gemini 3.1 Pro Preview',
    'org' => 'Google',
    'icon' => 'gemini',
    'lmarena' => 
    array (
      0 => 'gemini-3.1-pro-preview',
    ),
    'openrouter' => 
    array (
      0 => 'google/gemini-3.1-pro-preview',
    ),
    'aa' => 
    array (
      0 => 'gemini-3-1-pro-preview',
    ),
  ),
  'google/gemini-3.5-flash' => 
  array (
    'display_name' => 'Gemini 3.5 Flash',
    'org' => 'Google',
    'icon' => 'gemini',
    'lmarena' => 
    array (
      0 => 'gemini-3.5-flash-high',
      1 => 'gemini-3.5-flash-medium',
    ),
    'openrouter' => 
    array (
      0 => 'google/gemini-3.5-flash',
    ),
    'aa' => 
    array (
      0 => 'gemini-3-5-flash',
    ),
  ),
  'google/gemini-3-flash' => 
  array (
    'display_name' => 'Gemini 3 Flash',
    'org' => 'Google',
    'icon' => 'gemini',
    'lmarena' => 
    array (
      0 => 'gemini-3-flash',
      1 => 'gemini-3-flash (thinking-minimal)',
    ),
    'openrouter' => 
    array (
      0 => 'google/gemini-3-flash-preview',
    ),
    'aa' => 
    array (
      0 => 'gemini-3-flash',
    ),
  ),
  'x-ai/grok-4.6' => 
  array (
    'display_name' => 'Grok 4.6',
    'org' => 'xAI',
    'icon' => 'xai',
    'lmarena' => 
    array (
      0 => 'grok-4.6-high',
    ),
    'openrouter' => 
    array (
      0 => 'x-ai/grok-4.6',
    ),
    'aa' => 
    array (
      0 => 'grok-4-6',
    ),
  ),
  'x-ai/grok-4.5' => 
  array (
    'display_name' => 'Grok 4.5',
    'org' => 'xAI',
    'icon' => 'xai',
    'lmarena' => 
    array (
      0 => 'grok-4.5',
    ),
    'openrouter' => 
    array (
      0 => 'x-ai/grok-4.5',
    ),
    'aa' => 
    array (
      0 => 'grok-4-5',
    ),
  ),
  'meta/muse-spark-1.2' => 
  array (
    'display_name' => 'Muse Spark 1.2',
    'org' => 'Meta',
    'icon' => 'meta',
    'lmarena' => 
    array (
      0 => 'muse-spark-1.2 (xHigh)',
    ),
    'openrouter' => 
    array (
      0 => 'meta/muse-spark-1.2',
    ),
    'aa' => 
    array (
      0 => 'muse-spark-1-2',
    ),
  ),
  'meta/muse-spark-1.1' => 
  array (
    'display_name' => 'Muse Spark 1.1',
    'org' => 'Meta',
    'icon' => 'meta',
    'lmarena' => 
    array (
      0 => 'muse-spark-1.1',
    ),
    'openrouter' => 
    array (
      0 => 'meta/muse-spark-1.1',
    ),
    'aa' => 
    array (
      0 => 'muse-spark-1-1',
    ),
  ),
  'z-ai/glm-5.3-flash' => 
  array (
    'display_name' => 'GLM-5.3 Flash',
    'org' => 'Z.AI',
    'icon' => 'zhipu',
    'lmarena' => 
    array (
      0 => 'glm-5.3-flash',
    ),
    'openrouter' => 
    array (
      0 => 'z-ai/glm-5.3-flash',
    ),
    'aa' => 
    array (
      0 => 'glm-5-3-flash',
    ),
  ),
  'z-ai/glm-5.3' => 
  array (
    'display_name' => 'GLM-5.3',
    'org' => 'Z.AI',
    'icon' => 'zhipu',
    'lmarena' => 
    array (
      0 => 'glm-5.3-max',
    ),
    'openrouter' => 
    array (
      0 => 'z-ai/glm-5.3',
    ),
    'aa' => 
    array (
      0 => 'glm-5-3',
    ),
  ),
  'z-ai/glm-5.2' => 
  array (
    'display_name' => 'GLM-5.2',
    'org' => 'Z.AI',
    'icon' => 'zhipu',
    'lmarena' => 
    array (
      0 => 'glm-5.2-max',
    ),
    'openrouter' => 
    array (
      0 => 'z-ai/glm-5.2',
    ),
    'aa' => 
    array (
      0 => 'glm-5-2',
    ),
  ),
  'z-ai/glm-5.1' => 
  array (
    'display_name' => 'GLM-5.1',
    'org' => 'Z.AI',
    'icon' => 'zhipu',
    'lmarena' => 
    array (
      0 => 'glm-5.1',
    ),
    'openrouter' => 
    array (
      0 => 'z-ai/glm-5.1',
    ),
    'aa' => 
    array (
      0 => 'glm-5-1',
    ),
  ),
  'moonshotai/kimi-k3' => 
  array (
    'display_name' => 'Kimi K3',
    'org' => 'Moonshot AI',
    'icon' => 'moonshot',
    'lmarena' => 
    array (
      0 => 'kimi-k3-max',
    ),
    'openrouter' => 
    array (
      0 => 'moonshotai/kimi-k3',
    ),
    'aa' => 
    array (
      0 => 'kimi-k3',
    ),
  ),
  'moonshotai/kimi-k2.7-code' => 
  array (
    'display_name' => 'Kimi K2.7 Code',
    'org' => 'Moonshot AI',
    'icon' => 'moonshot',
    'lmarena' => 
    array (
    ),
    'openrouter' => 
    array (
      0 => 'moonshotai/kimi-k2.7-code',
    ),
    'aa' => 
    array (
      0 => 'kimi-k2-7-code',
    ),
  ),
  'moonshotai/kimi-k2.6' => 
  array (
    'display_name' => 'Kimi K2.6',
    'org' => 'Moonshot AI',
    'icon' => 'moonshot',
    'lmarena' => 
    array (
      0 => 'kimi-k2.6',
    ),
    'openrouter' => 
    array (
      0 => 'moonshotai/kimi-k2.6',
    ),
    'aa' => 
    array (
      0 => 'kimi-k2-6',
    ),
  ),
  'deepseek/deepseek-v4-pro' => 
  array (
    'display_name' => 'DeepSeek V4 Pro',
    'org' => 'DeepSeek',
    'icon' => 'deepseek',
    'lmarena' => 
    array (
      0 => 'deepseek-v4-pro',
      1 => 'deepseek-v4-pro-high-preview',
      2 => 'deepseek-v4-pro-high-20260813',
    ),
    'openrouter' => 
    array (
      0 => 'deepseek/deepseek-v4-pro',
      1 => 'deepseek/deepseek-v4-pro-0813',
    ),
    'aa' => 
    array (
      0 => 'deepseek-v4-pro',
    ),
  ),
  'deepseek/deepseek-v4-flash' => 
  array (
    'display_name' => 'DeepSeek V4 Flash',
    'org' => 'DeepSeek',
    'icon' => 'deepseek',
    'lmarena' => 
    array (
      0 => 'deepseek-v4-flash',
      1 => 'deepseek-v4-flash-high-preview',
    ),
    'openrouter' => 
    array (
      0 => 'deepseek/deepseek-v4-flash',
      1 => 'deepseek/deepseek-v4-flash-0731',
    ),
    'aa' => 
    array (
      0 => 'deepseek-v4-flash',
    ),
  ),
  'deepseek/deepseek-v4.1-flash' => 
  array (
    'display_name' => 'DeepSeek V4.1 Flash',
    'org' => 'DeepSeek',
    'icon' => 'deepseek',
    'lmarena' => 
    array (
    ),
    'openrouter' => 
    array (
      0 => 'deepseek/deepseek-v4.1-flash',
    ),
    'aa' => 
    array (
      0 => 'deepseek-v4-1-flash',
    ),
  ),
  'qwen/qwen3.8-max' => 
  array (
    'display_name' => 'Qwen3.8 Max',
    'org' => 'Alibaba',
    'icon' => 'qwen',
    'lmarena' => 
    array (
      0 => 'qwen3.8-max',
    ),
    'openrouter' => 
    array (
      0 => 'qwen/qwen3.8-max-0902',
      1 => 'qwen/qwen3.8-max',
    ),
    'aa' => 
    array (
      0 => 'qwen3-8-max',
    ),
  ),
  'qwen/qwen3.7-max' => 
  array (
    'display_name' => 'Qwen3.7 Max',
    'org' => 'Alibaba',
    'icon' => 'qwen',
    'lmarena' => 
    array (
      0 => 'qwen3.7-max-preview',
    ),
    'openrouter' => 
    array (
      0 => 'qwen/qwen3.7-max',
    ),
    'aa' => 
    array (
      0 => 'qwen3-7-max',
    ),
  ),
  'qwen/qwen3.7-plus' => 
  array (
    'display_name' => 'Qwen3.7 Plus',
    'org' => 'Alibaba',
    'icon' => 'qwen',
    'lmarena' => 
    array (
      0 => 'qwen3.7-plus',
    ),
    'openrouter' => 
    array (
      0 => 'qwen/qwen3.7-plus',
    ),
    'aa' => 
    array (
      0 => 'qwen3-7-plus',
    ),
  ),
  'qwen/qwen3.6-max' => 
  array (
    'display_name' => 'Qwen3.6 Max',
    'org' => 'Alibaba',
    'icon' => 'qwen',
    'lmarena' => 
    array (
      0 => 'qwen3.6-max-preview',
    ),
    'openrouter' => 
    array (
      0 => 'qwen/qwen3.6-max-preview',
    ),
    'aa' => 
    array (
      0 => 'qwen3-6-max',
    ),
  ),
  'minimax/minimax-m3' => 
  array (
    'display_name' => 'MiniMax M3',
    'org' => 'MiniMax',
    'icon' => 'minimax',
    'lmarena' => 
    array (
      0 => 'minimax-m3',
    ),
    'openrouter' => 
    array (
      0 => 'minimax/minimax-m3',
    ),
    'aa' => 
    array (
      0 => 'minimax-m3',
    ),
  ),
  'xiaomi/mimo-v2.5-pro' => 
  array (
    'display_name' => 'MiMo V2.5 Pro',
    'org' => 'Xiaomi',
    'icon' => 'xiaomi',
    'lmarena' => 
    array (
      0 => 'mimo-v2.5-pro',
    ),
    'openrouter' => 
    array (
      0 => 'xiaomi/mimo-v2.5-pro',
    ),
    'aa' => 
    array (
      0 => 'mimo-v2-5-pro',
    ),
  ),
  'xiaomi/mimo-v2.5' => 
  array (
    'display_name' => 'MiMo V2.5',
    'org' => 'Xiaomi',
    'icon' => 'xiaomi',
    'lmarena' => 
    array (
      0 => 'mimo-v2.5',
    ),
    'openrouter' => 
    array (
      0 => 'xiaomi/mimo-v2.5',
    ),
    'aa' => 
    array (
      0 => 'mimo-v2-5-0424',
    ),
  ),
  'tencent/hy3' => 
  array (
    'display_name' => 'Hy3',
    'org' => 'Tencent',
    'icon' => 'tencent',
    'lmarena' => 
    array (
      0 => 'hy3',
    ),
    'openrouter' => 
    array (
      0 => 'tencent/hy3',
    ),
    'aa' => 
    array (
      0 => 'hy3',
    ),
  ),
  'bytedance-seed/seed-2.1-turbo' => 
  array (
    'display_name' => 'Seed 2.1 Turbo',
    'org' => 'ByteDance',
    'icon' => 'doubao',
    'lmarena' => 
    array (
    ),
    'openrouter' => 
    array (
      0 => 'bytedance-seed/seed-2-1-turbo',
    ),
    'aa' => 
    array (
    ),
  ),
  'google/gemini-3.8-flash' => 
  array (
    'display_name' => 'Gemini 3.8 Flash',
    'org' => 'Google',
    'icon' => 'gemini',
    'lmarena' => 
    array (
      0 => 'gemini-3.8-flash-high',
    ),
    'openrouter' => 
    array (
      0 => 'google/gemini-3.8-flash',
    ),
    'aa' => 
    array (
      0 => 'gemini-3-8-flash',
    ),
  ),
  'google/gemini-3-pro' => 
  array (
    'display_name' => 'Gemini 3 Pro',
    'org' => 'Google',
    'icon' => 'gemini',
    'lmarena' => 
    array (
      0 => 'gemini-3-pro',
    ),
    'openrouter' => 
    array (
    ),
    'aa' => 
    array (
      0 => 'gemini-3-pro',
    ),
  ),
  'openai/gpt-6-astra' => 
  array (
    'display_name' => 'GPT-6 Astra',
    'org' => 'OpenAI',
    'icon' => 'openai',
    'lmarena' => 
    array (
    ),
    'openrouter' => 
    array (
      0 => 'openai/gpt-6-astra',
    ),
    'aa' => 
    array (
      0 => 'gpt-6-astra',
    ),
  ),
  'openai/gpt-6-astra-pro' => 
  array (
    'display_name' => 'GPT-6 Astra Pro',
    'org' => 'OpenAI',
    'icon' => 'openai',
    'lmarena' => 
    array (
    ),
    'openrouter' => 
    array (
      0 => 'openai/gpt-6-astra-pro',
    ),
    'aa' => 
    array (
    ),
  ),
  'google/gemini-3.6-flash' => 
  array (
    'display_name' => 'Gemini 3.6 Flash',
    'org' => 'Google',
    'icon' => 'gemini',
    'lmarena' => 
    array (
      0 => 'gemini-3.6-flash-high',
    ),
    'openrouter' => 
    array (
      0 => 'google/gemini-3.6-flash',
    ),
    'aa' => 
    array (
      0 => 'gemini-3-6-flash',
    ),
  ),
);
