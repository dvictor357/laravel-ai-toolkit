<?php

namespace AIToolkit\AIToolkit\Services;

use Illuminate\Support\Facades\Log;

class RetryService
{
    private const MAX_RETRIES = 3;

    private const BASE_DELAY = 1; // seconds

    private const MAX_DELAY = 60; // seconds

    private const JITTER = 0.1; // 10% jitter

    private array $circuitBreakers = [];

    /**
     * Execute a callable with retry logic
     */
    public function execute(callable $callback, array $options = []): mixed
    {
        $maxRetries = $options['max_retries'] ?? self::MAX_RETRIES;
        $baseDelay = $options['base_delay'] ?? self::BASE_DELAY;
        $maxDelay = $options['max_delay'] ?? self::MAX_DELAY;
        $strategy = $options['strategy'] ?? 'exponential'; // exponential, linear, fixed
        $jitter = $options['jitter'] ?? self::JITTER;
        $retryOn = $options['retry_on'] ?? $this->getDefaultRetryableExceptions();
        $circuitBreaker = $options['circuit_breaker'] ?? true;
        $circuitThreshold = $options['circuit_threshold'] ?? 5;
        $circuitTimeout = $options['circuit_timeout'] ?? 60;

        $operation = $options['operation'] ?? 'unknown';
        $provider = $options['provider'] ?? 'unknown';

        if ($circuitBreaker && $this->isCircuitBreakerOpen($operation, $provider, $circuitThreshold, $circuitTimeout)) {
            throw new \RuntimeException("Circuit breaker is open for {$operation} on {$provider}");
        }

        $lastException = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $result = $callback($attempt);

                // On success, reset circuit breaker failure count
                if ($circuitBreaker && $attempt > 0) {
                    $this->resetCircuitBreaker($operation, $provider);
                }

                // Log successful retry if it wasn't the first attempt
                if ($attempt > 0 && config('ai.logging.enabled', true)) {
                    Log::info('Operation succeeded after retry', [
                        'operation' => $operation,
                        'provider' => $provider,
                        'attempt' => $attempt + 1,
                        'max_retries' => $maxRetries + 1,
                    ]);
                }

                return $result;

            } catch (\Throwable $e) {
                $lastException = $e;

                // Check if this exception should be retried
                if (! $this->shouldRetry($e, $retryOn)) {
                    if (config('ai.logging.enabled', true)) {
                        Log::warning('Operation failed with non-retryable exception', [
                            'operation' => $operation,
                            'provider' => $provider,
                            'attempt' => $attempt + 1,
                            'exception' => get_class($e),
                            'message' => $e->getMessage(),
                        ]);
                    }
                    break;
                }

                // Check if this was the last attempt
                if ($attempt === $maxRetries) {
                    if (config('ai.logging.enabled', true)) {
                        Log::error('Operation failed after all retries', [
                            'operation' => $operation,
                            'provider' => $provider,
                            'attempts' => $attempt + 1,
                            'max_retries' => $maxRetries + 1,
                            'exception' => get_class($lastException),
                            'message' => $lastException->getMessage(),
                        ]);
                    }
                    break;
                }

                // Record failure for circuit breaker
                if ($circuitBreaker) {
                    $this->recordFailure($operation, $provider);
                }

                // Calculate delay before next retry
                $delay = $this->calculateDelay($attempt, $baseDelay, $maxDelay, $strategy, $jitter);

                if (config('ai.logging.enabled', true)) {
                    Log::warning('Operation failed, retrying', [
                        'operation' => $operation,
                        'provider' => $provider,
                        'attempt' => $attempt + 1,
                        'max_retries' => $maxRetries + 1,
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                        'delay_seconds' => $delay,
                    ]);
                }

                // Wait before retrying
                if ($delay > 0) {
                    usleep($delay * 1000000); // Convert to microseconds
                }
            }
        }

        // If we get here, all retries failed
        throw $lastException;
    }

    /**
     * Check if an exception should trigger a retry
     */
    private function shouldRetry(\Throwable $exception, array $retryOn): bool
    {
        // Check for specific exception classes
        foreach ($retryOn as $retryableException) {
            if ($exception instanceof $retryableException) {
                return true;
            }
        }

        // Check for specific HTTP status codes (for HTTP exceptions)
        if (method_exists($exception, 'getStatusCode')) {
            $statusCode = $exception->getStatusCode();

            // Retry on 5xx server errors and 429 rate limit
            return in_array($statusCode, [429, 500, 502, 503, 504]);
        }

        // Check exception message for retryable errors
        $message = strtolower($exception->getMessage());
        $retryableMessages = [
            'timeout',
            'connection refused',
            'connection reset',
            'temporary failure',
            'rate limit',
            'too many requests',
            'service unavailable',
            'gateway timeout',
        ];

        foreach ($retryableMessages as $retryableMessage) {
            if (str_contains($message, $retryableMessage)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate delay between retries
     */
    private function calculateDelay(int $attempt, float $baseDelay, float $maxDelay, string $strategy, float $jitter): float
    {
        $delay = match ($strategy) {
            'fixed' => $baseDelay,
            'linear' => $baseDelay * ($attempt + 1),
            'exponential' => $baseDelay * (2 ** $attempt),
            default => $baseDelay * (2 ** $attempt),
        };

        // Cap at maximum delay
        $delay = min($delay, $maxDelay);

        // Add jitter to prevent thundering herd
        if ($jitter > 0) {
            $jitterAmount = $delay * $jitter;
            $delay += (mt_rand() / mt_getrandmax() - 0.5) * 2 * $jitterAmount;
            $delay = max(0, $delay); // Ensure non-negative
        }

        return $delay;
    }

    /**
     * Check if circuit breaker is open
     */
    private function isCircuitBreakerOpen(string $operation, string $provider, int $threshold, int $timeout): bool
    {
        $key = $this->getCircuitBreakerKey($operation, $provider);

        if (! isset($this->circuitBreakers[$key])) {
            return false;
        }

        $state = $this->circuitBreakers[$key];

        // Check if we've waited long enough to try again
        if (time() - $state['last_failure'] > $timeout) {
            $this->resetCircuitBreaker($operation, $provider);

            return false;
        }

        // Check if we've exceeded the failure threshold
        return $state['failure_count'] >= $threshold;
    }

    /**
     * Record a failure for circuit breaker
     */
    private function recordFailure(string $operation, string $provider): void
    {
        $key = $this->getCircuitBreakerKey($operation, $provider);

        if (! isset($this->circuitBreakers[$key])) {
            $this->circuitBreakers[$key] = [
                'failure_count' => 0,
                'last_failure' => time(),
            ];
        }

        $this->circuitBreakers[$key]['failure_count']++;
        $this->circuitBreakers[$key]['last_failure'] = time();
    }

    /**
     * Reset circuit breaker after success
     */
    private function resetCircuitBreaker(string $operation, string $provider): void
    {
        $key = $this->getCircuitBreakerKey($operation, $provider);
        unset($this->circuitBreakers[$key]);
    }

    /**
     * Get circuit breaker storage key
     */
    private function getCircuitBreakerKey(string $operation, string $provider): string
    {
        return "{$operation}:{$provider}";
    }

    /**
     * Get default retryable exceptions
     */
    private function getDefaultRetryableExceptions(): array
    {
        return [
            \GuzzleHttp\Exception\ConnectException::class,
            \GuzzleHttp\Exception\ServerException::class,
            \GuzzleHttp\Exception\BadResponseException::class,
            \GuzzleHttp\Exception\RequestException::class,
            \League\OAuth2\Client\Provider\Exception\IdentityProviderException::class,
            \OpenAI\Exceptions\ErrorException::class,
            \JsonException::class,
        ];
    }

    /**
     * Get circuit breaker status
     */
    public function getCircuitBreakerStatus(string $operation, string $provider): array
    {
        $key = $this->getCircuitBreakerKey($operation, $provider);

        if (! isset($this->circuitBreakers[$key])) {
            return [
                'status' => 'closed',
                'failure_count' => 0,
                'last_failure' => null,
            ];
        }

        $state = $this->circuitBreakers[$key];
        $isOpen = $this->isCircuitBreakerOpen($operation, $provider, 5, 60);

        return [
            'status' => $isOpen ? 'open' : 'half-open',
            'failure_count' => $state['failure_count'],
            'last_failure' => $state['last_failure'],
        ];
    }

    /**
     * Manually reset circuit breaker
     */
    public function resetCircuitBreakerManually(string $operation, string $provider): void
    {
        $this->resetCircuitBreaker($operation, $provider);
    }
}
