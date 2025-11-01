<?php

namespace AIToolkit\AIToolkit\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SecurityService
{
    private const API_KEY_CACHE_PREFIX = 'ai_toolkit_validation';

    private const VALIDATION_CACHE_TTL = 3600; // 1 hour

    private const RATE_LIMIT_PREFIX = 'ai_toolkit_rate_limit';

    /**
     * Validate API key for a provider
     */
    public function validateApiKey(string $provider, string $apiKey): array
    {
        if (empty($apiKey)) {
            return [
                'valid' => false,
                'error' => 'API key is required',
                'provider' => $provider,
            ];
        }

        // Check cache first
        $cachedValidation = $this->getCachedValidation($provider, $apiKey);
        if ($cachedValidation !== null) {
            return $cachedValidation;
        }

        try {
            $validationResult = match ($provider) {
                'openai' => $this->validateOpenAIKey($apiKey),
                'anthropic' => $this->validateAnthropicKey($apiKey),
                'groq' => $this->validateGroqKey($apiKey),
                default => throw new InvalidArgumentException("Unsupported provider: {$provider}")
            };

            // Cache the result
            $this->cacheValidation($provider, $apiKey, $validationResult);

            return $validationResult;
        } catch (Exception $e) {
            Log::error('API key validation failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'error' => 'Validation failed: '.$e->getMessage(),
                'provider' => $provider,
            ];
        }
    }

    /**
     * Check if rate limit is exceeded for provider
     */
    public function checkRateLimit(string $provider, ?string $identifier = null): array
    {
        $config = config("ai.providers.{$provider}");
        if (! $config) {
            return ['allowed' => false, 'error' => 'Provider configuration not found'];
        }

        $maxRequests = $config['rate_limit_max_requests'] ?? 100;
        $windowSeconds = $config['rate_limit_window'] ?? 3600; // 1 hour default
        $key = $identifier ?? $provider;

        $currentCount = $this->getRateLimitCount($provider, $key);

        return [
            'allowed' => $currentCount < $maxRequests,
            'current' => $currentCount,
            'max' => $maxRequests,
            'window' => $windowSeconds,
            'reset_time' => $this->getRateLimitResetTime($provider, $key, $windowSeconds),
        ];
    }

