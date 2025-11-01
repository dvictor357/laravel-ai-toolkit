<?php

namespace AIToolkit\AIToolkit;

use AIToolkit\AIToolkit\Console\AIChatCommand;
use AIToolkit\AIToolkit\Console\Commands\AiTestCommand;
use AIToolkit\AIToolkit\Contracts\AIProviderContract;
use AIToolkit\AIToolkit\Providers\AnthropicProvider;
use AIToolkit\AIToolkit\Providers\GroqProvider;
use AIToolkit\AIToolkit\Providers\OpenAiProvider;
use AIToolkit\AIToolkit\Services\MonitoringService;
use AIToolkit\AIToolkit\Services\SecurityService;
use Exception;
use Illuminate\Support\ServiceProvider;

class AIToolkitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/ai-toolkit.php' => config_path('ai-toolkit.php')], 'config');

        // Load routes if configured
        if (config('ai.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/routes.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AIChatCommand::class, AiTestCommand::class]);
        }
    }

    public function register(): void
    {
        // Register AI Provider Contract
        $this->app->singleton(AIProviderContract::class, function ($app) {
            // Resolve based on config('ai.default_provider')
            $provider = config('ai.default_provider', 'openai');

            return match ($provider) {
                'openai' => new OpenAiProvider,
                'anthropic' => new AnthropicProvider,
                'groq' => new GroqProvider,
                default => throw new Exception('Invalid AI provider: '.$provider),
            };
        });

        // Register AI Cache Service
        $this->app->singleton('ai-cache', function ($app) {
            return new \AIToolkit\AIToolkit\Services\AiCacheService;
        });

        // Register AI Retry Service
        $this->app->singleton('ai-retry', function ($app) {
            return new \AIToolkit\AIToolkit\Services\RetryService;
        });

        // Register AI Security Service
        $this->app->singleton('ai-security', function ($app) {
            return new SecurityService;
        });

        // Register AI Monitoring Service
        $this->app->singleton('ai-monitoring', function ($app) {
            return new MonitoringService;
        });
    }
}
