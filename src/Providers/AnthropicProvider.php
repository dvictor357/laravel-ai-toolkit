<?php

namespace AIToolkit\AIToolkit\Providers;

use AIToolkit\AIToolkit\Contracts\AIProviderContract;
use AIToolkit\AIToolkit\Traits\BroadcastsAIStream;
use Anthropic\Client;
use Anthropic\Messages\MessageParam;
use Anthropic\Messages\Model;
use Exception;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnthropicProvider implements AIProviderContract
{
    use BroadcastsAIStream;

    private Client $client;

    public function __construct()
    {
        $apiKey = config('ai.providers.anthropic.api_key');

        if (! $apiKey) {
            throw new InvalidArgumentException(
                'Anthropic API key is required. Please set ANTHROPIC_API_KEY in your .env file.');
        }

        $this->client = new Client(apiKey: $apiKey);
    }

    public function chat(string $prompt, array $options = []): array
    {
        $defaultModel = config('ai.providers.anthropic.default_model', Model::CLAUDE_4_SONNET_20250514);
        $defaultMaxTokens = config('ai.providers.anthropic.default_max_tokens', 1024);
        $defaultTemperature = config('ai.providers.anthropic.default_temperature', 1.0);

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

        return "{$prefix}:anthropic:{$operation}:{$hash}";
    }

    private function makeChatRequest(
        string $prompt,
        array $options,
        string $model,
        int $maxTokens,
        float $temperature,
    ): array {
        $payload = ['max_tokens' => $options['max_tokens'] ?? $maxTokens,
            'messages' => [MessageParam::with(content: $prompt, role: 'user')],
            'model' => $options['model'] ?? $model,
            'temperature' => $options['temperature'] ?? $temperature, ];

        $response = $this->client->messages->create($payload);

        return ['content' => $response->content[0]->text,
            'usage' => ['prompt_tokens' => $response->usage->inputTokens ?? null,
                'completion_tokens' => $response->usage->outputTokens ?? null,
                'total_tokens' => ($response->usage->inputTokens ?? 0) + ($response->usage->outputTokens ?? 0), ],
            'model' => $response->model ?? $model, ];
    }

    public function stream(string $prompt, array $options = []): StreamedResponse
    {
        $defaultModel = config('ai.providers.anthropic.default_model', Model::CLAUDE_4_SONNET_20250514);
        $defaultMaxTokens = config('ai.providers.anthropic.default_max_tokens', 1024);
        $defaultTemperature = config('ai.providers.anthropic.default_temperature', 1.0);

        return response()->stream(
            function () use ($prompt, $options, $defaultModel, $defaultMaxTokens, $defaultTemperature) {
                $payload = ['max_tokens' => $options['max_tokens'] ?? $defaultMaxTokens,
                    'messages' => [MessageParam::with(content: $prompt, role: 'user')],
                    'model' => $options['model'] ?? $defaultModel,
                    'temperature' => $options['temperature'] ?? $defaultTemperature, ];

                $stream = $this->client->messages->createStream($payload);

                foreach ($stream as $event) {
                    if (isset($event->delta) && $event->delta->type === 'text_delta') {
                        echo $event->delta->text;
                        flush();
                    }
                }
            });
    }

    public function streamBroadcast(string $prompt, array $options = [], ?string $channel = null): StreamedResponse
    {
        $defaultModel = config('ai.providers.anthropic.default_model', Model::CLAUDE_4_SONNET_20250514);
        $defaultMaxTokens = config('ai.providers.anthropic.default_max_tokens', 1024);
        $defaultTemperature = config('ai.providers.anthropic.default_temperature', 1.0);

        $payload = ['max_tokens' => $options['max_tokens'] ?? $defaultMaxTokens,
            'messages' => [MessageParam::with(content: $prompt, role: 'user')],
            'model' => $options['model'] ?? $defaultModel,
            'temperature' => $options['temperature'] ?? $defaultTemperature, ];

        return $this->streamWithBroadcast($payload, function ($payload, $onChunk) {
            $stream = $this->client->messages->createStream($payload);

            foreach ($stream as $event) {
                if (isset($event->delta) && $event->delta->type === 'text_delta') {
                    $onChunk($event->delta->text);
                }
            }
        }, $channel);
    }

    public function embed(string $text): array
    {
        throw new Exception(
            'Anthropic does not provide native text embeddings. Consider using OpenAI for embeddings.');
    }
}
