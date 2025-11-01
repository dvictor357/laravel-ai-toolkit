<?php

namespace AIToolkit\AIToolkit\Filament\Resources;

use AIToolkit\AIToolkit\Filament\Resources\AIProviderResource\Pages;
use AIToolkit\AIToolkit\Services\EncryptionService;
use AIToolkit\AIToolkit\Support\AIProviderConfiguration;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AIProviderResource extends Resource
{
    protected static ?string $model = AIProviderConfiguration::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'AI Providers';

    protected static ?string $modelLabel = 'AI provider';

    protected static ?string $pluralModelLabel = 'AI providers';

    protected static string|null|\UnitEnum $navigationGroup = 'AI Management';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count() > 0 ? (string) static::getModel()::count() : null;
    }

    public static function canViewAny(): bool
    {
        return config('filament.ai_toolkit.management.enabled', true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(
            [Section::make('Provider Configuration')->schema(
                [Select::make('name')
                    ->label('Provider Name')
                    ->options(['openai' => 'OpenAI',
                        'anthropic' => 'Anthropic Claude',
                        'groq' => 'Groq', ])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('is_default', false)),

                    TextInput::make('display_name')->label('Display Name')->required()->maxLength(
                        255),

                    TextInput::make('api_key')->label('API Key')->password()->required()->maxLength(
                        255)->helperText('Your API key will be encrypted for security'),

                    Select::make('default_model')->label('Default Model')->options(
                        function (Get $get) {
                            $provider = $get('name');

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
                        })->required(),

                    TextInput::make('default_max_tokens')->label('Default Max Tokens')->numeric()->default(1024)->minValue(
                        1)->maxValue(4096),

                    TextInput::make('default_temperature')->label('Default Temperature')->numeric()->step(
                        '0.1')->default(0.7)->minValue(0.0)->maxValue(2.0),

                    Toggle::make('is_default')->label('Set as Default Provider')->helperText(
                        'Only one provider can be the default')->default(false), ])->columns(2),

                Section::make('Status')->schema(
                    [Toggle::make('is_enabled')->label('Enable Provider')->default(true)->helperText(
                        'Disable to stop using this provider'),

                        Textarea::make('notes')->label('Notes')->rows(3)->maxLength(500), ]), ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(
                [TextColumn::make('display_name')->label('Provider')->weight(FontWeight::Bold)->searchable(),

                    TextColumn::make('name')->label('Type')->badge()->color(
                        fn (string $state): string => match ($state) {
                            'openai' => 'success',
                            'anthropic' => 'warning',
                            'groq' => 'info',
                            default => 'gray',
                        }),

                    TextColumn::make('api_key')->label('API Key')->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return 'Not set';
                        }
                        $encryptionService = app(EncryptionService::class);

                        return $encryptionService->maskApiKey($state);
                    })->toggleable(isToggledHiddenByDefault: true),

                    TextColumn::make('default_model')->label('Default Model')->searchable(),

                    IconColumn::make('is_enabled')->label('Status')->boolean()->sortable(),

                    IconColumn::make('is_default')->label('Default')->boolean()->sortable()->toggleable(
                        isToggledHiddenByDefault: true),

                    TextColumn::make('created_at')->label('Created')->dateTime()->sortable()->toggleable(
                        isToggledHiddenByDefault: true), ])->filters(
                            [TernaryFilter::make('is_enabled')->label('Enabled'),
                                TernaryFilter::make('is_default')->label('Default Provider'), ])->recordActions(
                                    [\Filament\Actions\Action::make('test_connection')
                                        ->label('Test Connection')
                                        ->icon('heroicon-m-bolt')
                                        ->color('success')
                                        ->action(function (Model $record) {
                                            try {
                                                // Temporarily set the API key for testing
                                                $encryptionService = app(EncryptionService::class);
                                                $apiKey = $encryptionService->decryptApiKey($record->api_key);

                                                // Create provider with decrypted key
                                                $provider = match ($record->name) {
                                                    'openai' => new \AIToolkit\AIToolkit\Providers\OpenAiProvider($apiKey),
                                                    'anthropic' => new \AIToolkit\AIToolkit\Providers\AnthropicProvider($apiKey),
                                                    'groq' => new \AIToolkit\AIToolkit\Providers\GroqProvider($apiKey),
                                                    default => throw new \Exception('Unknown provider'),
                                                };

                                                // Test with simple message
                                                $testResult = $provider->chat('Hello', ['model' => $record->default_model,
                                                    'max_tokens' => 10, ]);

                                                Notification::make()->title('Connection Test Successful')->body(
                                                    "Response: {$testResult['content']}")->success()->send();
                                            } catch (\Exception $e) {
                                                Notification::make()
                                                    ->title('Connection Test Failed')
                                                    ->body($e->getMessage())
                                                    ->danger()
                                                    ->send();
                                            }
                                        }),

                                        EditAction::make(),
                                        DeleteAction::make(), ])->toolbarActions(
                                            [BulkActionGroup::make([DeleteBulkAction::make()])])->defaultSort(
                                                'is_default',
                                                'desc')->paginated([10, 25, 50, 100])->poll('30s');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAIProviders::route('/')];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'display_name', 'default_model'];
    }
}
