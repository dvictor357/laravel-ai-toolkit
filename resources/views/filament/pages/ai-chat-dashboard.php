<?php use Illuminate\Support\Str; ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $this->title }}</title>
    <style>
        .chat-container {
            height: 400px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            background-color: #f9fafb;
        }
        
        .message {
            margin-bottom: 12px;
            padding: 8px 12px;
            border-radius: 8px;
            max-width: 80%;
        }
        
        .user-message {
            background-color: #3b82f6;
            color: white;
            margin-left: auto;
            text-align: right;
        }
        
        .ai-message {
            background-color: #e5e7eb;
            color: #1f2937;
            margin-right: auto;
        }
        
        .conversation-item {
            border-left: 4px solid #3b82f6;
            padding: 12px;
            margin-bottom: 16px;
            background-color: white;
            border-radius: 4px;
        }
        
        .timestamp {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        .provider-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 12px;
            background-color: #dbeafe;
            color: #1d4ed8;
        }
    </style>
</head>
<body>
    <div class="space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $this->title }}</h1>
            <p class="text-sm text-gray-600">Interactive AI chat interface with real-time streaming</p>
        </div>

        <!-- Configuration Form -->
        <div>
            {{ $this->form }}
        </div>

        <!-- Current Response -->
        @if(!empty($response))
            <div class="bg-white p-6 rounded-lg border border-gray-200">
                <h3 class="text-lg font-semibold mb-4">Response</h3>
                
                @if(isset($response['error']))
                    <div class="bg-red-50 border border-red-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700">{{ $response['content'] }}</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-gray-50 rounded-md p-4">
                        <pre class="text-sm text-gray-800 whitespace-pre-wrap">{{ $response['content'] }}</pre>
                        
                        @if(isset($response['usage']))
                            <div class="mt-4 text-xs text-gray-500">
                                <span class="font-medium">Usage:</span> 
                                Total: {{ $response['usage']['total_tokens'] ?? 'N/A' }} tokens
                                (Prompt: {{ $response['usage']['prompt_tokens'] ?? 'N/A' }}, 
                                Completion: {{ $response['usage']['completion_tokens'] ?? 'N/A' }})
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        <!-- Loading Indicator -->
        @if($isLoading)
            <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="animate-spin h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">AI is thinking...</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Conversation History -->
        @if(!empty($conversationHistory))
            <div class="bg-white p-6 rounded-lg border border-gray-200">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Conversation History</h3>
                    <div class="space-x-2">
                        <button wire:click="exportConversation" class="text-sm text-blue-600 hover:text-blue-800">
                            Export
                        </button>
                        <button wire:click="clearHistory" class="text-sm text-red-600 hover:text-red-800">
                            Clear
                        </button>
                    </div>
                </div>
                
                <div class="space-y-4">
                    @foreach($conversationHistory as $index => $conversation)
                        <div class="conversation-item">
                            <div class="flex justify-between items-start mb-2">
                                <div class="timestamp">{{ $conversation['timestamp'] }}</div>
                                <div class="space-x-2">
                                    <span class="provider-badge">{{ ucfirst($conversation['provider']) }}</span>
                                    <span class="text-xs text-gray-500">{{ $conversation['model'] }}</span>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-medium text-gray-700 mb-1">You:</div>
                                    <div class="text-sm text-gray-900">{{ Str::limit($conversation['message'], 100) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-gray-700 mb-1">AI:</div>
                                    <div class="text-sm text-gray-900">{{ Str::limit($conversation['response'], 100) }}</div>
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
            </div>
        @endif

        <!-- Quick Actions -->
        <div class="bg-white p-4 rounded-lg border border-gray-200">
            <h4 class="text-sm font-semibold text-gray-900 mb-2">Quick Actions</h4>
            <div class="flex flex-wrap gap-2">
                <button wire:click="$set('message', 'Tell me a joke about programming')" class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded">
                    Programming Joke
                </button>
                <button wire:click="$set('message', 'Explain quantum computing in simple terms')" class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded">
                    Quantum Computing
                </button>
                <button wire:click="$set('message', 'What are the latest trends in AI?')" class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded">
                    AI Trends
                </button>
                <button wire:click="$set('message', 'Write a short poem about Laravel')" class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded">
                    Laravel Poem
                </button>
            </div>
        </div>
    </div>
</body>
</html>
