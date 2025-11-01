<?php

namespace AIToolkit\AIToolkit\Providers;

use AIToolkit\AIToolkit\Contracts\AIProviderContract;
use AIToolkit\AIToolkit\Services\SecurityService;
use AIToolkit\AIToolkit\Traits\BroadcastsAIStream;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use OpenAI;
use OpenAI\Client;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpenAiProvider implements AIProviderContract
{
    use BroadcastsAIStream;

    private Client $client;

    private SecurityService $securityService;

    public function __construct()
    {
        $apiKey = config('ai.providers.openai.api_key');

        if (! $apiKey) {
            throw new InvalidArgumentException(
                'OpenAI API key is required. Please set OPENAI_API_KEY in your .env file.');
        }

        // Initialize security service
        $this->securityService = app('ai-security');

        // Validate API key if configured
        if (config('ai.providers.openai.validate_on_init', true) &&
            config('ai.security.api_key_validation', true)) {

            $validation = $this->securityService->validateApiKey('openai', $apiKey);

            if (! $validation['valid']) {
                Log::warning('OpenAI API key validation failed', $validation);

                if (config('ai.security.log_security_events', true)) {
                    $this->securityService->logSecurityEvent('api_key_validation_failed', [
                        'provider' => 'openai',
                        'error' => $validation['error'],
                    ]);
                }

                // In production, you might want to throw an exception
                // throw new InvalidArgumentException('OpenAI API key validation failed: ' . $validation['error']);
            }
        }

        $this->client = OpenAI::client($apiKey);
    }

    public function stream(string $prompt, array $options = []): StreamedResponse
    {
        $defaultModel = config('ai.providers.openai.default_model', 'gpt-4o');
        $defaultMaxTokens = config('ai.providers.openai.default_max_tokens', 1024);
        $defaultTemperature = config('ai.providers.openai.default_temperature', 0.7);

        return response()->stream(
            function () use ($prompt, $options, $defaultModel, $defaultMaxTokens, $defaultTemperature) {
                $payload = array_merge(['model' => $defaultModel,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => $defaultMaxTokens,
                    'temperature' => $defaultTemperature, ], $options);

                $stream = $this->client->chat()->createStreamed($payload);
                foreach ($stream as $response) {
                    echo $response->choices[0]->delta->content ?? '';
                    flush();
                }
            });
    }

    public function chat(string $prompt, array $options = []): array
    {
        // Apply security checks
        $this->applySecurityChecks('chat');

        // Sanitize input if configured
        if (config('ai.security.input_sanitization', true)) {
            $sanitizedOptions = $this->sanitizeOptions($options);
            $prompt = $this->securityService->sanitizeInput($prompt, [
                'max_length' => config('ai.security.max_input_length', 32000),
                'remove_control_chars' => true,
            ]);
            $options = $sanitizedOptions;
        }

        $defaultModel = config('ai.providers.openai.default_model', 'gpt-4o');
        $defaultMaxTokens = config('ai.providers.openai.default_max_tokens', 1024);
        $defaultTemperature = config('ai.providers.openai.default_temperature', 0.7);

        $cacheKey = $this->getCacheKey('chat', $prompt, $options);

        if (config('ai.cache.enabled')) {
            return Cache::remember(
                $cacheKey,
                config('ai.cache.ttl'),
                function () use ($prompt, $options, $defaultModel, $defaultMaxTokens, $defaultTemperature) {
                    return $this->makeChatRequest(
                        $prompt,
                        $options,
                        $defaultModel,
                        $defaultMaxTokens,
                        $defaultTemperature);
                });
        }

        return $this->makeChatRequest($prompt, $options, $defaultModel, $defaultMaxTokens, $defaultTemperature);
    }

    private function getCacheKey(string $operation, string $input, array $options = []): string
    {
        $prefix = config('ai.cache.prefix', 'ai_toolkit');
        $hash = md5($input.serialize($options));

        return "{$prefix}:openai:{$operation}:{$hash}";
    }

    private function makeChatRequest(
        string $prompt,
        array $options,
        string $model,
        int $maxTokens,
        float $temperature,
    ): array {
        $payload = array_merge(['model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature, ], $options);

        $response = $this->client->chat()->create($payload);

        return ['content' => $response->choices[0]->message->content,
            'usage' => ['prompt_tokens' => $response->usage->promptTokens ?? null,
                'completion_tokens' => $response->usage->completionTokens ?? null,
                'total_tokens' => $response->usage->totalTokens ?? null, ],
            'model' => $response->model ?? $model, ];
    }

    public function streamBroadcast(string $prompt, array $options = [], ?string $channel = null): StreamedResponse
    {
        $defaultModel = config('ai.providers.openai.default_model', 'gpt-4o');
        $defaultMaxTokens = config('ai.providers.openai.default_max_tokens', 1024);
        $defaultTemperature = config('ai.providers.openai.default_temperature', 0.7);

        $payload = array_merge(['model' => $defaultModel,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $defaultMaxTokens,
            'temperature' => $defaultTemperature, ], $options);

        return $this->streamWithBroadcast($payload, function ($payload, $onChunk) {
            $stream = $this->client->chat()->createStreamed($payload);
            foreach ($stream as $response) {
                $chunk = $response->choices[0]->delta->content ?? '';
                $onChunk($chunk);
            }
        }, $channel);
    }

    public function embed(string $text): array
    {
        $cacheKey = $this->getCacheKey('embed', $text);

        if (config('ai.cache.enabled')) {
            return Cache::remember($cacheKey, config('ai.cache.ttl'), function () use ($text) {
                return $this->makeEmbedRequest($text);
            });
        }

        return $this->makeEmbedRequest($text);
    }

    private function makeEmbedRequest(string $text): array
    {
        $response = $this->client->embeddings()->create(['model' => 'text-embedding-3-small',
            'input' => $text, ]);

        return ['embedding' => $response->embeddings[0]->embedding,
            'model' => $response->model,
            'usage' => ['prompt_tokens' => $response->usage->promptTokens ?? null,
                'total_tokens' => $response->usage->totalTokens ?? null, ], ];
    }

    /**
     * Apply security checks (rate limiting)
     */
    private function applySecurityChecks(string $operation): void
    {
        if (! config('ai.security.rate_limiting', true)) {
            return;
        }

        $rateLimitCheck = $this->securityService->checkRateLimit('openai');

        if (! $rateLimitCheck['allowed']) {
            $this->securityService->logSecurityEvent('rate_limit_exceeded', [
                'provider' => 'openai',
                'operation' => $operation,
                'current' => $rateLimitCheck['current'],
                'max' => $rateLimitCheck['max'],
            ]);

            throw new \RuntimeException(
                "Rate limit exceeded for OpenAI. Current: {$rateLimitCheck['current']}/{$rateLimitCheck['max']}"
            );
        }

        // Increment rate limit counter
        $this->securityService->incrementRateLimit('openai');
    }

    /**
     * Sanitize options array
     */
    private function sanitizeOptions(array $options): array
    {
        $sanitized = [];

        foreach ($options as $key => $value) {
            // Sanitize string values
            if (is_string($value)) {
                $sanitized[$key] = $this->securityService->sanitizeInput($value, [
                    'max_length' => 1000,
                    'remove_control_chars' => true,
                ]);
            } else {
                $sanitized[$key] = $value;
            }
        }

        // Ensure temperature is within valid bounds
        if (isset($sanitized['temperature'])) {
            $sanitized['temperature'] = max(0.0, min(2.0, (float) $sanitized['temperature']));
        }

        // Ensure max_tokens is within valid bounds
        if (isset($sanitized['max_tokens'])) {
            $sanitized['max_tokens'] = max(1, min(4096, (int) $sanitized['max_tokens']));
        }

        return $sanitized;
    }
}
