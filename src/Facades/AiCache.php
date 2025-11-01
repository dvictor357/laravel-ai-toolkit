<?php

namespace AIToolkit\AIToolkit\Facades;

use AIToolkit\AIToolkit\Services\AiCacheService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string generateKey(string $operation, string $provider, string $input, array $options = [])
 * @method static bool isEnabled()
 * @method static int getTTL()
 * @method static mixed remember(string $operation, string $provider, string $input, callable $callback, array
 *   $options = [])
 * @method static mixed get(string $operation, string $provider, string $input, array $options = [])
 * @method static bool put(string $operation, string $provider, string $input, mixed $value, array $options = [])
 * @method static bool forget(string $operation, string $provider, string $input, array $options = [])
 * @method static array warmCache(array $prompts)
 * @method static int invalidatePattern(string $pattern)
 * @method static int invalidateAll()
 * @method static array getStats()
 * @method static bool has(string $operation, string $provider, string $input, array $options = [])
 * @method static int getTTLForKey(string $operation, string $provider, string $input, array $options = [])
 *
 * @see AiCacheService
 */
class AiCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai-cache';
    }
}
