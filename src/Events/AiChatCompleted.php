<?php

namespace AIToolkit\AIToolkit\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class AiChatCompleted implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public ?array $response,
        public ?string $resultId = null,
        public ?string $jobId = null,
        public ?string $error = null,
        public bool $failed = false,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [new Channel('ai-chat-jobs')];

        if ($this->resultId) {
            // Also broadcast on a specific channel for this result
            $channels[] = new Channel("ai-chat-result.{$this->resultId}");
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return ['result_id' => $this->resultId,
            'job_id' => $this->jobId,
            'response' => $this->response,
            'error' => $this->error,
            'failed' => $this->failed,
            'timestamp' => now()->toISOString(), ];
    }

    public function broadcastAs(): string
    {
        return 'ai.chat.completed';
    }
}
