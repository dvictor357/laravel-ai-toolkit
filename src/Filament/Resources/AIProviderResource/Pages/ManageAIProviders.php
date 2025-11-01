<?php

namespace AIToolkit\AIToolkit\Filament\Resources\AIProviderResource\Pages;

use AIToolkit\AIToolkit\Filament\Resources\AIProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAIProviders extends ManageRecords
{
    protected static string $resource = AIProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Provider')
                ->mutateFormDataUsing(function (array $data): array {
                    // If this provider is set as default, unset others
                    if ($data['is_default'] ?? false) {
                        \AIToolkit\AIToolkit\Support\AIProviderConfiguration::where('is_default', true)
                            ->update(['is_default' => false]);
                    }

                    return $data;
                }),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }
}
