<?php

namespace Database\Seeders;

use AIToolkit\AIToolkit\Services\EncryptionService;
use AIToolkit\AIToolkit\Support\AIProviderConfiguration;
use Illuminate\Database\Seeder;

class AIProviderSeeder extends Seeder
{
    public function run(): void
    {
        $encryptionService = new EncryptionService;

        // Check if providers already exist
        if (AIProviderConfiguration::count() > 0) {
            $this->command->info('AI providers already exist. Skipping seeding.');

            return;
        }

        // Seed OpenAI
        if ($apiKey = config('ai-toolkit.providers.openai.api_key')) {
            AIProviderConfiguration::create([
                'name' => 'openai',
                'display_name' => 'OpenAI',
                'api_key' => $encryptionService->encryptApiKey($apiKey),
                'default_model' => config('ai-toolkit.providers.openai.default_model', 'gpt-4o'),
                'default_max_tokens' => config('ai-toolkit.providers.openai.default_max_tokens', 1024),
                'default_temperature' => config('ai-toolkit.providers.openai.default_temperature', 0.7),
                'is_default' => config('ai-toolkit.default_provider', 'openai') === 'openai',
                'is_enabled' => true,
                'notes' => 'OpenAI provider - configured via .env',
            ]);
        }

        // Seed Anthropic
        if ($apiKey = config('ai-toolkit.providers.anthropic.api_key')) {
            AIProviderConfiguration::create([
                'name' => 'anthropic',
                'display_name' => 'Anthropic Claude',
                'api_key' => $encryptionService->encryptApiKey($apiKey),
                'default_model' => config('ai-toolkit.providers.anthropic.default_model', 'claude-3-5-sonnet-20241022'),
                'default_max_tokens' => config('ai-toolkit.providers.anthropic.default_max_tokens', 1024),
                'default_temperature' => config('ai-toolkit.providers.anthropic.default_temperature', 1.0),
                'is_default' => config('ai-toolkit.default_provider', 'openai') === 'anthropic',
                'is_enabled' => true,
                'notes' => 'Anthropic Claude provider - configured via .env',
            ]);
        }

        // Seed Groq
        if ($apiKey = config('ai-toolkit.providers.groq.api_key')) {
            AIProviderConfiguration::create([
                'name' => 'groq',
                'display_name' => 'Groq',
                'api_key' => $encryptionService->encryptApiKey($apiKey),
                'default_model' => config('ai-toolkit.providers.groq.default_model', 'mixtral-8x7b-32768'),
                'default_max_tokens' => config('ai-toolkit.providers.groq.default_max_tokens', 1024),
                'default_temperature' => config('ai-toolkit.providers.groq.default_temperature', 0.7),
                'is_default' => config('ai-toolkit.default_provider', 'openai') === 'groq',
                'is_enabled' => true,
                'notes' => 'Groq provider - configured via .env',
            ]);
        }

        $this->command->info('AI providers seeded successfully!');
        $this->command->info('Remember to update API keys via the admin panel at /admin');
    }
}
