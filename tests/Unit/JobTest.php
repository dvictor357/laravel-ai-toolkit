<?php

use AIToolkit\AIToolkit\Contracts\AIProviderContract;
use AIToolkit\AIToolkit\Jobs\AiChatJob;
use Illuminate\Support\Facades\Cache;

describe('AiChatJob', function () {
    beforeEach(function () {
        config(['ai.cache.enabled' => false]);
        config(['ai.logging.enabled' => false]);
        Cache::flush();
    });

    it('can be created with prompt', function () {
        $job = new AiChatJob('test prompt');

        expect($job->getPrompt())->toBe('test prompt');
        expect($job->getOptions())->toBe([]);
        expect($job->getResultId())->toBeNull();
    });

    it('can be created with options', function () {
        $options = ['temperature' => 0.7, 'max_tokens' => 100];
        $job = new AiChatJob('test prompt', $options);

        expect($job->getOptions())->toBe($options);
    });

    it('can be created with result ID', function () {
        $job = new AiChatJob('test prompt', [], 'result-123');

        expect($job->getResultId())->toBe('result-123');
    });

    it('has proper queue configuration', function () {
        config(['ai.queue.tries' => 5]);

        $job = new AiChatJob('test prompt');
        expect($job->tries)->toBe(5);
    });

    it('handles successful chat completion', function () {
        $mockProvider = mock(AIProviderContract::class);
        $mockProvider
            ->shouldReceive('chat')->with('test prompt', ['temperature' => 0.7])->once()->andReturn(
                ['content' => 'AI response',
                    'usage' => ['total_tokens' => 100], ]);

        $job = new AiChatJob('test prompt', ['temperature' => 0.7], 'result-123');

        expect(fn () => $job->handle($mockProvider))->not->toThrow();
    });

    it('stores successful result when result ID is provided', function () {
        $mockProvider = mock(AIProviderContract::class);
        $mockProvider
            ->shouldReceive('chat')->once()->andReturn(['content' => 'AI response',
                'usage' => ['total_tokens' => 100], ]);

        $job = new AiChatJob('test prompt', [], 'result-123');
        $job->handle($mockProvider);

        $cached = Cache::get('ai_job_result:result-123');
        expect($cached['status'])->toBe('completed');
        expect($cached['response']['content'])->toBe('AI response');
        expect($cached['completed_at'])->not->toBeNull();
    });

    it('handles failed chat completion', function () {
        $mockProvider = mock(AIProviderContract::class);
        $mockProvider
            ->shouldReceive('chat')->once()->andThrow(new Exception('API error'));

        $job = new AiChatJob('test prompt', [], 'result-123');

        expect(fn () => $job->handle($mockProvider))->toThrow(Exception::class, 'API error');
    });

    it('stores error when job fails with result ID', function () {
        $mockProvider = mock(AIProviderContract::class);
        $mockProvider
            ->shouldReceive('chat')->once()->andThrow(new Exception('API error'));

        $job = new AiChatJob('test prompt', [], 'result-123');

        try {
            $job->handle($mockProvider);
        } catch (Exception $e) {
            // Expected
        }

        $cached = Cache::get('ai_job_result:result-123');
        expect($cached['status'])->toBe('failed');
        expect($cached['error'])->toBe('API error');
        expect($cached['failed_at'])->not->toBeNull();
    });

    it('calls failed method when job permanently fails', function () {
        $job = new AiChatJob('test prompt', [], 'result-123');

        expect(fn () => $job->failed(new Exception('Final failure')))->not->toThrow();
    });

    it('stores error when job permanently fails', function () {
        $job = new AiChatJob('test prompt', [], 'result-123');

        $job->failed(new Exception('Final failure'));

        $cached = Cache::get('ai_job_result:result-123');
        expect($cached['status'])->toBe('failed');
        expect($cached['error'])->toBe('Final failure');
    });
});
