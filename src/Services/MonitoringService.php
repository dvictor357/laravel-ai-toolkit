<?php

namespace AIToolkit\AIToolkit\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MonitoringService
{
    private const METRICS_PREFIX = 'ai_toolkit_metrics';

    private const METRICS_TTL = 86400; // 24 hours

    /**
     * Record an AI operation metric
     */
    public function recordMetric(
        string $provider,
        string $operation,
        array $metadata = []
    ): void {
        try {
            $timestamp = now();
            $dateKey = $timestamp->format('Y-m-d');
            $hourKey = $timestamp->format('H');

            $this->incrementCounter($provider, $operation, 'total', $dateKey, $hourKey);

            if (isset($metadata['success']) && $metadata['success'] === true) {
                $this->incrementCounter($provider, $operation, 'success', $dateKey, $hourKey);
            } elseif (isset($metadata['success']) && $metadata['success'] === false) {
                $this->incrementCounter($provider, $operation, 'failure', $dateKey, $hourKey);
            }

            // Record response time if provided
            if (isset($metadata['response_time_ms'])) {
                $this->recordResponseTime(
                    $provider,
                    $operation,
                    $metadata['response_time_ms'],
                    $dateKey,
                    $hourKey
                );
            }

            // Record token usage if provided
            if (isset($metadata['token_usage'])) {
                $this->recordTokenUsage($provider, $operation, $metadata['token_usage'], $dateKey, $hourKey);
            }

            // Record cache hit/miss
            if (isset($metadata['cache_hit'])) {
                $this->incrementCounter(
                    $provider,
                    $operation,
                    $metadata['cache_hit'] ? 'cache_hit' : 'cache_miss',
                    $dateKey,
                    $hourKey
                );
            }

        } catch (Exception $e) {
            Log::warning('Failed to record metric', [
                'error' => $e->getMessage(),
                'provider' => $provider,
                'operation' => $operation,
            ]);
        }
    }

    /**
     * Get metrics for a specific period
     */
    public function getMetrics(
        ?string $provider = null,
        ?string $operation = null,
        string $period = '24h'
    ): array {
        $endTime = now();
        $startTime = match ($period) {
            '1h' => $endTime->copy()->subHour(),
            '6h' => $endTime->copy()->subHours(6),
            '24h' => $endTime->copy()->subDay(),
            '7d' => $endTime->copy()->subDays(7),
            '30d' => $endTime->copy()->subDays(30),
            default => $endTime->copy()->subDay()
        };

        $metrics = [];

        try {
            $providers = $provider ? [$provider] : ['openai', 'anthropic', 'groq'];
            $operations = $operation ? [$operation] : ['chat', 'stream', 'embed'];

            foreach ($providers as $prov) {
                foreach ($operations as $op) {
                    $metrics[$prov][$op] = $this->getProviderOperationMetrics(
                        $prov,
                        $op,
                        $startTime,
                        $endTime
                    );
                }
            }

            // Add summary metrics
            $metrics['summary'] = $this->getSummaryMetrics($providers, $operations, $startTime, $endTime);

        } catch (Exception $e) {
            Log::error('Failed to retrieve metrics', [
                'error' => $e->getMessage(),
                'provider' => $provider,
                'operation' => $operation,
                'period' => $period,
            ]);
        }

        return $metrics;
    }

    /**
     * Get health status for all providers
     */
    public function getHealthStatus(): array
    {
        $health = [
            'timestamp' => now(),
            'providers' => [],
            'overall' => 'healthy',
        ];

        $providers = ['openai', 'anthropic', 'groq'];
        $unhealthyProviders = [];

        foreach ($providers as $provider) {
            $metrics = $this->getMetrics($provider, null, '1h');
            $providerHealth = $this->evaluateProviderHealth($provider, $metrics[$provider] ?? []);

            $health['providers'][$provider] = $providerHealth;

            if ($providerHealth['status'] !== 'healthy') {
                $unhealthyProviders[] = $provider;
            }
        }

        if (count($unhealthyProviders) === count($providers)) {
            $health['overall'] = 'critical';
        } elseif (count($unhealthyProviders) > 0) {
            $health['overall'] = 'degraded';
        }

        return $health;
    }

