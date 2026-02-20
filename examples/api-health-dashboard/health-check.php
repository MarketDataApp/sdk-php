#!/usr/bin/env php
<?php

/**
 * API Health Check
 *
 * Cron-compatible health check script for monitoring systems.
 * Returns standardized exit codes and optional JSON output.
 *
 * Usage:
 *   php health-check.php                  # Text output
 *   php health-check.php --json           # JSON output
 *   php health-check.php --test-endpoints # Test all endpoints
 *   php health-check.php --quiet          # Only output on failure
 *
 * Exit Codes:
 *   0 = Healthy
 *   1 = Warning (rate limits high, but functional)
 *   2 = Critical (API offline or authentication failed)
 *
 * Cron Example (every 5 minutes):
 *   0,5,10,15,20,25,30,35,40,45,50,55 * * * * php health-check.php --quiet 2>&1
 *
 * @see plan.md for detailed documentation
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Exceptions\UnauthorizedException;

// Parse arguments
$jsonOutput = false;
$testEndpoints = false;
$quiet = false;

foreach ($argv as $i => $arg) {
    if ($i === 0) continue;

    if ($arg === '--help' || $arg === '-h') {
        showHelp();
        exit(0);
    } elseif ($arg === '--json' || $arg === '-j') {
        $jsonOutput = true;
    } elseif ($arg === '--test-endpoints' || $arg === '-t') {
        $testEndpoints = true;
    } elseif ($arg === '--quiet' || $arg === '-q') {
        $quiet = true;
    }
}

function showHelp(): void
{
    echo <<<HELP
API Health Check - Cron-compatible monitoring script

Usage:
  php health-check.php [options]

Options:
  --json, -j           Output results as JSON
  --test-endpoints, -t Test individual API endpoints
  --quiet, -q          Only output on warnings/errors
  --help, -h           Show this help message

Exit Codes:
  0 = Healthy (all systems operational)
  1 = Warning (rate limits high but functional)
  2 = Critical (API offline or auth failed)

Cron Example:
  */5 * * * * php health-check.php --quiet 2>&1 | logger -t market-data-api

Environment:
  MARKETDATA_TOKEN    Your Market Data API token (required)

HELP;
}

// Health check result structure
$result = [
    'timestamp' => date('c'),
    'status' => 'healthy',
    'exit_code' => 0,
    'checks' => [],
    'warnings' => [],
    'errors' => [],
];

