<?php

namespace AIToolkit\AIToolkit\Http\Controllers;

use AIToolkit\AIToolkit\Services\MonitoringService;
use AIToolkit\AIToolkit\Services\SecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AiHealthController extends Controller
{
    private MonitoringService $monitoringService;

    private SecurityService $securityService;

    public function __construct()
    {
        $this->monitoringService = app('ai-monitoring');
        $this->securityService = app('ai-security');
    }

    /**
     * Health check endpoint
     */
    public function check(Request $request): JsonResponse
    {
        try {
            $checks = [
                'status' => 'healthy',
                'timestamp' => now(),
                'version' => '1.0.0',
                'checks' => [],
            ];

            // Check provider health
            $healthStatus = $this->monitoringService->getHealthStatus();
            $checks['checks']['providers'] = $healthStatus;

            // Check security configuration
            $securityConfig = $this->securityService->getSecurityConfig();
            $checks['checks']['security'] = $securityConfig;

            // Check performance metrics
            $performanceStats = $this->monitoringService->getPerformanceStats();
            $checks['checks']['performance'] = $performanceStats;

            // Check cache service
            $cacheService = app('ai-cache');
            $checks['checks']['cache'] = [
                'enabled' => $cacheService->isEnabled(),
                'ttl' => $cacheService->getTTL(),
                'stats' => $cacheService->getStats(),
            ];

            // Check retry service
            $retryService = app('ai-retry');
            $checks['checks']['retry'] = [
                'available' => $retryService !== null,
                'max_retries' => 3, // Default from RetryService
            ];

            // Determine overall status
            $overallStatus = $this->determineOverallStatus($checks['checks']);
            $checks['status'] = $overallStatus;

            $statusCode = match ($overallStatus) {
                'healthy' => 200,
                'degraded' => 200,
                'warning' => 200,
                'critical' => 503,
                default => 503
            };

            return response()->json($checks, $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Health check failed',
                'error' => $e->getMessage(),
                'timestamp' => now(),
            ], 500);
        }
    }

    /**
     * Detailed metrics endpoint
     */
    public function metrics(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', '24h');
            $provider = $request->get('provider');
            $format = $request->get('format', 'json');

            $metrics = $this->monitoringService->getMetrics($provider, null, $period);

            return response()->json([
                'metrics' => $metrics,
                'period' => $period,
                'timestamp' => now(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve metrics',
                'message' => $e->getMessage(),
                'timestamp' => now(),
            ], 500);
        }
    }

    /**
     * Performance statistics endpoint
     */
    public function performance(Request $request): JsonResponse
    {
        try {
            $provider = $request->get('provider');

            $stats = $this->monitoringService->getPerformanceStats($provider);

            return response()->json([
                'performance_stats' => $stats,
                'provider' => $provider,
                'timestamp' => now(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve performance stats',
                'message' => $e->getMessage(),
                'timestamp' => now(),
            ], 500);
        }
    }

    /**
     * Export metrics for external monitoring
     */
    public function export(Request $request): \Illuminate\Http\Response
    {
        try {
            $format = $request->get('format', 'json');
            $content = $this->monitoringService->exportMetrics($format);

            $headers = match ($format) {
                'prometheus' => ['Content-Type' => 'text/plain'],
                default => ['Content-Type' => 'application/json']
            };

            return response($content, 200, $headers);

        } catch (\Exception $e) {
            return response([
                'error' => 'Failed to export metrics',
                'message' => $e->getMessage(),
            ], 500, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * Simple liveness probe
     */
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'timestamp' => now()], 200);
    }

    /**
     * Simple readiness probe
     */
    public function ready(): JsonResponse
    {
        try {
            // Check if basic services are available
            $cache = app('ai-cache');
            $security = app('ai-security');
            $monitoring = app('ai-monitoring');

            if ($cache && $security && $monitoring) {
                return response()->json(['status' => 'ready', 'timestamp' => now()], 200);
            }

            return response()->json(['status' => 'not_ready', 'timestamp' => now()], 503);

        } catch (\Exception $e) {
            return response()->json(['status' => 'not_ready', 'error' => $e->getMessage()], 503);
        }
    }

    private function determineOverallStatus(array $checks): string
    {
        $statuses = [];

        // Check provider statuses
        if (isset($checks['providers']['providers'])) {
            foreach ($checks['providers']['providers'] as $provider => $health) {
                if ($health['status'] !== 'healthy') {
                    $statuses[] = $health['status'];
                }
            }
        }

        // Check performance metrics
        if (isset($checks['performance']['success_rate'])) {
            $successRate = $checks['performance']['success_rate'];
            if ($successRate < 90) {
                $statuses[] = 'critical';
            } elseif ($successRate < 95) {
                $statuses[] = 'warning';
            }
        }

        // Determine overall status
        if (in_array('critical', $statuses)) {
            return 'critical';
        } elseif (in_array('warning', $statuses) || in_array('degraded', $statuses)) {
            return 'degraded';
        } elseif (in_array('unhealthy', $statuses)) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }
}
