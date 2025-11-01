<?php

namespace AIToolkit\AIToolkit\Console;

use AIToolkit\AIToolkit\Contracts\AIProviderContract;
use Exception;
use Illuminate\Console\Command;

class AIChatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:chat {prompt : The prompt to send to the AI}
                            {--provider= : The AI provider to use (openai, anthropic, groq)}
                            {--model= : The specific model to use}
                            {--max-tokens= : Maximum tokens to generate}
                            {--temperature= : Temperature for response creativity (0-2)}
                            {--stream : Enable streaming response}
                            {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Chat with AI providers (OpenAI, Anthropic, Groq)';

    /**
     * Execute the console command.
     */
    public function handle(AIProviderContract $provider): int
    {
        try {
            $prompt = $this->argument('prompt');
            $options = $this->buildOptions();

            // Show provider info
            $currentProvider = config('ai.default_provider');
            $providerName = $this->option('provider') ?? $currentProvider;
            $this->info("Using AI provider: {$providerName}");
            $this->line("Prompt: {$prompt}");
            $this->line('');

            if ($this->option('stream')) {
                $this->handleStreaming($provider, $prompt, $options);
            } else {
                $this->handleNormal($provider, $prompt, $options);
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Error: '.$e->getMessage());

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function buildOptions(): array
    {
        $options = [];

        if ($this->option('model')) {
            $options['model'] = $this->option('model');
        }

        if ($this->option('max-tokens')) {
            $options['max_tokens'] = (int) $this->option('max-tokens');
        }

        if ($this->option('temperature')) {
            $options['temperature'] = (float) $this->option('temperature');
        }

        return $options;
    }

    private function handleStreaming(AIProviderContract $provider, string $prompt, array $options): void
    {
        $this->line('AI Response (streaming):');
        $this->line(str_repeat('-', 50));

        $stream = $provider->stream($prompt, $options);

        // For streaming, we'll output the raw content
        // In a real CLI, you might want to use a progress bar or spinner
        echo $stream->getContent();

        $this->line('');
        $this->line(str_repeat('-', 50));
    }

    private function handleNormal(AIProviderContract $provider, string $prompt, array $options): void
    {
        $response = $provider->chat($prompt, $options);

        if ($this->option('json')) {
            $this->line(json_encode($response, JSON_PRETTY_PRINT));
        } else {
            $this->line('AI Response:');
            $this->line(str_repeat('-', 50));
            $this->line($response['content']);
            $this->line(str_repeat('-', 50));

            if (isset($response['usage'])) {
                $usage = $response['usage'];
                $this->line(
                    "Tokens used: {$usage['total_tokens']} "."(Prompt: {$usage['prompt_tokens']}, "."Completion: {$usage['completion_tokens']})");
            }
        }
    }
}