    /**
     * Increment rate limit counter
     */
    public function incrementRateLimit(string $provider, ?string $identifier = null): void
    {
        $key = $identifier ?? $provider;
        $cacheKey = $this->getRateLimitCacheKey($provider, $key);
        $windowSeconds = config("ai.providers.{$provider}.rate_limit_window", 3600);

        try {
            $current = Cache::get($cacheKey, 0);
            Cache::put($cacheKey, $current + 1, $windowSeconds);
        } catch (Exception $e) {
            Log::warning('Failed to increment rate limit', [
                'provider' => $provider,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sanitize input data to prevent injection attacks
     */
    public function sanitizeInput(string $input, array $options = []): string
    {
        // Basic sanitization
        $sanitized = trim($input);

        // Remove potential prompt injection patterns
        $dangerousPatterns = [
            '/ignore previous instructions/i',
            '/forget all previous context/i',
            '/system.*instruction/i',
            '/you are now.*assistant/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            $sanitized = preg_replace($pattern, '[FILTERED]', $sanitized);
        }

        // Apply additional sanitization based on options
        if ($options['max_length'] ?? false) {
            $maxLength = $options['max_length'];
            if (strlen($sanitized) > $maxLength) {
                $sanitized = substr($sanitized, 0, $maxLength);
                Log::warning('Input truncated due to max length', [
                    'original_length' => strlen($input),
                    'truncated_length' => strlen($sanitized),
                    'max_length' => $maxLength,
                ]);
            }
        }

        if ($options['remove_control_chars'] ?? true) {
            $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $sanitized);
        }

        return $sanitized;
    }

    /**
     * Generate rotation tokens for API keys
     */
    public function generateRotationToken(string $provider): string
    {
        return hash('sha256', $provider.now()->timestamp.config('app.key'));
    }

    /**
     * Validate rotation token
     */
    public function validateRotationToken(string $provider, string $token): bool
    {
        // In production, you might want to store these tokens in database
        // For now, we'll use a simple validation based on recent generation
        $expectedToken = $this->generateRotationToken($provider);

        // Allow tokens generated within the last 5 minutes
        $tokenAge = now()->timestamp - substr($token, -10);

        return hash_equals($expectedToken, $token) && $tokenAge < 300;
    }

    /**
     * Get security configuration
     */
    public function getSecurityConfig(): array
    {
        return [
            'input_sanitization' => config('ai.security.input_sanitization', true),
            'rate_limiting' => config('ai.security.rate_limiting', true),
            'api_key_validation' => config('ai.security.api_key_validation', true),
            'prompt_injection_protection' => config('ai.security.prompt_injection_protection', true),
            'max_input_length' => config('ai.security.max_input_length', 32000),
            'log_security_events' => config('ai.security.log_security_events', true),
        ];
    }

    /**
     * Log security event
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        if (! $this->getSecurityConfig()['log_security_events']) {
            return;
        }

        Log::channel('stack')->warning('AI Security Event', array_merge([
            'event' => $event,
            'timestamp' => now(),
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'provider' => 'ai-toolkit',
        ], $context));
    }

    private function validateOpenAIKey(string $apiKey): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->get('https://api.openai.com/v1/models');

            if ($response->successful()) {
                $models = $response->json();

                return [
                    'valid' => true,
                    'provider' => 'openai',
                    'model_count' => count($models['data'] ?? []),
                    'validated_at' => now(),
                ];
            }

            return [
                'valid' => false,
                'error' => 'Invalid API key or insufficient permissions',
                'provider' => 'openai',
                'status_code' => $response->status(),
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => 'Network error during validation',
                'provider' => 'openai',
                'details' => $e->getMessage(),
            ];
        }
    }

    private function validateAnthropicKey(string $apiKey): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01',
            ])->timeout(10)->post('https://api.anthropic.com/v1/messages', [
                'max_tokens' => 1,
                'messages' => [['role' => 'user', 'content' => 'test']],
            ]);

            // Anthropic returns 400 for valid keys with insufficient tokens
            if (in_array($response->status(), [200, 400])) {
                return [
                    'valid' => true,
                    'provider' => 'anthropic',
                    'validated_at' => now(),
                ];
            }

            return [
                'valid' => false,
                'error' => 'Invalid API key',
                'provider' => 'anthropic',
                'status_code' => $response->status(),
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => 'Network error during validation',
                'provider' => 'anthropic',
                'details' => $e->getMessage(),
            ];
        }
    }

    private function validateGroqKey(string $apiKey): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->get('https://api.groq.com/openai/v1/models');

            if ($response->successful()) {
                $models = $response->json();

                return [
                    'valid' => true,
                    'provider' => 'groq',
                    'model_count' => count($models['data'] ?? []),
                    'validated_at' => now(),
                ];
            }

            return [
                'valid' => false,
                'error' => 'Invalid API key',
                'provider' => 'groq',
                'status_code' => $response->status(),
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => 'Network error during validation',
                'provider' => 'groq',
                'details' => $e->getMessage(),
            ];
        }
    }

    private function getCachedValidation(string $provider, string $apiKey): ?array
    {
        $cacheKey = $this->getValidationCacheKey($provider, $apiKey);

        return Cache::get($cacheKey);
    }

    private function cacheValidation(string $provider, string $apiKey, array $result): void
    {
        $cacheKey = $this->getValidationCacheKey($provider, $apiKey);
        Cache::put($cacheKey, $result, self::VALIDATION_CACHE_TTL);
    }

    private function getValidationCacheKey(string $provider, string $apiKey): string
    {
        $hash = hash('sha256', $apiKey);

        return self::API_KEY_CACHE_PREFIX.":{$provider}:{$hash}";
    }

    private function getRateLimitCount(string $provider, string $identifier): int
    {
        $cacheKey = $this->getRateLimitCacheKey($provider, $identifier);

        return Cache::get($cacheKey, 0);
    }

    private function getRateLimitResetTime(string $provider, string $identifier, int $windowSeconds): int
    {
        $cacheKey = $this->getRateLimitCacheKey($provider, $identifier);
        $timestamp = Cache::getStore()->getRedis() ? null : null; // Simplified for now

        return now()->addSeconds($windowSeconds)->timestamp;
    }

    private function getRateLimitCacheKey(string $provider, string $identifier): string
    {
        return self::RATE_LIMIT_PREFIX.":{$provider}:{$identifier}";
    }
}
