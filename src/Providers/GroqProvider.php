<?php

namespace AIToolkit\AIToolkit\Providers;

use AIToolkit\AIToolkit\Contracts\AIProviderContract;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GroqProvider implements AIProviderContract
{
    private const BASE_URL = 'https://api.groq.com/openai/v1';

    public function __construct()
    {
        $apiKey = config('ai.providers.groq.api_key');

        if (! $apiKey) {
            throw new InvalidArgumentException(
                'Groq API key is required. Please set GROQ_API_KEY in your .env file.');
        }

        $this->apiKey = $apiKey;
    }

    public function chat(string $prompt, array $options = []): array
    {
        $defaultModel = config('ai.providers.groq.default_model', 'mixtral-8x7b-32768');
        $defaultMaxTokens = config('ai.providers.groq.default_max_tokens', 1024);
        $defaultTemperature = config('ai.providers.groq.default_temperature', 0.7);

        $payload = array_merge(['model' => $defaultModel,
            'messages' => [['role' => 'user',
                'content' => $prompt]],
            'max_tokens' => $defaultMaxTokens,
            'temperature' => $defaultTemperature, ], $options);

        $cacheKey = $this->getCacheKey('chat', $prompt, $options);

        if (config('ai.cache.enabled')) {
            return Cache::remember($cacheKey, config('ai.cache.ttl'), function () use ($payload) {
                return $this->makeChatRequest($payload);
            });
        }

        return $this->makeChatRequest($payload);
    }

    private function getCacheKey(string $operation, string $prompt, array $options = []): string
    {
        $prefix = config('ai.cache.prefix', 'ai_toolkit');
        $hash = md5($prompt.serialize($options));

        return "{$prefix}:groq:{$operation}:{$hash}";
    }

    private function makeChatRequest(array $payload): array
    {
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json', ])
            ->withOptions(['timeout' => 60])
            ->post(self::BASE_URL.'/chat/completions', $payload);

        if ($response->failed()) {
            throw new Exception('Groq API request failed: '.$response->body());
        }

        $data = $response->json();

        return ['content' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => $data['usage'] ?? null,
            'model' => $data['model'] ?? null, ];
    }

    public function stream(string $prompt, array $options = []): StreamedResponse
    {
        $defaultModel = config('ai.providers.groq.default_model', 'mixtral-8x7b-32768');
        $defaultMaxTokens = config('ai.providers.groq.default_max_tokens', 1024);
        $defaultTemperature = config('ai.providers.groq.default_temperature', 0.7);

        $payload = array_merge(['model' => $defaultModel,
            'messages' => [['role' => 'user',
                'content' => $prompt]],
            'max_tokens' => $defaultMaxTokens,
            'temperature' => $defaultTemperature,
            'stream' => true, ], $options);

        return response()->stream(function () use ($payload) {
            $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json', ])
                ->withOptions(['timeout' => 60])
                ->post(self::BASE_URL.'/chat/completions', $payload);

            if ($response->failed()) {
                throw new Exception('Groq API request failed: '.$response->body());
            }

            $body = $response->body();
            $lines = explode("\n", $body);

            foreach ($lines as $line) {
                $line = trim($line);

                if (empty($line) || str_starts_with($line, 'data: ')) {
                    continue;
                }

                if (str_starts_with($line, '[DONE]')) {
                    break;
                }

                $data = json_decode($line, true);

                if (isset($data['choices'][0]['delta']['content'])) {
                    echo $data['choices'][0]['delta']['content'];
                    flush();
                }
            }
        }, 200, ['Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'Connection' => 'keep-alive', ]);
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
        // Groq doesn't have native embeddings API yet, fallback to error
        throw new Exception(
            'Groq does not currently provide native text embeddings API. '.'Consider using OpenAI for embeddings or check Groq documentation for updates.');
    }
}
