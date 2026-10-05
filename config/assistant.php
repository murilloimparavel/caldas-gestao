<?php

return [
    'enabled' => filter_var(env('ASSISTANT_ENABLED', false), FILTER_VALIDATE_BOOL),
    'retention_days' => (int) env('ASSISTANT_RETENTION_DAYS', 30),
    'max_prompt_length' => (int) env('ASSISTANT_MAX_PROMPT_LENGTH', 4000),
    'max_history_messages' => (int) env('ASSISTANT_MAX_HISTORY_MESSAGES', 20),
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        'endpoint' => env('GROQ_ENDPOINT', 'https://api.groq.com/openai/v1/chat/completions'),
        'timeout' => (int) env('GROQ_TIMEOUT', 20),
        'connect_timeout' => (int) env('GROQ_CONNECT_TIMEOUT', 5),
    ],
];
