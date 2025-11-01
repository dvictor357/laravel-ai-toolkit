<x-filament-panels::page>
  {{-- Header with title and description --}}
  <div class="space-y-2">
    <h1 class="text-2xl font-bold tracking-tight">
      {{ $this->title }}
    </h1>
    <p class="text-sm text-gray-600">
      Interactive AI chat interface with real-time streaming
    </p>
  </div>

  {{-- Configuration Form --}}
  <x-filament::section>
    <x-slot name="heading">
      Chat Configuration
    </x-slot>

    {{ $this->form }}
  </x-filament::section>

  {{-- Current Response Display --}}
  @if(!empty($response))
  <x-filament::section>
    <x-slot name="heading">
      Response
    </x-slot>

    @if(isset($response['error']))
    <div class="rounded-lg bg-red-50 dark:bg-red-900/20 p-4 border border-red-200 dark:border-red-800">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                  clip-rule="evenodd"/>
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-sm text-red-700 dark:text-red-300">{{ $response['content'] }}</p>
        </div>
      </div>
    </div>
    @else
    <div class="prose prose-sm max-w-none">
      <pre
        class="whitespace-pre-wrap text-sm text-gray-800 dark:text-gray-200 font-sans">{{ $response['content'] }}</pre>

      @if(isset($response['usage']))
      <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
        <span class="font-medium">Usage:</span>
        Total: {{ $response['usage']['total_tokens'] ?? 'N/A' }} tokens
        (Prompt: {{ $response['usage']['prompt_tokens'] ?? 'N/A' }},
        Completion: {{ $response['usage']['completion_tokens'] ?? 'N/A' }})
      </div>
      @endif
    </div>
    @endif
  </x-filament::section>
  @endif

  {{-- Loading Indicator --}}
  @if($isLoading)
  <x-filament::section>
    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4 border border-blue-200 dark:border-blue-800">
      <div class="flex items-center gap-3">
        <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg"
             fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="text-sm text-blue-700 dark:text-blue-300">AI is thinking...</span>
      </div>
    </div>
  </x-filament::section>
  @endif

  {{-- Conversation History --}}
  @if(!empty($conversationHistory))
  <x-filament::section>
    <x-slot name="heading">
      Conversation History
    </x-slot>

    <x-slot name="headerEnd">
      <div class="flex gap-2">
        <x-filament::button
          color="gray"
          size="sm"
          wire:click="exportConversation"
        >
          Export
        </x-filament::button>

        <x-filament::button
          color="danger"
          size="sm"
          wire:click="clearHistory"
        >
          Clear
        </x-filament::button>
      </div>
    </x-slot>

    <div class="space-y-4">
      @foreach($conversationHistory as $index => $conversation)
      <div class="border-l-4 border-blue-500 pl-4 py-3 bg-white dark:bg-gray-800 rounded-r-md">
        <div class="flex justify-between items-start mb-2">
          <div class="text-xs text-gray-500">
            {{ $conversation['timestamp'] }}
          </div>
          <div class="flex gap-2">
                                <span
                                  class="text-xs px-2 py-1 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded-full">
                                    {{ ucfirst($conversation['provider']) }}
                                </span>
            <span class="text-xs text-gray-500">{{ $conversation['model'] }}</span>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
              You:
            </div>
            <div class="text-sm text-gray-900 dark:text-gray-100">
              {{ Str::limit($conversation['message'], 100) }}
            </div>
          </div>
          <div>
            <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
              AI:
            </div>
            <div class="text-sm text-gray-900 dark:text-gray-100">
              {{ Str::limit($conversation['response'], 100) }}
            </div>
          </div>
        </div>

        @if($conversation['usage'])
        <div class="mt-2 text-xs text-gray-500">
          Tokens: {{ $conversation['usage']['total_tokens'] ?? 'N/A' }}
        </div>
        @endif
      </div>
      @endforeach
    </div>
  </x-filament::section>
  @endif

  {{-- Quick Actions --}}
  <x-filament::section>
    <x-slot name="heading">
      Quick Actions
    </x-slot>

    <div class="flex flex-wrap gap-2">
      <x-filament::button
        color="gray"
        size="xs"
        wire:click="$set('message', 'Tell me a joke about programming')"
      >
        Programming Joke
      </x-filament::button>

      <x-filament::button
        color="gray"
        size="xs"
        wire:click="$set('message', 'Explain quantum computing in simple terms')"
      >
        Quantum Computing
      </x-filament::button>

      <x-filament::button
        color="gray"
        size="xs"
        wire:click="$set('message', 'What are the latest trends in AI?')"
      >
        AI Trends
      </x-filament::button>

      <x-filament::button
        color="gray"
        size="xs"
        wire:click="$set('message', 'Write a short poem about Laravel')"
      >
        Laravel Poem
      </x-filament::button>
    </div>
  </x-filament::section>
</x-filament-panels::page>
