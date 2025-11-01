<?php

namespace AIToolkit\AIToolkit\Jobs;

use AIToolkit\AIToolkit\Contracts\AIProviderContract;
use AIToolkit\AIToolkit\Events\AiChatCompleted;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiChatJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public $tries;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [10, 30, 60];

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public $maxExceptions = 3;

    public function __construct(
        private readonly string $prompt,
        private readonly array $options = [],
        private readonly ?string $resultId = null,
    ) {
        // Configure job settings from config
        $this->tries = config('ai.queue.tries', 3);
    }

    public function handle(AIProviderContract $provider): void
    {
        try {
            // Log the start of the job if logging is enabled
            if (config('ai.logging.enabled')) {
                Log::channel(config('ai.logging.channel', 'stack'))->info(
                    'AI Chat Job Started',
                    ['prompt' => substr(
                        $this->prompt,
                        0,
                        100).(strlen(
                            $this->prompt) > 100 ? '...' : ''),
                        'options' => $this->options,
                        'result_id' => $this->resultId,
                        'job_id' => $this->job?->getJobId(), ]);
            }

            // Get AI response
            $response = $provider->chat($this->prompt, $this->options);

            // Store the response if a result ID is provided
            if ($this->resultId) {
                $this->storeResponse($response);
            }

            // Dispatch completion event
            event(
                new AiChatCompleted(
                    response: $response, resultId: $this->resultId, jobId: $this->job?->getJobId()));

            // Log successful completion if enabled
            if (config('ai.logging.enabled')) {
                Log::channel(config('ai.logging.channel', 'stack'))->info(
                    'AI Chat Job Completed',
                    ['result_id' => $this->resultId,
                        'job_id' => $this->job?->getJobId(),
                        'content_length' => strlen(
                            $response['content'] ?? ''),
                        'usage' => $response['usage'] ?? null, ]);
            }
        } catch (Exception $e) {
            // Log the error
            if (config('ai.logging.enabled')) {
                Log::channel(config('ai.logging.channel', 'stack'))->error(
                    'AI Chat Job Failed',
                    ['result_id' => $this->resultId,
                        'job_id' => $this->job?->getJobId(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(), ]);
            }

            // If we have a result ID, store the error
            if ($this->resultId) {
                $this->storeError($e->getMessage());
            }

            // Dispatch error event
            event(
                new AiChatCompleted(
                    response: null,
                    resultId: $this->resultId,
                    jobId   : $this->job?->getJobId(),
                    error   : $e->getMessage()));

            // Re-throw the exception to trigger Laravel's retry mechanism
            throw $e;
        }
    }

    private function storeResponse(array $response): void
    {
        $cacheKey = $this->getResultCacheKey();

        Cache::put($cacheKey, ['status' => 'completed',
            'response' => $response,
            'completed_at' => now()->toISOString(), ], now()->addHours(24)); // Keep for 24 hours
    }

    private function getResultCacheKey(): string
    {
        return "ai_job_result:{$this->resultId}";
    }

    private function storeError(string $error): void
    {
        $cacheKey = $this->getResultCacheKey();

        Cache::put($cacheKey, ['status' => 'failed',
            'error' => $error,
            'failed_at' => now()->toISOString(), ], now()->addHours(24)); // Keep for 24 hours
    }

    public function failed(Throwable $exception): void
    {
        // Log the final failure
        if (config('ai.logging.enabled')) {
            Log::channel(config('ai.logging.channel', 'stack'))->error(
                'AI Chat Job Permanently Failed',
                ['result_id' => $this->resultId,
                    'job_id' => $this->job?->getJobId(),
                    'error' => $exception->getMessage(),
                    'attempts' => $this->attempts(), ]);
        }

        // Store the final failure if we have a result ID
        if ($this->resultId) {
            $this->storeError($exception->getMessage());
        }

        // Dispatch failed event
        event(
            new AiChatCompleted(
                response: null,
                resultId: $this->resultId,
                jobId   : $this->job?->getJobId(),
                error   : $exception->getMessage(),
                failed  : true));
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getResultId(): ?string
    {
        return $this->resultId;
    }
}
