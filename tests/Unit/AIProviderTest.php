<?php

use AIToolkit\AIToolkit\Contracts\AIProviderContract;
use AIToolkit\AIToolkit\Providers\AnthropicProvider;
use AIToolkit\AIToolkit\Providers\GroqProvider;
use AIToolkit\AIToolkit\Providers\OpenAiProvider;

describe('AI Provider Contract', function () {
    it('ensures all providers implement the same interface', function ($providerClass) {
        $provider = new $providerClass;
        expect($provider)->toBeInstanceOf(AIProviderContract::class);
    })->with([OpenAiProvider::class,
        AnthropicProvider::class,
        GroqProvider::class, ]);
});

describe('OpenAI Provider', function () {
    it('requires API key', function () {
        config(['ai.providers.openai.api_key' => null]);

        expect(fn () => new OpenAiProvider)->toThrow(
            InvalidArgumentException::class,
            'OpenAI API key is required');
    });

    it('initializes with valid API key', function () {
        expect(fn () => new OpenAiProvider)->not->toThrow();
    });

    it('generates proper cache keys', function () {
        $provider = new OpenAiProvider;
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);

        $key1 = $method->invoke($provider, 'chat', 'test prompt', ['temperature' => 0.7]);
        $key2 = $method->invoke($provider, 'chat', 'test prompt', ['temperature' => 0.8]);
        $key3 = $method->invoke($provider, 'embed', 'test prompt');

        expect($key1)->toContain('ai_toolkit:openai:chat:');
        expect($key1)->not->toEqual($key2); // Different options should generate different keys
        expect($key3)->toContain('ai_toolkit:openai:embed:');
        expect($key3)->not->toEqual($key1); // Different operations should generate different keys
    });
});

describe('Anthropic Provider', function () {
    it('requires API key', function () {
        config(['ai.providers.anthropic.api_key' => null]);

        expect(fn () => new AnthropicProvider)->toThrow(
            InvalidArgumentException::class,
            'Anthropic API key is required');
    });

    it('initializes with valid API key', function () {
        expect(fn () => new AnthropicProvider)->not->toThrow();
    });

    it('does not support embeddings', function () {
        $provider = new AnthropicProvider;

        expect(fn () => $provider->embed('test text'))->toThrow(
            Exception::class,
            'Anthropic does not provide native text embeddings');
    });

    it('generates proper cache keys', function () {
        $provider = new AnthropicProvider;
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);

        $key = $method->invoke($provider, 'chat', 'test prompt');

        expect($key)->toContain('ai_toolkit:anthropic:chat:');
    });
});

describe('Groq Provider', function () {
    it('requires API key', function () {
        config(['ai.providers.groq.api_key' => null]);

        expect(fn () => new GroqProvider)->toThrow(InvalidArgumentException::class, 'Groq API key is required');
    });

    it('initializes with valid API key', function () {
        expect(fn () => new GroqProvider)->not->toThrow();
    });

    it('does not support embeddings', function () {
        $provider = new GroqProvider;

        expect(fn () => $provider->embed('test text'))->toThrow(
            Exception::class,
            'Groq does not currently provide native text embeddings API');
    });

    it('generates proper cache keys', function () {
        $provider = new GroqProvider;
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);

        $key = $method->invoke($provider, 'chat', 'test prompt');

        expect($key)->toContain('ai_toolkit:groq:chat:');
    });
});
