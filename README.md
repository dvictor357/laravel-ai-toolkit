# Laravel AI Toolkit

A professional, production-ready Laravel package for integrating multiple AI providers with advanced features like async
processing, real-time streaming, intelligent caching, and error handling.

[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=for-the-badge&logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-Pest-brightgreen.svg?style=for-the-badge)](tests/)

## ✨ Features

### 🤖 Multi-Provider AI Support

- **OpenAI** - GPT-4, GPT-3.5, and embeddings
- **Anthropic** - Claude 4 Sonnet, Claude 3 Opus, and more
- **Groq** - Ultra-fast inference with Mixtral and Llama models
- Easy provider switching via configuration

### ⚡ Performance & Reliability

- **Async Queue Jobs** - Non-blocking AI operations
- **Intelligent Caching** - Redis/Database cache with TTL and invalidation
- **Circuit Breaker Pattern** - Automatic failure protection
- **Exponential Backoff** - Smart retry logic with jitter
- **Rate Limiting** - Built-in API rate limit handling

### 🔄 Real-Time Features

- **Streaming Responses** - Real-time AI response streaming
- **Laravel Reverb Integration** - WebSocket broadcasting for live updates
- **Event-Driven Architecture** - Dispatch events for UI updates

### 🔧 Developer Experience

- **Type-Safe Contracts** - Interface-driven provider architecture
- **Comprehensive CLI** - Command-line tools for testing and management
- **Laravel Facades** - Easy dependency injection
- **Rich Configuration** - Environment-based provider settings
- **Extensible Design** - Easy to add new providers

## 📦 Installation

### Requirements

- PHP 8.3 or higher
- Laravel 11 or 12
- Composer

### Install via Composer

```bash
composer require dvictor357/laravel-ai-toolkit
```

### Publish Configuration

```bash
php artisan vendor:publish --provider="AIToolkit\\AIToolkit\\AiToolkitServiceProvider" --tag="config"
```

This creates `config/ai-toolkit.php` where you can configure your providers.

### Environment Variables

Add your API keys to `.env`:

```env
# Default provider
AI_DEFAULT_PROVIDER=openai

# OpenAI
OPENAI_API_KEY=sk-your-openai-key

# Anthropic
ANTHROPIC_API_KEY=sk-ant-your-anthropic-key

# Groq
GROQ_API_KEY=gsk_your-groq-key
```

## 🚀 Quick Start

### Basic Usage

```php
use AIToolkit\AIToolkit\Contracts\AIProviderContract;

// Dependency injection
public function chat(AIProviderContract $provider)
{
    $response = $provider->chat('Tell me a joke about programming');

    return [
        'content' => $response['content'],
        'usage' => $response['usage'], // Token usage stats
        'model' => $response['model'],
    ];
}
```

### Using Facades

```php
use AIToolkit\AIToolkit\Facades\AiCache;

// Cache AI responses
$result = AiCache::remember('chat', 'openai', 'Your prompt', function () {
    return app(AIProviderContract::class)->chat('Your prompt');
});
```

### Async Jobs

```php
use AIToolkit\AIToolkit\Jobs\AiChatJob;

// Dispatch async job
AiChatJob::dispatch('Generate a report about AI trends', [
    'max_tokens' => 2000,
    'temperature' => 0.7
], 'unique-result-id');

// Listen for completion
event(new AiChatCompleted($response, 'unique-result-id'));
```

### Streaming Responses

```php
// Direct streaming
$stream = $provider->stream('Tell me a story about...');

return $stream; // Returns StreamedResponse

// Broadcasting via Reverb
$stream = $provider->streamBroadcast('Your prompt', [], 'my-channel');

// Frontend JavaScript
window.Echo.channel('ai-stream')
    .listen('AiResponseChunk', (e) => {
        if (e.chunk === '__START__') {
            // Stream started
        } else {if (e.chunk === '__END__') {
            // Stream ended
        } else {
            // Append chunk to UI
            document.getElementById('response').innerHTML += e.chunk;
        }}
    });
```

### CLI Usage

