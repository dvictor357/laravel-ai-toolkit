<?php

use AIToolkit\AIToolkit\Services\AiCacheService;
use AIToolkit\AIToolkit\Services\RetryService;
use Illuminate\Support\Facades\Cache;

describe('AiCacheService', function () {
    beforeEach(function () {
        config(['ai.cache.enabled' => true]);
        Cache::flush();
    });

    it('generates proper cache keys', function () {
        $service = new AiCacheService;

        $key1 = $service->generateKey('chat', 'openai', 'test prompt', ['temperature' => 0.7]);
        $key2 = $service->generateKey('chat', 'openai', 'test prompt', ['temperature' => 0.8]);
        $key3 = $service->generateKey('embed', 'openai', 'test prompt');

        expect($key1)->toContain('ai_toolkit:openai:chat:');
        expect($key1)->not->toEqual($key2); // Different options
        expect($key3)->toContain('ai_toolkit:openai:embed:');
        expect($key3)->not->toEqual($key1); // Different operation
    });

    it('respects cache enabled setting', function () {
        config(['ai.cache.enabled' => false]);
        $service = new AiCacheService;

        expect($service->isEnabled())->toBeFalse();
    });

    it('gets and puts cached values', function () {
        $service = new AiCacheService;

        // Test put
        $result = $service->put('chat', 'openai', 'test', 'cached response');
        expect($result)->toBeTrue();

        // Test get
        $cached = $service->get('chat', 'openai', 'test');
        expect($cached)->toBe('cached response');
    });

    it('checks if cache has a key', function () {
        $service = new AiCacheService;

        expect($service->has('chat', 'openai', 'nonexistent'))->toBeFalse();

        $service->put('chat', 'openai', 'test', 'value');
        expect($service->has('chat', 'openai', 'test'))->toBeTrue();
    });

    it('forgets cached values', function () {
        $service = new AiCacheService;

        $service->put('chat', 'openai', 'test', 'value');
        expect($service->has('chat', 'openai', 'test'))->toBeTrue();

        $service->forget('chat', 'openai', 'test');
        expect($service->has('chat', 'openai', 'test'))->toBeFalse();
    });

    it('uses remember pattern', function () {
        $service = new AiCacheService;

        $called = false;
        $result = $service->remember('chat', 'openai', 'test', function () use (&$called) {
            $called = true;

            return 'fresh response';
        });

        expect($called)->toBeTrue();
        expect($result)->toBe('fresh response');

        // Second call should use cache
        $called = false;
        $result = $service->remember('chat', 'openai', 'test', function () use (&$called) {
            $called = true;

            return 'another response';
        });

        expect($called)->toBeFalse();            // Callback not called
        expect($result)->toBe('fresh response'); // Cached value returned
    });

    it('gets cache statistics', function () {
        $service = new AiCacheService;
        $stats = $service->getStats();

        expect($stats)->toHaveKeys(['enabled', 'ttl', 'driver', 'prefix']);
        expect($stats['enabled'])->toBeTrue();
        expect($stats['prefix'])->toBe('ai_toolkit');
    });
});

describe('RetryService', function () {
    it('executes successful operations without retry', function () {
        $service = new RetryService;

        $result = $service->execute(function () {
            return 'success';
        });

        expect($result)->toBe('success');
    });

    it('retries failed operations', function () {
        $service = new RetryService;
        $attempts = 0;

        $result = $service->execute(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new Exception('Temporary failure');
            }

            return 'success after retry';
        }, ['max_retries' => 5]);

        expect($attempts)->toBe(3);
        expect($result)->toBe('success after retry');
    });

    it('fails after max retries', function () {
        $service = new RetryService;

        expect(fn () => $service->execute(function () {
            throw new Exception('Always fails');
        }, ['max_retries' => 2]))->toThrow(Exception::class, 'Always fails');
    });

    it('does not retry non-retryable exceptions', function () {
        $service = new RetryService;
        $attempts = 0;

        expect(fn () => $service->execute(function () use (&$attempts) {
            $attempts++;
            throw new InvalidArgumentException('Invalid argument');
        }, ['max_retries' => 3]))->toThrow(InvalidArgumentException::class);

        expect($attempts)->toBe(1); // Should not retry
    });

    it('respects circuit breaker', function () {
        $service = new RetryService;

        // Fill up circuit breaker with failures
        for ($i = 0; $i < 6; $i++) {
            try {
                $service->execute(function () {
                    throw new Exception('Always fails');
                }, ['operation' => 'test',
                    'provider' => 'test',
                    'circuit_threshold' => 5,
                    'max_retries' => 0, ]);
            } catch (Exception $e) {
                // Expected
            }
        }

        // Circuit breaker should be open now
        $status = $service->getCircuitBreakerStatus('test', 'test');
        expect($status['status'])->toBe('open');

        // Next attempt should immediately fail due to circuit breaker
        expect(fn () => $service->execute(function () {
            return 'should not execute';
        }, ['operation' => 'test',
            'provider' => 'test',
            'max_retries' => 0, ]))->toThrow(RuntimeException::class, 'Circuit breaker is open');
    });

    it('can reset circuit breaker manually', function () {
        $service = new RetryService;

        // Fill up circuit breaker
        for ($i = 0; $i < 5; $i++) {
            try {
                $service->execute(function () {
                    throw new Exception('Always fails');
                }, ['operation' => 'test', 'provider' => 'test', 'max_retries' => 0]);
            } catch (Exception $e) {
                // Expected
            }
        }

        $statusBefore = $service->getCircuitBreakerStatus('test', 'test');
        expect($statusBefore['status'])->toBe('open');

        $service->resetCircuitBreakerManually('test', 'test');

        $statusAfter = $service->getCircuitBreakerStatus('test', 'test');
        expect($statusAfter['status'])->toBe('closed');
    });
});
