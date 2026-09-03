<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Router & LLM Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to OpenAI-compatible AI routers such as
    | OpenRouter, OpenCode, OpenAI, Groq, Ollama, DeepSeek, etc.
    |
    */

    'base_url' => env('AI_BASE_URL', 'https://openrouter.ai/api/v1'),

    'api_key' => env('AI_API_KEY', ''),

    'model' => env('AI_MODEL', 'google/gemini-2.5-flash'),

    'timeout' => (int) env('AI_TIMEOUT', 60),

    'temperature' => (float) env('AI_TEMPERATURE', 0.7),

    'site_url' => env('APP_URL', 'http://localhost:8000'),

    'site_name' => env('APP_NAME', 'Medina Warehouse'),
];
