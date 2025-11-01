<?php

use AIToolkit\AIToolkit\Services\AiCacheService;
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

    it('remembers values with callback when not cached', function () {
        $service = new AiCacheService;

        $callbackCalled = false;
        $result = $service->remember('chat', 'openai', 'test prompt', function () use (&$callbackCalled) {
            $callbackCalled = true;

            return 'fresh response';
        });

        expect($callbackCalled)->toBeTrue();
        expect($result)->toBe('fresh response');

        // Second call should use cached value
        $callbackCalled = false;
        $cachedResult = $service->remember('chat', 'openai', 'test prompt', function () use (&$callbackCalled) {
            $callbackCalled = true;

            return 'fresh response';
        });

        expect($callbackCalled)->toBeFalse();
        expect($cachedResult)->toBe('fresh response');
    });

    it('skips caching when disabled', function () {
        config(['ai.cache.enabled' => false]);
        $service = new AiCacheService;

        expect($service->isEnabled())->toBeFalse();

        $result = $service->remember('chat', 'openai', 'test', function () {
            return 'uncached response';
        });

        expect($result)->toBe('uncached response');

        // Value should not be cached
        $cached = $service->get('chat', 'openai', 'test');
        expect($cached)->toBeNull();
    });

    it('respects custom TTL options', function () {
        $service = new AiCacheService;

        $service->put('chat', 'openai', 'test', 'value', ['ttl' => 600]);

        $cached = $service->get('chat', 'openai', 'test');
        expect($cached)->toBe('value');
    });

    it('gets default TTL from configuration', function () {
        $service = new AiCacheService;

        expect($service->getTTL())->toBe(config('ai.cache.ttl', 3600));
    });

    it('warms cache with multiple providers and operations', function () {
        $service = new AiCacheService;

        $prompts = [
            [
                'prompt' => 'What is Laravel?',
                'operations' => ['chat', 'embed'],
                'options' => ['temperature' => 0.7],
            ],
            [
                'prompt' => 'Explain PHP',
                'operations' => ['chat'],
            ],
        ];

        $warmedKeys = $service->warmCache($prompts);

        expect($warmedKeys)->toHaveCount(6); // 2 prompts * 3 providers * operations
        expect($warmedKeys)->toEach()->toContain('ai_toolkit:');
    });

    it('handles invalidation by pattern', function () {
        $service = new AiCacheService;

        // Put some test values
        $service->put('chat', 'openai', 'test1', 'value1');
        $service->put('chat', 'anthropic', 'test2', 'value2');
        $service->put('embed', 'openai', 'test3', 'value3');

        expect($service->has('chat', 'openai', 'test1'))->toBeTrue();
        expect($service->has('chat', 'anthropic', 'test2'))->toBeTrue();
        expect($service->has('embed', 'openai', 'test3'))->toBeTrue();

        // Invalidate all openai chat entries
        $invalidated = $service->invalidatePattern('openai:chat:*');

        expect($invalidated)->toBeGreaterThanOrEqual(1);
        expect($service->has('chat', 'openai', 'test1'))->toBeFalse();
        expect($service->has('chat', 'anthropic', 'test2'))->toBeTrue();
        expect($service->has('embed', 'openai', 'test3'))->toBeTrue();
    });

    it('generates consistent cache keys for same inputs', function () {
        $service = new AiCacheService;

        $options = ['temperature' => 0.7, 'max_tokens' => 100];
        $key1 = $service->generateKey('chat', 'openai', 'test prompt', $options);
        $key2 = $service->generateKey('chat', 'openai', 'test prompt', $options);

        expect($key1)->toEqual($key2);
    });

    it('handles empty and complex options properly', function () {
        $service = new AiCacheService;

        $key1 = $service->generateKey('chat', 'openai', 'test', []);
        $key2 = $service->generateKey('chat', 'openai', 'test', ['complex' => ['nested' => 'value']]);

        expect($key1)->not->toEqual($key2);
        expect($key1)->toContain('ai_toolkit:');
        expect($key2)->toContain('ai_toolkit:');
    });

    it('provides cache statistics', function () {
        $service = new AiCacheService;

        // This would need to be implemented in the service
        $stats = $service->getStats();

        expect($stats)->toBeArray();
        expect($stats)->toHaveKeys(['hits', 'misses', 'hit_rate', 'total_requests']);
    });

    it('handles serialization of complex data types', function () {
        $service = new AiCacheService;

        $complexData = [
            'response' => 'test',
            'usage' => ['tokens' => 100],
            'model' => 'gpt-4',
            'metadata' => ['created' => now()],
        ];

        $service->put('chat', 'openai', 'complex test', $complexData);
        $cached = $service->get('chat', 'openai', 'complex test');

        expect($cached)->toEqual($complexData);
        expect($cached['usage']['tokens'])->toBe(100);
    });
});
