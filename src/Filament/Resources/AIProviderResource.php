<?php

namespace AIToolkit\AIToolkit\Filament\Resources;

use AIToolkit\AIToolkit\Filament\Resources\AIProviderResource\Pages;
use AIToolkit\AIToolkit\Support\AIProviderConfiguration;
use AIToolkit\AIToolkit\Services\EncryptionService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AIProviderResource extends Resource
{
    protected static ?string $model = AIProviderConfiguration::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'AI Providers';

    protected static ?string $modelLabel = 'AI provider';

    protected static ?string $pluralModelLabel = 'AI providers';

    protected static ?string $navigationGroup = 'AI Management';

    protected static ?string $navigationBadgeColor = 'success';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count() > 0 ? (string) static::getModel()::count() : null;
    }

    public static function canViewAny(): bool
    {
        return config('filament.ai_toolkit.management.enabled', true);
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Provider Configuration')
                    ->schema([
                        Forms\Components\Select::make('name')
                            ->label('Provider Name')
                            ->options([
                                'openai' => 'OpenAI',
                                'anthropic' => 'Anthropic Claude',
                                'groq' => 'Groq',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('is_default', false)),

                        Forms\Components\TextInput::make('display_name')
                            ->label('Display Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('api_key')
                            ->label('API Key')
                            ->password()
                            ->required()
                            ->maxLength(255)
                            ->helperText('Your API key will be encrypted for security'),

                        Forms\Components\Select::make('default_model')
                            ->label('Default Model')
                            ->options(function (Forms\Get $get) {
                                $provider = $get('name');

                                return match ($provider) {
                                    'openai' => [
                                        'gpt-4o' => 'GPT-4o',
                                        'gpt-4o-mini' => 'GPT-4o Mini',
                                        'gpt-4-turbo' => 'GPT-4 Turbo',
                                        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                                    ],
                                    'anthropic' => [
                                        'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
                                        'claude-3-opus-20240229' => 'Claude 3 Opus',
                                        'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
                                        'claude-3-haiku-20240307' => 'Claude 3 Haiku',
                                    ],
                                    'groq' => [
                                        'mixtral-8x7b-32768' => 'Mixtral 8x7B',
                                        'llama2-70b-4096' => 'Llama 2 70B',
                                        'gemma-7b-it' => 'Gemma 7B',
                                    ],
                                    default => [],
                                };
                            })
                            ->required(),

                        Forms\Components\TextInput::make('default_max_tokens')
                            ->label('Default Max Tokens')
                            ->numeric()
                            ->default(1024)
                            ->minValue(1)
                            ->maxValue(4096),

                        Forms\Components\TextInput::make('default_temperature')
                            ->label('Default Temperature')
                            ->numeric()
                            ->step('0.1')
                            ->default(0.7)
                            ->minValue(0.0)
                            ->maxValue(2.0),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Set as Default Provider')
                            ->helperText('Only one provider can be the default')
                            ->default(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_enabled')
                            ->label('Enable Provider')
                            ->default(true)
                            ->helperText('Disable to stop using this provider'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->maxLength(500),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Provider')
                    ->weight(FontWeight::Bold)
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'openai' => 'success',
                        'anthropic' => 'warning',
                        'groq' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('api_key')
                    ->label('API Key')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return 'Not set';
                        }
                        $encryptionService = app(EncryptionService::class);
                        return $encryptionService->maskApiKey($state);
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('default_model')
                    ->label('Default Model')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label('Enabled'),
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default Provider'),
            ])
            ->actions([
                Tables\Actions\Action::make('test_connection')
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
                            $testResult = $provider->chat('Hello', [
                                'model' => $record->default_model,
                                'max_tokens' => 10,
                            ]);

                            Notification::make()
                                ->title('Connection Test Successful')
                                ->body("Response: {$testResult['content']}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Connection Test Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('is_default', 'desc')
            ->paginated([10, 25, 50, 100])
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAIProviders::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'display_name', 'default_model'];
    }

    /**
     * Encrypt API key before saving to database
     */
    public static function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['api_key']) && !empty($data['api_key'])) {
            $encryptionService = app(EncryptionService::class);

            // Only encrypt if not already encrypted
            if (!$encryptionService->isEncrypted($data['api_key'])) {
                $data['api_key'] = $encryptionService->encryptApiKey($data['api_key']);
            }
        }

        return $data;
    }

    /**
     * Test connection with decrypted API key
     */
    public static function afterTestConnection(Model $record): void
    {
        // This is handled in the action above
    }
}
