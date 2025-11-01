<?php

namespace AIToolkit\AIToolkit\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AIResponseChunk implements ShouldBroadcast
{
    public function __construct(public string $chunk) {}

    public function broadcastOn(): array
    {
        return [new Channel('ai-stream')];
    }
}
