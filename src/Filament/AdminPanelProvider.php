<?php

namespace AIToolkit\AIToolkit\Filament;

use AIToolkit\AIToolkit\Filament\Pages\AIChatDashboard;
use AIToolkit\AIToolkit\Filament\Resources\AIProviderResource;
use AIToolkit\AIToolkit\Filament\Widgets\AIUsageStatsWidget;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('ai-toolkit-admin')
            ->path('admin')
            ->login()
            ->colors(['primary' => Color::Blue,
                'gray' => Color::Slate,
                'info' => Color::Cyan,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose, ])
            ->font(
                'Inter')
            ->brandName('AI Toolkit Admin')
            ->brandLogoHeight('2rem')
            ->discoverResources(
                in: __DIR__.'/Resources')
            ->discoverPages(in: __DIR__.'/Pages')
            ->discoverWidgets(in: __DIR__.'/Widgets')
            ->middleware([\Filament\Http\Middleware\DisableBladeIconComponents::class,
                \Filament\Http\Middleware\DispatchServingFilamentEvent::class, ])
            ->plugins([// Register plugins conditionally
                ...($this->isExceptionsPluginEnabled() ? [\BezhanSalleh\FilamentExceptions\FilamentExceptionsPlugin::make()] : []), ])
            ->authMiddleware([\Filament\Http\Middleware\Authenticate::class])
            ->databaseNotifications()
            ->sidebarWidth('16rem')
            ->topbarHeight('4rem')
            ->widgets([AIUsageStatsWidget::class])
            ->pages([AIChatDashboard::class])
            ->resources([AIProviderResource::class]);
    }

    protected function isExceptionsPluginEnabled(): bool
    {
        return class_exists(\BezhanSalleh\FilamentExceptions\FilamentExceptionsPlugin::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Boot-time initialization if needed
        if (config('filament.admin.enabled', true)) {
            // Initialize AI Toolkit specific Filament settings
            $this->initializeAiToolkitSettings();
        }
    }

    protected function initializeAiToolkitSettings(): void
    {
        // Set up AI Toolkit specific configurations
        config(['filament.admin.auth.authenticator' => 'eloquent',
            'filament.admin.auth.model' => config('auth.providers.users.model'),
            'filament.admin.brand_name' => 'AI Toolkit Admin',
            'filament.admin.title' => 'AI Toolkit Administration', ]);
    }
}
