<?php

namespace AIToolkit\AIToolkit\Facades;

use AIToolkit\AIToolkit\Services\RetryService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed execute(callable $callback, array $options = [])
 * @method static array getCircuitBreakerStatus(string $operation, string $provider)
 * @method static void resetCircuitBreakerManually(string $operation, string $provider)
 *
 * @see RetryService
 */
class RetryServiceFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai-retry';
    }
}
