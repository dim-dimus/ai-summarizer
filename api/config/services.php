<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM (provider-agnostic summarization client)
    |--------------------------------------------------------------------------
    |
    | Selection is config-driven: the LlmClient interface is bound to the
    | adapter named by `llm.provider`. Adapters translate to/from vendor wire
    | formats and return { text, model, inputTokens, outputTokens }.
    | `rates` are USD per 1,000,000 tokens, keyed by model id, used to compute
    | cost_usd from reported usage.
    |
    */
    'llm' => [
        'provider' => env('LLM_PROVIDER', 'anthropic'),

        // Hard cap on source tokens fed to the model (input is truncated to this).
        'max_input_tokens' => (int) env('MAX_INPUT_TOKENS', 12000),

        // Per-style output caps (max_tokens) and prompt copy live in SummarizerService.
        'rates' => [
            // $1 / $5 per 1M in/out tokens (Anthropic Haiku 4.5 reference).
            'claude-haiku-4-5-20251001' => ['input' => 1.00, 'output' => 5.00],
            'claude-sonnet-4-6' => ['input' => 3.00, 'output' => 15.00],
        ],

        'anthropic' => [
            'key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Server-side URL fetching (SSRF-guarded content extraction)
    |--------------------------------------------------------------------------
    */
    'fetch' => [
        'timeout_seconds' => (int) env('FETCH_TIMEOUT_SECONDS', 10),
        'max_bytes' => (int) env('FETCH_MAX_BYTES', 2000000),
        'max_redirects' => (int) env('FETCH_MAX_REDIRECTS', 3),
        'user_agent' => env('FETCH_USER_AGENT', 'AISummarizerBot/1.0 (+server-side fetch)'),
    ],

    'summaries' => [
        'rate_limit_per_hour' => (int) env('RATE_LIMIT_PER_HOUR', 20),
    ],

];