```bash
# Basic chat
php artisan ai:chat "What's the weather like today?"

# With options
php artisan ai:chat "Explain quantum computing" \
    --provider=anthropic \
    --model=claude-3-5-sonnet-20241022 \
    --max-tokens=1000 \
    --temperature=0.7

# Streaming response
php artisan ai:chat "Continue this story..." --stream

# JSON output
php artisan ai:chat "List the planets" --json
```

## ⚙️ Configuration

### Provider Settings

```php
// config/ai-toolkit.php
return [
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o'),
            'default_max_tokens' => env('OPENAI_DEFAULT_MAX_TOKENS', 1024),
            'default_temperature' => env('OPENAI_DEFAULT_TEMPERATURE', 0.7),
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-3-5-sonnet-20241022'),
            'default_max_tokens' => env('ANTHROPIC_DEFAULT_MAX_TOKENS', 1024),
            'default_temperature' => env('ANTHROPIC_DEFAULT_TEMPERATURE', 1.0),
        ],

        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
            'default_model' => env('GROQ_DEFAULT_MODEL', 'mixtral-8x7b-32768'),
            'default_max_tokens' => env('GROQ_DEFAULT_MAX_TOKENS', 1024),
            'default_temperature' => env('GROQ_DEFAULT_TEMPERATURE', 0.7),
        ],
    ],
];
```

### Cache Settings

```php
'cache' => [
    'enabled' => env('AI_CACHE_ENABLED', true),
    'ttl' => env('AI_CACHE_TTL', 3600), // 1 hour
    'prefix' => env('AI_CACHE_PREFIX', 'ai_toolkit'),
],
```

### Queue Settings

```php
'queue' => [
    'connection' => env('AI_QUEUE_CONNECTION', null),
    'timeout' => env('AI_QUEUE_TIMEOUT', 60),
    'tries' => env('AI_QUEUE_TRIES', 3),
],
```

### Broadcasting Settings

```php
'broadcasting' => [
    'enabled' => env('AI_BROADCASTING_ENABLED', true),
    'channel' => env('AI_BROADCASTING_CHANNEL', 'ai-stream'),
],
```

## 🏗️ Architecture

### Provider Pattern

All AI providers implement `AIProviderContract`:

```php
interface AIProviderContract
{
    public function chat(string $prompt, array $options = []): array;
    public function stream(string $prompt, array $options = []): \Symfony\Component\HttpFoundation\StreamedResponse;
    public function embed(string $text): array;
}
```

### Service Architecture

```
┌─────────────────┐
│   Controllers   │
└────────┬────────┘
         │
┌────────▼────────┐
│ AIProviderContract │
└────────┬────────┘
         │
    ┌────┴─────┐
    │ Providers │
    ├──────────┤
    │ • OpenAI │
    │ • Claude │
    │ • Groq   │
    └──────────┘
```

### Caching Strategy

```
Request ──┐
          │
          ├─→ Cache Hit ──→ Return Cached Response
          │
          └─→ Cache Miss ──→ Call API ──→ Store & Return
```

### Retry Logic

```
Request ──┐
          │
          ├─→ Success ──→ Return Response
          │
          └─→ Failure ──→ Wait (exponential backoff)
                     │
                     ├─→ Retry (up to max attempts)
                     │
                     └─→ Final Failure
```

## 🧪 Testing

Run the test suite:

```bash
composer test
```

Run with coverage:

```bash
composer test-coverage
```

The package includes:

- **Unit Tests** - Individual component testing
- **Feature Tests** - Integration testing
- **Provider Tests** - Mock API testing
- **Service Tests** - Caching, retry logic, and jobs

## 📚 API Reference

### AIProviderContract

#### `chat(string $prompt, array $options = []): array`

Send a chat message to the AI provider.

**Parameters:**

- `$prompt` - The user message
- `$options` - Additional parameters (model, max_tokens, temperature, etc.)

**Returns:**

```php
[
    'content' => 'AI response text',
    'usage' => [
        'prompt_tokens' => 150,
        'completion_tokens' => 50,
        'total_tokens' => 200,
    ],
    'model' => 'gpt-4o',
]
```

#### `stream(string $prompt, array $options = []): StreamedResponse`

Stream a chat response in real-time.

#### `embed(string $text): array`

