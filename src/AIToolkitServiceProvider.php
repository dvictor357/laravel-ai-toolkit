<?php

namespace AIToolkit\AIToolkit;

use AIToolkit\AIToolkit\Console\AIChatCommand;
use AIToolkit\AIToolkit\Console\Commands\AiTestCommand;
use AIToolkit\AIToolkit\Contracts\AIProviderContract;
use AIToolkit\AIToolkit\Providers\AnthropicProvider;
use AIToolkit\AIToolkit\Providers\GroqProvider;
use AIToolkit\AIToolkit\Providers\OpenAiProvider;
use AIToolkit\AIToolkit\Services\EncryptionService;
use AIToolkit\AIToolkit\Services\MonitoringService;
use AIToolkit\AIToolkit\Services\SecurityService;
use AIToolkit\AIToolkit\Support\AIProviderConfiguration;
use Exception;
use Illuminate\Support\ServiceProvider;

class AIToolkitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/ai-toolkit.php' => config_path('ai-toolkit.php')], 'config');

        // Publish migration for AI provider configurations
        $this->publishes([
            __DIR__.'/../database/migrations/2024_11_01_000000_create_ai_provider_configurations_table.php'
                => database_path('migrations/2024_11_01_000000_create_ai_provider_configurations_table.php'),
        ], 'ai-toolkit-migrations');

        // Load routes if configured
        if (config('ai.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/routes.php');
        }

        // Register Filament Admin Panel (auto-registered in Laravel 11+)
        if (config('ai-toolkit.filament.enabled', true) && class_exists(\Filament\PanelProvider::class)) {
            // AdminPanelProvider is auto-discovered, no manual registration needed
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AIChatCommand::class, AiTestCommand::class]);
        }
    }

    public function register(): void
    {
        // Register AI Provider Contract (now database-driven)
        $this->app->singleton(AIProviderContract::class, function ($app) {
            // Check database first for default provider
            $defaultProvider = AIProviderConfiguration::where('is_default', true)
                ->where('is_enabled', true)
                ->first();

            if (!$defaultProvider) {
                // Fallback to config if no default found in DB
                $provider = config('ai.default_provider', 'openai');
            } else {
                $provider = $defaultProvider->name;
            }

            return match ($provider) {
                'openai' => new OpenAiProvider,
                'anthropic' => new AnthropicProvider,
                'groq' => new GroqProvider,
                default => throw new Exception('Invalid AI provider: '.$provider),
            };
        });

        // Register Encryption Service
        $this->app->singleton('ai-encryption', function ($app) {
            return new EncryptionService;
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
