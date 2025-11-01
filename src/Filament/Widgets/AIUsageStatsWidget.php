<?php

namespace AIToolkit\AIToolkit\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AIUsageStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * Get the stats to display.
     *
     * @return array<\Filament\Widgets\StatsOverviewWidget\Stat>
     */
    protected function getStats(): array
    {
        $stats = $this->getUsageStats();

        return [
            Stat::make('Total Requests', number_format($stats['total_requests']))
                ->description($stats['requests_change'] >= 0 ? '+'.$stats['requests_change'].'% from last period' : $stats['requests_change'].'% from last period')
                ->descriptionIcon($stats['requests_change'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($stats['requests_change'] >= 0 ? 'success' : 'danger'),

            Stat::make('Active Providers', $stats['active_providers'])
                ->description('Currently enabled AI providers')
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('info'),

            Stat::make('Cache Hit Rate', $stats['cache_hit_rate'].'%')
                ->description($stats['cache_change'] >= 0 ? '+'.$stats['cache_change'].'% from last period' : $stats['cache_change'].'% from last period')
                ->descriptionIcon($stats['cache_change'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($stats['cache_hit_rate'] >= 80 ? 'success' : ($stats['cache_hit_rate'] >= 60 ? 'warning' : 'danger')),

            Stat::make('Avg Response Time', $stats['avg_response_time'].'ms')
                ->description($stats['response_time_change'] <= 0 ? abs($stats['response_time_change']).'% faster than last period' : $stats['response_time_change'].'% slower than last period')
                ->descriptionIcon($stats['response_time_change'] <= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($stats['response_time_change'] <= 0 ? 'success' : 'danger'),

            Stat::make('Success Rate', $stats['success_rate'].'%')
                ->description($stats['success_rate_change'] >= 0 ? '+'.$stats['success_rate_change'].'% from last period' : $stats['success_rate_change'].'% from last period')
                ->descriptionIcon($stats['success_rate_change'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($stats['success_rate'] >= 95 ? 'success' : ($stats['success_rate'] >= 90 ? 'warning' : 'danger')),

            Stat::make('Error Rate', $stats['error_rate'].'%')
                ->description($stats['error_rate_change'] <= 0 ? abs($stats['error_rate_change']).'% decrease from last period' : '+'.$stats['error_rate_change'].'% from last period')
                ->descriptionIcon($stats['error_rate_change'] <= 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')
                ->color($stats['error_rate'] <= 5 ? 'success' : ($stats['error_rate'] <= 10 ? 'warning' : 'danger')),
        ];
    }

    protected function getUsageStats(): array
    {
        return Cache::remember('ai_usage_stats', 300, function () {
            return [
                'total_requests' => $this->getTotalRequests(),
                'requests_change' => $this->getRequestsChange(),
                'active_providers' => $this->getActiveProviders(),
                'cache_hit_rate' => $this->getCacheHitRate(),
                'cache_change' => $this->getCacheChange(),
                'avg_response_time' => $this->getAvgResponseTime(),
                'response_time_change' => $this->getResponseTimeChange(),
                'success_rate' => $this->getSuccessRate(),
                'success_rate_change' => $this->getSuccessRateChange(),
                'error_rate' => $this->getErrorRate(),
                'error_rate_change' => $this->getErrorRateChange(),
            ];
        });
    }

    protected function getTotalRequests(): int
    {
        // In a real application, you would track this in a database
        // For now, we'll return a simulated value
        return rand(1000, 5000);
    }

    protected function getRequestsChange(): int
    {
        // Percentage change from previous period
        return rand(-20, 30);
    }

    protected function getActiveProviders(): int
    {
        // Count enabled providers from configuration
        $providers = config('ai-toolkit.providers', []);

        return count(array_filter($providers, fn ($provider) => $provider['enabled'] ?? false));
    }

    protected function getCacheHitRate(): int
    {
        // Calculate cache hit rate from actual metrics
        // For demo purposes, return a simulated value
        return rand(70, 95);
    }

    protected function getCacheChange(): int
    {
        return rand(-10, 15);
    }

    protected function getAvgResponseTime(): int
    {
        // Average response time in milliseconds
        // For demo purposes, return a simulated value
        return rand(500, 2000);
    }

    protected function getResponseTimeChange(): int
    {
        // Percentage change in response time (negative means faster)
        return rand(-20, 25);
    }

    protected function getSuccessRate(): int
    {
        // Success rate percentage
        // For demo purposes, return a simulated value
        return rand(88, 99);
    }

    protected function getSuccessRateChange(): int
    {
        return rand(-5, 10);
    }

    protected function getErrorRate(): int
    {
        // Error rate percentage (inverse of success rate)
        return 100 - $this->getSuccessRate();
    }

    protected function getErrorRateChange(): int
    {
        // Percentage change in error rate (negative means fewer errors)
        return rand(-5, 5);
    }

    public static function canView(): bool
    {
        return config('filament.ai_toolkit.widgets.usage_stats.enabled', true);
    }
}
