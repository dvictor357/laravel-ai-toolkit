<?php

use AIToolkit\AIToolkit\Contracts\AIProviderContract;
use AIToolkit\AIToolkit\Providers\AnthropicProvider;
use AIToolkit\AIToolkit\Providers\GroqProvider;
use AIToolkit\AIToolkit\Providers\OpenAiProvider;
use AIToolkit\AIToolkit\Services\AiCacheService;
use AIToolkit\AIToolkit\Services\RetryService;
use Illuminate\Support\Facades\App;

it('binds AIProviderContract to OpenAiProvider by default', function () {
    $provider = app(AIProviderContract::class);

    expect($provider)->toBeInstanceOf(OpenAiProvider::class);
});

it('can resolve different AI providers based on configuration', function () {
    config(['ai.default_provider' => 'openai']);
    $openaiProvider = app(AIProviderContract::class);
    expect($openaiProvider)->toBeInstanceOf(OpenAiProvider::class);

    config(['ai.default_provider' => 'anthropic']);
    App::forgetInstance(AIProviderContract::class);
    $anthropicProvider = app(AIProviderContract::class);
    expect($anthropicProvider)->toBeInstanceOf(AnthropicProvider::class);

    config(['ai.default_provider' => 'groq']);
    App::forgetInstance(AIProviderContract::class);
    $groqProvider = app(AIProviderContract::class);
    expect($groqProvider)->toBeInstanceOf(GroqProvider::class);
});

it('throws exception for invalid provider', function () {
    config(['ai.default_provider' => 'invalid']);

    expect(fn () => app(AIProviderContract::class))
        ->toThrow(\Exception::class, 'Invalid AI provider: invalid');
});

it('binds AiCacheService as singleton', function () {
    $cache1 = app('ai-cache');
    $cache2 = app('ai-cache');

    expect($cache1)->toBeInstanceOf(AiCacheService::class);
    expect($cache2)->toBe($cache1); // Same instance
});

it('binds RetryService as singleton', function () {
    $retry1 = app('ai-retry');
    $retry2 = app('ai-retry');

    expect($retry1)->toBeInstanceOf(RetryService::class);
    expect($retry2)->toBe($retry1); // Same instance
});

it('registers commands when running in console', function () {
    $this->artisan('list')
        ->expectsOutputToContain('ai:chat');
});
