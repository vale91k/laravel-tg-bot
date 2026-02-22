<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Client (драйвер)
    |--------------------------------------------------------------------------
    | Какой клиент использовать для ответов бота: deepseek, openai (будущие).
    | Меняя значение, подставляется соответствующая реализация AiClientInterface.
    */
    'client' => env('AI_CLIENT', 'deepseek'),

    /*
    | API ключ провайдера (DeepSeek, OpenAI и т.д.)
    */
    'api_key' => env('AI_API_KEY', ''),

    /*
    | Модель по умолчанию (например deepseek-chat, gpt-4o)
    */
    'model' => env('AI_MODEL', 'deepseek-chat'),

    'temperature' => (float) env('AI_TEMPERATURE', 0.7),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 1000),

];
