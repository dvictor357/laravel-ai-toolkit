<?php

namespace AIToolkit\AIToolkit\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiCacheService
{
    private const CACHE_PREFIX = 'ai_toolkit';

    private const DEFAULT_TTL = 3600; // 1 hour

    /**
     * Get or set cached response
     */
    public function remember(
        string $operation,
        string $provider,
        string $input,
        callable $callback,
        array $options = [],
    ): mixed {
        if (! $this->isEnabled()) {
            return $callback();
        }

        $key = $this->generateKey($operation, $provider, $input, $options);
        $ttl = $options['ttl'] ?? $this->getTTL();

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Check if caching is enabled
     */
    public function isEnabled(): bool
    {
        return config('ai.cache.enabled', true);
    }

    /**
     * Generate a cache key for AI operations
     */
    public function generateKey(string $operation, string $provider, string $input, array $options = []): string
    {
        $hash = hash('sha256', $input.serialize($options).$provider);

        return self::CACHE_PREFIX.":{$provider}:{$operation}:{$hash}";
    }

    /**
     * Get cache TTL
     */
    public function getTTL(): int
    {
        return config('ai.cache.ttl', self::DEFAULT_TTL);
    }

    /**
     * Get cached value without executing callback
     */
    public function get(string $operation, string $provider, string $input, array $options = []): mixed
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $key = $this->generateKey($operation, $provider, $input, $options);

        return Cache::get($key);
    }

    /**
     * Set cached value
     */
    public function put(
        string $operation,
        string $provider,
        string $input,
        mixed $value,
        array $options = [],
    ): bool {
        if (! $this->isEnabled()) {
            return false;
        }

        $key = $this->generateKey($operation, $provider, $input, $options);
        $ttl = $options['ttl'] ?? $this->getTTL();

        return Cache::put($key, $value, $ttl);
    }

    /**
     * Forget cached value
     */
    public function forget(string $operation, string $provider, string $input, array $options = []): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $key = $this->generateKey($operation, $provider, $input, $options);

        return Cache::forget($key);
    }

    /**
     * Warm up cache with common prompts
     */
    public function warmCache(array $prompts): array
    {
        $warmed = [];
        $providers = ['openai', 'anthropic', 'groq'];

        foreach ($prompts as $promptData) {
            $prompt = $promptData['prompt'] ?? '';
            $operations = $promptData['operations'] ?? ['chat'];
            $options = $promptData['options'] ?? [];

            foreach ($providers as $provider) {
                foreach ($operations as $operation) {
                    $key = $this->generateKey($operation, $provider, $prompt, $options);
                    $warmed[] = $key;
                }
            }
        }

        return $warmed;
    }

    /**
     * Invalidate cache by pattern
     */
    public function invalidatePattern(string $pattern): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $prefix = self::CACHE_PREFIX.":{$pattern}";
        $driver = Cache::getStore();
        $invalidated = 0;

        try {
            // For Redis driver with tag support
            if ($driver->supportsTags()) {
                // Extract provider and operation from pattern like "openai:chat:*"
                $patternParts = explode(':', $pattern);
                if (count($patternParts) >= 2) {
                    $provider = $patternParts[0];
                    $operation = $patternParts[1] ?? '';

                    // Use pattern matching for Redis
                    $keys = $this->getMatchingKeys($prefix);
                    foreach ($keys as $key) {
                        Cache::forget($key);
                        $invalidated++;
                    }
                }
            } else {
                // For non-taggable stores, iterate through known keys
                // This is a limitation - in production you might want to use Redis directly
                $invalidated = $this->invalidateAll();
            }
        } catch (Exception $e) {
            Log::warning('Failed to invalidate cache pattern', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
        }

        return $invalidated;
    }

    /**
     * Get keys matching a pattern (Redis-specific implementation)
     */
    private function getMatchingKeys(string $pattern): array
    {
        if (config('cache.default') !== 'redis') {
            return [];
        }

        try {
            $redis = Cache::getStore()->getRedis();
            $keys = $redis->keys($pattern);

            // Filter to only include our cache keys
            return array_filter($keys, function ($key) {
                return str_contains($key, self::CACHE_PREFIX);
            });
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Invalidate all AI cache
     */
    public function invalidateAll(): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $driver = Cache::getStore();

        if ($driver->supportsTags()) {
            Cache::tags([self::CACHE_PREFIX])->flush();

            return 1;
        }

        // For non-taggable stores, we need to iterate through all keys
        // This is a simplified implementation - in production you might want to use Redis directly
        return 0;
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $stats = ['enabled' => $this->isEnabled(),
            'ttl' => $this->getTTL(),
            'driver' => config('cache.default'),
            'prefix' => self::CACHE_PREFIX, ];

        // Try to get more detailed stats based on the driver
        try {
            switch (config('cache.default')) {
                case 'redis':
                    $stats['redis_info'] = $this->getRedisStats();
                    break;
                case 'database':
                    $stats['database_info'] = $this->getDatabaseStats();
                    break;
            }
        } catch (Exception $e) {
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    /**
     * Get Redis-specific statistics
     */
    private function getRedisStats(): array
    {
        if (! class_exists('\Redis')) {
            return ['error' => 'Redis extension not available'];
        }

        try {
            $redis = Cache::getStore()->getRedis();
            $info = $redis->info();

            return ['memory_usage' => $info['used_memory_human'] ?? null,
                'memory_peak' => $info['used_memory_peak_human'] ?? null,
                'connected_clients' => $info['connected_clients'] ?? null,
                'total_commands_processed' => $info['total_commands_processed'] ?? null, ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get database-specific statistics
     */
    private function getDatabaseStats(): array
    {
        try {
            $table = config('cache.stores.database.table', 'cache');

            return ['total_records' => DB::table($table)->count(),
                'expired_records' => DB::table($table)->where('expiration', '<', time())->count(),
                'table_size' => $this->getTableSize($table), ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get table size in MB
     */
    private function getTableSize(string $table): ?string
    {
        try {
            $result = DB::select(
                "
                SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'table_size_mb'
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                AND table_name = '{$table}'
            ");

            return $result[0]->table_size_mb ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Check if a key exists in cache
     */
    public function has(string $operation, string $provider, string $input, array $options = []): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $key = $this->generateKey($operation, $provider, $input, $options);

        return Cache::has($key);
    }

    /**
     * Get remaining TTL for a cache key
     */
    public function getTTLForKey(string $operation, string $provider, string $input, array $options = []): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $key = $this->generateKey($operation, $provider, $input, $options);

        // This is a simplified implementation - some cache drivers don't expose TTL
        try {
            if (method_exists(Cache::getStore(), 'ttl')) {
                return Cache::getStore()->ttl($key);
            }
        } catch (Exception $e) {
            // Ignore errors and return 0
        }

        return 0;
    }
}