try {
    $client = new Client();

    // Check 1: API Status
    try {
        $status = $client->utilities->api_status();

        // Calculate overall status from services
        $allOnline = true;
        $totalUptime30d = 0;
        $totalUptime90d = 0;
        $serviceCount = count($status->services);

        foreach ($status->services as $service) {
            if (!$service->online) {
                $allOnline = false;
            }
            $totalUptime30d += $service->uptime_percentage_30d;
            $totalUptime90d += $service->uptime_percentage_90d;
        }

        $avgUptime30d = $serviceCount > 0 ? $totalUptime30d / $serviceCount : 0;
        $avgUptime90d = $serviceCount > 0 ? $totalUptime90d / $serviceCount : 0;

        $result['checks']['api_status'] = [
            'online' => $allOnline,
            'uptime_30d' => $avgUptime30d,
            'uptime_90d' => $avgUptime90d,
        ];

        if (!$allOnline) {
            $result['errors'][] = 'API is offline';
            $result['status'] = 'critical';
            $result['exit_code'] = 2;
        }
    } catch (\Exception $e) {
        $result['checks']['api_status'] = ['error' => $e->getMessage()];
        $result['errors'][] = 'Could not check API status: ' . $e->getMessage();
        $result['status'] = 'critical';
        $result['exit_code'] = 2;
    }

    // Check 2: Rate Limits
    try {
        $user = $client->utilities->user();
        $rateLimits = $user->rate_limits;

        $used = $rateLimits->limit - $rateLimits->remaining;
        $usagePct = $rateLimits->limit > 0 ? ($used / $rateLimits->limit) * 100 : 0;

        $result['checks']['rate_limits'] = [
            'used' => $used,
            'limit' => $rateLimits->limit,
            'remaining' => $rateLimits->remaining,
            'usage_percent' => round($usagePct, 2),
        ];

        if ($usagePct >= 95) {
            $result['warnings'][] = 'Rate limit usage at ' . round($usagePct, 1) . '%';
            if ($result['exit_code'] < 1) {
                $result['status'] = 'warning';
                $result['exit_code'] = 1;
            }
        }
    } catch (UnauthorizedException $e) {
        $result['checks']['rate_limits'] = ['error' => 'Unauthorized'];
        $result['errors'][] = 'Authentication failed - check API token';
        $result['status'] = 'critical';
        $result['exit_code'] = 2;
    } catch (\Exception $e) {
        $result['checks']['rate_limits'] = ['error' => $e->getMessage()];
        // Non-critical - rate limit check failure doesn't mean API is down
    }

    // Check 3: Endpoint Tests (optional)
    if ($testEndpoints) {
        $endpoints = [
            'stocks_quote' => fn() => $client->stocks->quote('AAPL'),
            'market_status' => fn() => $client->markets->status(),
        ];

        $result['checks']['endpoints'] = [];

        foreach ($endpoints as $name => $test) {
            $start = microtime(true);
            try {
                $test();
                $latency = round((microtime(true) - $start) * 1000);
                $result['checks']['endpoints'][$name] = [
                    'status' => 'ok',
                    'latency_ms' => $latency,
                ];
            } catch (\Exception $e) {
                $result['checks']['endpoints'][$name] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
                $result['errors'][] = "Endpoint {$name} failed: " . $e->getMessage();
                $result['status'] = 'critical';
                $result['exit_code'] = 2;
            }
        }
    }

} catch (UnauthorizedException $e) {
    $result['errors'][] = 'Authentication failed - invalid or missing API token';
    $result['status'] = 'critical';
    $result['exit_code'] = 2;
} catch (\Exception $e) {
    $result['errors'][] = 'Unexpected error: ' . $e->getMessage();
    $result['status'] = 'critical';
    $result['exit_code'] = 2;
}

// Output results
if ($jsonOutput) {
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
} else {
    // Text output
    $shouldOutput = !$quiet || $result['exit_code'] > 0;

    if ($shouldOutput) {
        $statusIcon = match ($result['status']) {
            'healthy' => '✓',
            'warning' => '⚠',
            'critical' => '✗',
            default => '?',
        };

        echo "[{$result['timestamp']}] Health Check: {$statusIcon} " . strtoupper($result['status']) . "\n";

        // Rate limit info
        if (isset($result['checks']['rate_limits']['usage_percent'])) {
            $usage = $result['checks']['rate_limits']['usage_percent'];
            echo "  Rate Limits: {$usage}% used\n";
        }

        // API uptime (API returns decimal 0.0-1.0, convert to percentage)
        if (isset($result['checks']['api_status']['uptime_30d'])) {
            $uptime = $result['checks']['api_status']['uptime_30d'] * 100;
            echo "  30-Day Uptime: " . number_format($uptime, 2) . "%\n";
        }

        // Endpoint latencies
        if (isset($result['checks']['endpoints'])) {
            echo "  Endpoints:\n";
            foreach ($result['checks']['endpoints'] as $name => $check) {
                if ($check['status'] === 'ok') {
                    echo "    {$name}: {$check['latency_ms']}ms\n";
                } else {
                    echo "    {$name}: FAILED\n";
                }
            }
        }

        // Warnings
        foreach ($result['warnings'] as $warning) {
            echo "  ⚠ WARNING: {$warning}\n";
        }

        // Errors
        foreach ($result['errors'] as $error) {
            echo "  ✗ ERROR: {$error}\n";
        }
    }
}

exit($result['exit_code']);
