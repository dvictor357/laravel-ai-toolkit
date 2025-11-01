<?php

namespace AIToolkit\AIToolkit\Tests;

use AIToolkit\AIToolkit\AiToolkitServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up testing environment variables
        config(['ai.default_provider' => 'openai',
            'ai.providers.openai.api_key' => 'test-openai-key',
            'ai.providers.anthropic.api_key' => 'test-anthropic-key',
            'ai.providers.groq.api_key' => 'test-groq-key',
            'ai.cache.enabled' => true,
            'ai.logging.enabled' => false,
            'ai.queue.tries' => 3, ]);
    }

    protected function getPackageProviders($app): array
    {
        return [AiToolkitServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', ['driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '', ]);
    }
}