Generate text embeddings (OpenAI only).

### AiCacheService

#### `remember(string $operation, string $provider, string $input, callable $callback, array $options = []): mixed`

Cache AI responses with automatic TTL.

#### `generateKey(string $operation, string $provider, string $input, array $options = []): string`

Generate consistent cache keys.

#### `invalidatePattern(string $pattern): int`

Invalidate cache by pattern.

### RetryService

#### `execute(callable $callback, array $options = []): mixed`

Execute operations with retry logic.

**Options:**

- `max_retries` - Maximum retry attempts (default: 3)
- `base_delay` - Base delay in seconds (default: 1)
- `strategy` - 'exponential', 'linear', or 'fixed'
- `circuit_breaker` - Enable circuit breaker (default: true)

## 🔒 Security

- **API Key Validation** - Validates provider keys on initialization
- **Input Sanitization** - All inputs are sanitized before API calls
- **Rate Limiting** - Built-in rate limit handling
- **Error Handling** - No sensitive data in error messages
- **Caching** - Cached responses don't include API keys

## 🚀 Performance Tips

### 1. Enable Caching

```php
config(['ai.cache.enabled' => true]);
```

### 2. Use Async Jobs for Long Operations

```php
AiChatJob::dispatch($prompt, $options, $resultId);
```

### 3. Monitor Cache Hit Rates

```php
$stats = AiCache::getStats();
Log::info('Cache stats', $stats);
```

### 4. Use Circuit Breakers

```php
// Circuit breaker automatically opens after 5 failures
// Resets after 60 seconds
```

## 📖 Advanced Usage

### Custom Providers

Create a custom provider by implementing the contract:

```php
namespace App\AI;

use AIToolkit\AIToolkit\Contracts\AIProviderContract;

class CustomProvider implements AIProviderContract
{
    public function chat(string $prompt, array $options = []): array
    {
        // Your custom implementation
    }

    // ... implement other methods
}
```

Register in your service provider:

```php
$this->app->singleton(AIProviderContract::class, function () {
    return new CustomProvider();
});
```

### Event Listeners

```php
// Listen for AI chat completion
Event::listen(AiChatCompleted::class, function (AiChatCompleted $event) {
    if ($event->failed) {
        Log::error('AI chat failed', $event->broadcastWith());
    } else {
        Log::info('AI chat completed', $event->broadcastWith());
    }
});
```

### Custom Caching

```php
$cacheService = app('ai-cache');

// Pre-warm cache
$cacheService->warmCache([
    [
        'prompt' => 'What is Laravel?',
        'operations' => ['chat'],
        'options' => ['temperature' => 0.7]
    ]
]);
```

## 🐛 Troubleshooting

### Common Issues

**1. "Invalid AI provider" Error**

- Check your `ai.default_provider` configuration
- Ensure the provider name matches exactly: 'openai', 'anthropic', or 'groq'

**2. API Key Not Found**

- Verify environment variables are set
- Check for typos in variable names
- Ensure the config file is published

**3. Streaming Not Working**

- Enable broadcasting in config
- Set up Laravel Reverb or Pusher
- Check browser console for WebSocket errors

**4. Queue Jobs Not Running**

- Ensure queue driver is configured
- Run `php artisan queue:work`
- Check Laravel logs for errors

### Debug Mode

Enable detailed logging:

```php
config([
    'ai.logging.enabled' => true,
    'ai.logging.channel' => 'stack',
    'app.debug' => true,
]);
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

### Development Setup

```bash
git clone https://github.com/dvictor357/laravel-ai-toolkit.git
cd laravel-ai-toolkit
composer install
cp .env.example .env
php artisan key:generate
composer test
```

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## 🆘 Support

- 📧 Email: dvictor3579@gmail.com
- 🐛 Issues: [GitHub Issues](https://github.com/dvictor357/laravel-ai-toolkit/issues)

## 🙏 Acknowledgments

- [OpenAI](https://openai.com) for their excellent APIs
- [Anthropic](https://anthropic.com) for Claude
- [Groq](https://groq.com) for lightning-fast inference
- [Laravel](https://laravel.com) for the amazing framework

---

**Made with ❤️ for the Laravel community**
