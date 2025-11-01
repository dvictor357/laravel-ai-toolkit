<?php

namespace AIToolkit\AIToolkit\Filament\Pages;

use AIToolkit\AIToolkit\Contracts\AIProviderContract;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class AIChatDashboard extends Page implements HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'AI Chat';

    protected static string|null|\UnitEnum $navigationGroup = 'AI Management';

    protected static ?string $title = 'AI Chat Dashboard';

    public string $message = '';

    public string $selectedProvider = 'openai';

    public string $selectedModel = 'gpt-4o';

    public array $response = [];

    public bool $isLoading = false;

    public array $conversationHistory = [];

    public int $maxTokens = 1024;

    public float $temperature = 0.7;

    protected static ?string $slug = 'ai-chat';

    protected string $view = 'filament.pages.ai-chat-dashboard';

    public function mount(): void
    {
        $this->loadProviders();
    }

    public function loadProviders(): void
    {
        // Load available providers from configuration
        $providers = config('ai-toolkit.providers', []);
        $this->selectedProvider = config('ai-toolkit.default_provider', 'openai');
    }

    protected function getFormSchema(): array
    {
        return [Section::make('Chat Configuration')->schema(
            [Select::make('selectedProvider')
                ->label('AI Provider')
                ->options(['openai' => 'OpenAI',
                    'anthropic' => 'Anthropic Claude',
                    'groq' => 'Groq', ])
                ->reactive()
                ->afterStateUpdated(function ($state) {
                    $this->updateModels($state);
                }),

                Select::make('selectedModel')->label('Model')->options(function (Get $get) {
                    return $this->getModelsForProvider($get('selectedProvider'));
                })->required(),

                TextInput::make('maxTokens')->label('Max Tokens')->numeric()->default(1024)->minValue(
                    1)->maxValue(4096),

                TextInput::make('temperature')->label('Temperature')->numeric()->step('0.1')->default(
                    0.7)->minValue(0.0)->maxValue(2.0), ])->columns(2),

            Section::make('Your Message')->schema(
                [RichEditor::make('message')
                    ->label('Message')
                    ->required()
                    ->placeholder(
                        'Ask AI anything...')
                    ->helperText('Enter your question or prompt here')
                    ->disableAllToolbarButtons()
                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList']),

                    Actions::make(
                        [Action::make('send')->label('Send Message')->submit('sendMessage')->color(
                            'primary')->icon('heroicon-m-paper-airplane'), ]), ]), ];
    }

    protected function updateModels(string $provider): void
    {
        $models = $this->getModelsForProvider($provider);
        $this->selectedModel = array_key_first($models);
    }

    protected function getModelsForProvider(string $provider): array
    {
        return match ($provider) {
            'openai' => ['gpt-4o' => 'GPT-4o',
                'gpt-4o-mini' => 'GPT-4o Mini',
                'gpt-4-turbo' => 'GPT-4 Turbo',
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo', ],
            'anthropic' => ['claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
                'claude-3-opus-20240229' => 'Claude 3 Opus',
                'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
                'claude-3-haiku-20240307' => 'Claude 3 Haiku', ],
            'groq' => ['mixtral-8x7b-32768' => 'Mixtral 8x7B',
                'llama2-70b-4096' => 'Llama 2 70B',
                'gemma-7b-it' => 'Gemma 7B', ],
            default => [],
        };
    }

    public function sendMessage(): void
    {
        if (empty(trim($this->message))) {
            Notification::make()
                ->title('Message Required')
                ->body('Please enter a message to send.')
                ->warning()
                ->send();

            return;
        }

        $this->isLoading = true;

        try {
            $provider = app(AIProviderContract::class);

            $options = ['model' => $this->selectedModel,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature, ];

            $this->response = $provider->chat($this->message, $options);

            // Add to conversation history
            $this->conversationHistory[] = ['timestamp' => now()->format('Y-m-d H:i:s'),
                'message' => $this->message,
                'response' => $this->response['content'],
                'provider' => $this->selectedProvider,
                'model' => $this->selectedModel,
                'usage' => $this->response['usage'] ?? null, ];

            Notification::make()->title('Message Sent Successfully')->body(
                "Response received from {$this->selectedProvider}")->success()->send();

            // Clear the message
            $this->message = '';
        } catch (\Exception $e) {
            Notification::make()->title('Message Failed')->body($e->getMessage())->danger()->send();

            $this->response = ['content' => 'Error: '.$e->getMessage(),
                'error' => true, ];
        } finally {
            $this->isLoading = false;
        }
    }

    public function startStream(): void
    {
        if (empty(trim($this->message))) {
            Notification::make()
                ->title('Message Required')
                ->body('Please enter a message to stream.')
                ->warning()
                ->send();

            return;
        }

        try {
            $provider = app(AIProviderContract::class);
            $stream = $provider->stream($this->message, ['model' => $this->selectedModel,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature, ]);

            // In Filament context, streaming is handled differently
            // This would need to be implemented using Livewire events or similar
            $this->dispatch('stream-started', ['message' => $this->message]);

            Notification::make()
                ->title('Stream Started')
                ->body('Streaming response from '.$this->selectedProvider)
                ->info()
                ->send();
        } catch (\Exception $e) {
            Notification::make()->title('Stream Failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function clearHistory(): void
    {
        $this->conversationHistory = [];

        Notification::make()
            ->title('History Cleared')
            ->body('Conversation history has been cleared.')
            ->success()
            ->send();
    }

    public function exportConversation(): void
    {
        if (empty($this->conversationHistory)) {
            Notification::make()
                ->title('No Data to Export')
                ->body('Conversation history is empty.')
                ->warning()
                ->send();

            return;
        }

        $filename = 'ai-conversation-'.now()->format('Y-m-d-H-i-s').'.json';

        $content = json_encode($this->conversationHistory, JSON_PRETTY_PRINT);

        // For web response, we would need to use a proper HTTP response
        // In Filament context, this is handled differently
        $this->dispatch('download-file', ['filename' => $filename,
            'content' => base64_encode($content), ]);

        Notification::make()
            ->title('File Ready for Download')
            ->body("Conversation exported as {$filename}")
            ->success()
            ->send();
    }
}