    /**
     * Get performance statistics
     */
    public function getPerformanceStats(?string $provider = null): array
    {
        $metrics = $this->getMetrics($provider, null, '24h');

        $stats = [
            'avg_response_time' => 0,
            'p95_response_time' => 0,
            'success_rate' => 0,
            'cache_hit_rate' => 0,
            'total_requests' => 0,
            'total_tokens' => 0,
        ];

        $totalResponseTimes = [];
        $totalRequests = 0;
        $totalSuccesses = 0;
        $totalCacheHits = 0;
        $totalTokenUsage = 0;

        foreach ($metrics as $prov => $provMetrics) {
            if ($prov === 'summary') {
                continue;
            }

            foreach ($provMetrics as $op => $opMetrics) {
                $totalRequests += $opMetrics['total'] ?? 0;
                $totalSuccesses += $opMetrics['success'] ?? 0;
                $totalCacheHits += $opMetrics['cache_hit'] ?? 0;

                if (isset($opMetrics['response_time'])) {
                    $avgTime = $opMetrics['response_time']['avg'] ?? 0;
                    if ($avgTime > 0) {
                        $totalResponseTimes[] = $avgTime;
                    }
                }

                if (isset($opMetrics['token_usage'])) {
                    $totalTokenUsage += $opMetrics['token_usage']['total'] ?? 0;
                }
            }
        }

        // Calculate statistics
        if (! empty($totalResponseTimes)) {
            sort($totalResponseTimes);
            $stats['avg_response_time'] = array_sum($totalResponseTimes) / count($totalResponseTimes);
            $stats['p95_response_time'] = $totalResponseTimes[(int) (count($totalResponseTimes) * 0.95)] ?? 0;
        }

        $stats['total_requests'] = $totalRequests;
        $stats['success_rate'] = $totalRequests > 0 ? ($totalSuccesses / $totalRequests) * 100 : 0;
        $stats['cache_hit_rate'] = $totalRequests > 0 ? ($totalCacheHits / $totalRequests) * 100 : 0;
        $stats['total_tokens'] = $totalTokenUsage;

        return $stats;
    }

    /**
     * Export metrics for external monitoring systems
     */
    public function exportMetrics(string $format = 'json'): string
    {
        $metrics = $this->getMetrics(null, null, '24h');

        return match ($format) {
            'json' => json_encode($metrics, JSON_PRETTY_PRINT),
            'prometheus' => $this->formatPrometheusMetrics($metrics),
            default => json_encode($metrics, JSON_PRETTY_PRINT)
        };
    }

