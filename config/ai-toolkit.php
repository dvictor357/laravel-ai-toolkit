<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This is the default AI provider that will be used when none is specified.
    | Supported providers: 'openai', 'anthropic', 'groq'
    |
    */

    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your AI providers here. Each provider requires an API key
    | which should be stored in your .env file for security.
    |
    */

    'providers' => [

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o'),
            'default_max_tokens' => env('OPENAI_DEFAULT_MAX_TOKENS', 1024),
            'default_temperature' => env('OPENAI_DEFAULT_TEMPERATURE', 0.7),
            // Security settings
            'rate_limit_max_requests' => env('OPENAI_RATE_LIMIT_MAX_REQUESTS', 100),
            'rate_limit_window' => env('OPENAI_RATE_LIMIT_WINDOW', 3600), // 1 hour
            'validate_on_init' => env('OPENAI_VALIDATE_ON_INIT', true),
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-3-5-sonnet-20241022'),
            'default_max_tokens' => env('ANTHROPIC_DEFAULT_MAX_TOKENS', 1024),
            'default_temperature' => env('ANTHROPIC_DEFAULT_TEMPERATURE', 1.0),
            // Security settings
            'rate_limit_max_requests' => env('ANTHROPIC_RATE_LIMIT_MAX_REQUESTS', 80),
            'rate_limit_window' => env('ANTHROPIC_RATE_LIMIT_WINDOW', 3600), // 1 hour
            'validate_on_init' => env('ANTHROPIC_VALIDATE_ON_INIT', true),
        ],

        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
            'default_model' => env('GROQ_DEFAULT_MODEL', 'mixtral-8x7b-32768'),
            'default_max_tokens' => env('GROQ_DEFAULT_MAX_TOKENS', 1024),
            'default_temperature' => env('GROQ_DEFAULT_TEMPERATURE', 0.7),
            // Security settings
            'rate_limit_max_requests' => env('GROQ_RATE_LIMIT_MAX_REQUESTS', 30),
            'rate_limit_window' => env('GROQ_RATE_LIMIT_WINDOW', 60), // 1 minute
            'validate_on_init' => env('GROQ_VALIDATE_ON_INIT', true),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for AI responses and embeddings.
    |
    */

    'cache' => [
        'enabled' => env('AI_CACHE_ENABLED', true),
        'ttl' => env('AI_CACHE_TTL', 3600), // Time to live in seconds (default: 1 hour)
        'prefix' => env('AI_CACHE_PREFIX', 'ai_toolkit'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure async job behavior for AI operations.
    |
    */

    'queue' => [
        'connection' => env('AI_QUEUE_CONNECTION', null), // Use default queue if null
        'timeout' => env('AI_QUEUE_TIMEOUT', 60), // Job timeout in seconds
        'tries' => env('AI_QUEUE_TRIES', 3), // Number of retry attempts
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure real-time streaming with Laravel Reverb/Pusher.
    |
    */

    'broadcasting' => [
        'enabled' => env('AI_BROADCASTING_ENABLED', true),
        'channel' => env('AI_BROADCASTING_CHANNEL', 'ai-stream'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for AI operations and errors.
    |
    */

    'logging' => [
        'enabled' => env('AI_LOGGING_ENABLED', true),
        'channel' => env('AI_LOGGING_CHANNEL', 'stack'),
        'log_successful_responses' => env('AI_LOG_SUCCESSFUL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configure security features for AI operations.
    |
    */

    'security' => [
        'input_sanitization' => env('AI_SECURITY_INPUT_SANITIZATION', true),
        'rate_limiting' => env('AI_SECURITY_RATE_LIMITING', true),
        'api_key_validation' => env('AI_SECURITY_API_KEY_VALIDATION', true),
        'prompt_injection_protection' => env('AI_SECURITY_PROMPT_INJECTION_PROTECTION', true),
        'max_input_length' => env('AI_SECURITY_MAX_INPUT_LENGTH', 32000),
        'log_security_events' => env('AI_SECURITY_LOG_EVENTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Configure HTTP routes for AI toolkit endpoints.
    |
    */

    'routes' => [
        'enabled' => env('AI_ROUTES_ENABLED', true),
        'middleware' => env('AI_ROUTES_MIDDLEWARE', ['api']),
        'prefix' => env('AI_ROUTES_PREFIX', 'ai'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Admin Panel Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the Filament Admin Panel integration for AI Toolkit.
    |
    */

    'filament' => [
        'enabled' => env('AI_TOOLKIT_FILAMENT_ENABLED', true),
        'auto_discover' => env('AI_TOOLKIT_FILAMENT_AUTO_DISCOVER', true),
        'panel_path' => env('AI_TOOLKIT_FILAMENT_PATH', 'admin'),
        'middleware' => [
            'auth' => true,
            'verified' => false,
        ],
        'resources' => [
            'ai_provider' => [
                'enabled' => true,
                'model' => \AIToolkit\AIToolkit\Support\AIProviderConfiguration::class,
            ],
        ],
        'pages' => [
            'ai_chat_dashboard' => [
                'enabled' => true,
            ],
        ],
        'widgets' => [
            'usage_stats' => [
                'enabled' => true,
                'cache_duration' => 300, // 5 minutes
            ],
        ],
        'features' => [
            'real_time_chat' => env('AI_TOOLKIT_FILAMENT_REALTIME_CHAT', true),
            'usage_analytics' => env('AI_TOOLKIT_FILAMENT_USAGE_ANALYTICS', true),
            'provider_testing' => env('AI_TOOLKIT_FILAMENT_PROVIDER_TESTING', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Package Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how this package integrates with other systems.
    |
    */

    'integration' => [
        'broadcast_events' => env('AI_TOOLKIT_BROADCAST_EVENTS', true),
        'dispatch_jobs' => env('AI_TOOLKIT_DISPATCH_JOBS', true),
        'monitoring' => env('AI_TOOLKIT_MONITORING', true),
        'notifications' => env('AI_TOOLKIT_NOTIFICATIONS', true),
    ],

];
