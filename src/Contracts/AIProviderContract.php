<?php

namespace AIToolkit\AIToolkit\Contracts;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface AIProviderContract
{
    public function chat(string $prompt, array $options = []): array;

    public function stream(string $prompt, array $options = []): StreamedResponse;

    public function embed(string $text): array;  // For vector embeddings
}