    private function incrementCounter(
        string $provider,
        string $operation,
        string $metric,
        string $dateKey,
        string $hourKey
    ): void {
        $keys = [
            "{$this->getPrefix()}:{$provider}:{$operation}:{$metric}:{$dateKey}",
            "{$this->getPrefix()}:{$provider}:{$operation}:{$metric}:{$dateKey}:{$hourKey}",
        ];

        foreach ($keys as $key) {
            try {
                Cache::increment($key);
                Cache::put($key, Cache::get($key), self::METRICS_TTL);
            } catch (Exception $e) {
                // Log error but don't fail the operation
                Log::debug('Failed to increment metric counter', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function recordResponseTime(
        string $provider,
        string $operation,
        float $responseTime,
        string $dateKey,
        string $hourKey
    ): void {
        $keys = [
            "{$this->getPrefix()}:{$provider}:{$operation}:response_time:{$dateKey}",
            "{$this->getPrefix()}:{$provider}:{$operation}:response_time:{$dateKey}:{$hourKey}",
        ];

        foreach ($keys as $key) {
            try {
                $current = Cache::get($key, []);
                $current[] = $responseTime;

                // Keep only last 100 measurements per key
                if (count($current) > 100) {
                    $current = array_slice($current, -100);
                }

                Cache::put($key, $current, self::METRICS_TTL);
            } catch (Exception $e) {
                Log::debug('Failed to record response time', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function recordTokenUsage(
        string $provider,
        string $operation,
        array $tokenUsage,
        string $dateKey,
        string $hourKey
    ): void {
        $keys = [
            "{$this->getPrefix()}:{$provider}:{$operation}:token_usage:{$dateKey}",
            "{$this->getPrefix()}:{$provider}:{$operation}:token_usage:{$dateKey}:{$hourKey}",
        ];

        foreach ($keys as $key) {
            try {
                $current = Cache::get($key, []);
                $current[] = $tokenUsage;

                // Keep only last 100 measurements per key
                if (count($current) > 100) {
                    $current = array_slice($current, -100);
                }

                Cache::put($key, $current, self::METRICS_TTL);
            } catch (Exception $e) {
                Log::debug('Failed to record token usage', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function getProviderOperationMetrics(
        string $provider,
        string $operation,
        $startTime,
        $endTime
    ): array {
        $metrics = [
            'total' => 0,
            'success' => 0,
            'failure' => 0,
            'cache_hit' => 0,
            'cache_miss' => 0,
            'response_time' => ['avg' => 0, 'min' => 0, 'max' => 0],
            'token_usage' => ['total' => 0, 'avg' => 0],
        ];

        // Get date range for iteration
        $current = $startTime->copy();
        while ($current->lte($endTime)) {
            $dateKey = $current->format('Y-m-d');
            $hourKey = $current->format('H');

            // Get metrics for this hour
            $hourlyMetrics = $this->getHourlyMetrics($provider, $operation, $dateKey, $hourKey);

            // Aggregate metrics
            foreach (['total', 'success', 'failure', 'cache_hit', 'cache_miss'] as $metric) {
                $metrics[$metric] += $hourlyMetrics[$metric] ?? 0;
            }

            // Aggregate response times
            if (isset($hourlyMetrics['response_times']) && ! empty($hourlyMetrics['response_times'])) {
                $responseTimes = array_merge(
                    $metrics['response_time']['times'] ?? [],
                    $hourlyMetrics['response_times']
                );
                $metrics['response_time']['times'] = $responseTimes;
            }

            // Aggregate token usage
            if (isset($hourlyMetrics['token_usage_data'])) {
                $tokenUsage = array_merge(
                    $metrics['token_usage']['data'] ?? [],
                    $hourlyMetrics['token_usage_data']
                );
                $metrics['token_usage']['data'] = $tokenUsage;
            }

            $current->addHour();
        }

        // Calculate averages
        if (! empty($metrics['response_time']['times'])) {
            $times = $metrics['response_time']['times'];
            $metrics['response_time']['avg'] = array_sum($times) / count($times);
            $metrics['response_time']['min'] = min($times);
            $metrics['response_time']['max'] = max($times);
            unset($metrics['response_time']['times']);
        }

        if (! empty($metrics['token_usage']['data'])) {
            $tokens = array_column($metrics['token_usage']['data'], 'total_tokens');
            $metrics['token_usage']['total'] = array_sum($tokens);
            $metrics['token_usage']['avg'] = array_sum($tokens) / count($tokens);
            unset($metrics['token_usage']['data']);
        }

        return $metrics;
    }

    private function getHourlyMetrics(string $provider, string $operation, string $dateKey, string $hourKey): array
    {
        $metrics = [];

        foreach (['total', 'success', 'failure', 'cache_hit', 'cache_miss'] as $metric) {
            $key = "{$this->getPrefix()}:{$provider}:{$operation}:{$metric}:{$dateKey}:{$hourKey}";
            $metrics[$metric] = Cache::get($key, 0);
        }

        // Get response times
        $responseTimeKey = "{$this->getPrefix()}:{$provider}:{$operation}:response_time:{$dateKey}:{$hourKey}";
        $metrics['response_times'] = Cache::get($responseTimeKey, []);

        // Get token usage
        $tokenUsageKey = "{$this->getPrefix()}:{$provider}:{$operation}:token_usage:{$dateKey}:{$hourKey}";
        $metrics['token_usage_data'] = Cache::get($tokenUsageKey, []);

        return $metrics;
    }

    private function getSummaryMetrics(array $providers, array $operations, $startTime, $endTime): array
    {
        $summary = [
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'avg_response_time' => 0,
            'cache_hit_rate' => 0,
            'providers_status' => [],
        ];

        $responseTimes = [];
        $totalCacheHits = 0;
        $totalRequests = 0;

        foreach ($providers as $provider) {
            $providerTotal = 0;
            $providerSuccess = 0;
            $providerFailure = 0;
            $providerCacheHits = 0;

            foreach ($operations as $operation) {
                $opMetrics = $this->getProviderOperationMetrics($provider, $operation, $startTime, $endTime);

                $providerTotal += $opMetrics['total'];
                $providerSuccess += $opMetrics['success'];
                $providerFailure += $opMetrics['failure'];
                $providerCacheHits += $opMetrics['cache_hit'];

                if ($opMetrics['response_time']['avg'] > 0) {
                    $responseTimes[] = $opMetrics['response_time']['avg'];
                }
            }

            $summary['providers_status'][$provider] = [
                'status' => $this->getProviderStatus($providerTotal, $providerSuccess, $providerFailure),
                'requests' => $providerTotal,
                'success_rate' => $providerTotal > 0 ? ($providerSuccess / $providerTotal) * 100 : 0,
            ];

            $totalRequests += $providerTotal;
            $totalCacheHits += $providerCacheHits;
            $summary['total_requests'] += $providerTotal;
            $summary['successful_requests'] += $providerSuccess;
            $summary['failed_requests'] += $providerFailure;
        }

        if (! empty($responseTimes)) {
            $summary['avg_response_time'] = array_sum($responseTimes) / count($responseTimes);
        }

        $summary['cache_hit_rate'] = $totalRequests > 0 ? ($totalCacheHits / $totalRequests) * 100 : 0;

        return $summary;
    }

    private function evaluateProviderHealth(string $provider, array $metrics): array
    {
        $health = [
            'status' => 'healthy',
            'success_rate' => 100,
            'avg_response_time' => 0,
            'total_requests' => 0,
            'issues' => [],
        ];

        $totalRequests = 0;
        $totalSuccesses = 0;
        $responseTimes = [];

        foreach ($metrics as $operation => $opMetrics) {
            $totalRequests += $opMetrics['total'];
            $totalSuccesses += $opMetrics['success'];

            if ($opMetrics['response_time']['avg'] > 0) {
                $responseTimes[] = $opMetrics['response_time']['avg'];
            }
        }

        $health['total_requests'] = $totalRequests;

        if ($totalRequests > 0) {
            $health['success_rate'] = ($totalSuccesses / $totalRequests) * 100;
        }

        if (! empty($responseTimes)) {
            $health['avg_response_time'] = array_sum($responseTimes) / count($responseTimes);
        }

        // Evaluate health based on metrics
        if ($health['success_rate'] < 90) {
            $health['status'] = 'critical';
            $health['issues'][] = 'Low success rate: '.round($health['success_rate'], 1).'%';
        } elseif ($health['success_rate'] < 95) {
            $health['status'] = 'warning';
            $health['issues'][] = 'Degraded success rate: '.round($health['success_rate'], 1).'%';
        }

        if ($health['avg_response_time'] > 5000) { // 5 seconds
            $health['status'] = $health['status'] === 'healthy' ? 'warning' : $health['status'];
            $health['issues'][] = 'High response time: '.round($health['avg_response_time'], 0).'ms';
        }

        return $health;
    }

    private function getProviderStatus(int $total, int $success, int $failure): string
    {
        if ($total === 0) {
            return 'no_data';
        }
        if ($failure === 0) {
            return 'healthy';
        }

        $failureRate = ($failure / $total) * 100;

        return $failureRate < 5 ? 'degraded' : 'critical';
    }

    private function formatPrometheusMetrics(array $metrics): string
    {
        $output = "# HELP ai_toolkit_requests_total Total number of AI requests\n";
        $output .= "# TYPE ai_toolkit_requests_total counter\n";
        $output .= "# HELP ai_toolkit_request_duration_seconds AI request duration in seconds\n";
        $output .= "# TYPE ai_toolkit_request_duration_seconds histogram\n";
        $output .= "# HELP ai_toolkit_success_rate AI request success rate\n";
        $output .= "# TYPE ai_toolkit_success_rate gauge\n";

        foreach ($metrics as $provider => $provMetrics) {
            if ($provider === 'summary') {
                continue;
            }

            foreach ($provMetrics as $operation => $opMetrics) {
                $output .= "ai_toolkit_requests_total{provider=\"{$provider}\",operation=\"{$operation}\"} {$opMetrics['total']}\n";

                if ($opMetrics['response_time']['avg'] > 0) {
                    $output .= "ai_toolkit_request_duration_seconds{provider=\"{$provider}\",operation=\"{$operation}\",quantile=\"0.5\"} ".
                              ($opMetrics['response_time']['avg'] / 1000)."\n";
                }

                if ($opMetrics['total'] > 0) {
                    $successRate = ($opMetrics['success'] / $opMetrics['total']) * 100;
                    $output .= "ai_toolkit_success_rate{provider=\"{$provider}\",operation=\"{$operation}\"} {$successRate}\n";
                }
            }
        }

        return $output;
    }

    private function getPrefix(): string
    {
        return self::METRICS_PREFIX;
    }
}
