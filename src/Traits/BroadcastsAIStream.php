<?php

namespace AIToolkit\AIToolkit\Traits;

use AIToolkit\AIToolkit\Events\AIResponseChunk;
use Exception;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait BroadcastsAIStream
{
    /**
     * Stream AI response and broadcast chunks via events for real-time updates.
     *
     * @param  array  $payload  The request payload
     * @param  callable  $streamCallback  Callback to execute the streaming request
     * @param  string|null  $channel  Broadcast channel name
     */
    protected function streamWithBroadcast(
        array $payload,
        callable $streamCallback,
        ?string $channel = null,
    ): StreamedResponse {
        $channel = $channel ?? config('ai.broadcasting.channel', 'ai-stream');

        return response()->stream(function () use ($payload, $streamCallback) {
            try {
                // Send start event
                event(new AIResponseChunk('__START__'));

                $streamCallback($payload, function ($chunk) {
                    if (! empty($chunk)) {
                        event(new AIResponseChunk($chunk));
                        echo $chunk; // Also output to HTTP stream for backward compatibility
                        flush();
                    }
                });

                // Send end event
                event(new AIResponseChunk('__END__'));
            } catch (Exception $e) {
                event(new AIResponseChunk('__ERROR__:'.$e->getMessage()));
                throw $e;
            }
        }, 200, ['Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'Connection' => 'keep-alive', ]);
    }
}
