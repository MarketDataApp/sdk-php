#!/usr/bin/env php
<?php

/**
 * API Health Dashboard
 *
 * Display comprehensive API health information including service status,
 * rate limits, and historical uptime.
 *
 * Usage:
 *   php dashboard.php                  # Full dashboard
 *   php dashboard.php --rate-limits    # Focus on rate limits
 *   php dashboard.php --services       # Check all service endpoints
 *
 * @see plan.md for detailed documentation
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Exceptions\ApiException;

// Parse arguments
$showRateLimits = false;
$showServices = false;
$showHeaders = false;

foreach ($argv as $i => $arg) {
    if ($i === 0) continue;

    if ($arg === '--help' || $arg === '-h') {
        showHelp();
        exit(0);
    } elseif ($arg === '--rate-limits' || $arg === '-r') {
        $showRateLimits = true;
    } elseif ($arg === '--services' || $arg === '-s') {
        $showServices = true;
    } elseif ($arg === '--headers') {
        $showHeaders = true;
    }
}

// If no specific option, show everything
if (!$showRateLimits && !$showServices && !$showHeaders) {
    $showRateLimits = true;
    $showServices = true;
}

function showHelp(): void
{
    echo <<<HELP
API Health Dashboard - Monitor your Market Data API integration

Usage:
  php dashboard.php [options]

Options:
  --rate-limits, -r   Show rate limit status
  --services, -s      Check service endpoints
  --headers           Show request headers (debug)
  --help, -h          Show this help message

Examples:
  php dashboard.php                  Full dashboard
  php dashboard.php --rate-limits    Just rate limits
  php dashboard.php --services       Check all services

Exit Codes:
  0 = All healthy
  1 = Warning (rate limits high)
  2 = Error (services offline)

Environment:
  MARKETDATA_TOKEN    Your Market Data API token (required)

HELP;
}

/**
 * Format uptime percentage with color
 * Note: API returns uptime as decimal (0.0-1.0), so we multiply by 100 for display
 */
function formatUptime(float $uptime): string
{
    $uptimePct = $uptime * 100; // Convert decimal to percentage
    $color = match (true) {
        $uptimePct >= 99.9 => "\033[32m", // Green
        $uptimePct >= 99.0 => "\033[33m", // Yellow
        default => "\033[31m", // Red
    };
    $reset = "\033[0m";
    return $color . number_format($uptimePct, 2) . '%' . $reset;
}

/**
 * Format rate limit usage
 */
function formatUsage(int $used, int $limit): string
{
    $pct = $limit > 0 ? ($used / $limit) * 100 : 0;
    $color = match (true) {
        $pct >= 90 => "\033[31m", // Red
        $pct >= 75 => "\033[33m", // Yellow
        default => "\033[32m", // Green
    };
    $reset = "\033[0m";
    return $color . number_format($used) . ' / ' . number_format($limit) . ' (' . number_format($pct, 1) . '%)' . $reset;
}

$exitCode = 0;

try {
    $client = new Client();

    echo "\n";
    echo "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║              MARKET DATA API HEALTH DASHBOARD                    ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

    echo "Time: " . date('Y-m-d H:i:s T') . "\n\n";

    // API Status and Uptime
    echo "┌─────────────────────────────────────────────────────────────────┐\n";
    echo "│ API STATUS & UPTIME                                            │\n";
    echo "└─────────────────────────────────────────────────────────────────┘\n";

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

        // Overall status
        $statusIcon = $allOnline ? "\033[32m● ONLINE\033[0m" : "\033[31m● OFFLINE\033[0m";
        echo "  Overall Status: {$statusIcon}\n";

        // Uptime metrics
        echo "  30-Day Uptime: " . formatUptime($avgUptime30d) . "\n";
        echo "  90-Day Uptime: " . formatUptime($avgUptime90d) . "\n";

        // Individual services
        if (!empty($status->services)) {
            echo "\n  Service Status:\n";
            foreach ($status->services as $service) {
                $icon = $service->online ? "\033[32m●\033[0m" : "\033[31m●\033[0m";
                $uptimeStr = formatUptime($service->uptime_percentage_30d);
                printf("    %s %-35s (30d: %s)\n",
                    $icon,
                    $service->service,
                    $uptimeStr
                );
            }
        }

        if (!$allOnline) {
            $exitCode = 2;
        }
    } catch (ApiException $e) {
        echo "  \033[31m✗ Could not fetch API status: {$e->getMessage()}\033[0m\n";
        $exitCode = 2;
    }

    // Rate Limits
    if ($showRateLimits) {
        echo "\n┌─────────────────────────────────────────────────────────────────┐\n";
        echo "│ RATE LIMITS                                                     │\n";
        echo "└─────────────────────────────────────────────────────────────────┘\n";

        try {
            $user = $client->utilities->user();
            $rateLimits = $user->rate_limits;

            $used = $rateLimits->limit - $rateLimits->remaining;
            echo "  Credits Used: " . formatUsage($used, $rateLimits->limit) . "\n";
            echo "  Credits Remaining: " . number_format($rateLimits->remaining) . "\n";

            if ($rateLimits->reset) {
                $resetTime = $rateLimits->reset->format('H:i:s T');
                $secondsUntil = $rateLimits->reset->diffInSeconds(\Carbon\Carbon::now(), false);
                if ($secondsUntil < 0) {
                    echo "  Resets: {$resetTime} (in " . abs($secondsUntil) . " seconds)\n";
                }
            }

            // Warning if usage is high
            $usagePct = $rateLimits->limit > 0 ? ($used / $rateLimits->limit) * 100 : 0;
            if ($usagePct >= 90) {
                echo "\n  \033[31m⚠ WARNING: Rate limit usage above 90%!\033[0m\n";
                if ($exitCode < 1) $exitCode = 1;
            } elseif ($usagePct >= 75) {
                echo "\n  \033[33m⚠ NOTICE: Rate limit usage above 75%\033[0m\n";
            }
        } catch (ApiException $e) {
            echo "  \033[31m✗ Could not fetch rate limits: {$e->getMessage()}\033[0m\n";
        }
    }

    // Service Endpoint Checks
    if ($showServices) {
        echo "\n┌─────────────────────────────────────────────────────────────────┐\n";
        echo "│ ENDPOINT HEALTH CHECKS                                          │\n";
        echo "└─────────────────────────────────────────────────────────────────┘\n";

        $endpoints = [
            '/v1/stocks/quotes/' => 'Stock Quotes',
            '/v1/stocks/candles/' => 'Stock Candles',
            '/v1/options/chain/' => 'Option Chains',
            '/v1/markets/status/' => 'Market Status',
        ];

        foreach ($endpoints as $endpoint => $name) {
            try {
                $serviceStatus = $client->utilities->getServiceStatus($endpoint);
                $icon = match ($serviceStatus->value) {
                    'online' => "\033[32m●\033[0m",
                    'offline' => "\033[31m●\033[0m",
                    default => "\033[33m●\033[0m",
                };
                $statusStr = strtoupper($serviceStatus->value);
                printf("  %s %-30s %s\n", $icon, $name, $statusStr);

                if ($serviceStatus->value === 'offline') {
                    $exitCode = 2;
                }
            } catch (\Exception $e) {
                printf("  \033[33m●\033[0m %-30s UNKNOWN\n", $name);
            }
        }
    }

    // Request Headers (debug)
    if ($showHeaders) {
        echo "\n┌─────────────────────────────────────────────────────────────────┐\n";
        echo "│ REQUEST HEADERS (DEBUG)                                         │\n";
        echo "└─────────────────────────────────────────────────────────────────┘\n";

        try {
            $headers = $client->utilities->headers();

            foreach ($headers->headers as $name => $value) {
                // Truncate long values
                $displayValue = is_array($value) ? implode(', ', $value) : $value;
                if (strlen($displayValue) > 50) {
                    $displayValue = substr($displayValue, 0, 47) . '...';
                }
                printf("  %-25s %s\n", $name . ':', $displayValue);
            }
        } catch (ApiException $e) {
            echo "  \033[31m✗ Could not fetch headers: {$e->getMessage()}\033[0m\n";
        }
    }

    // Summary
    echo "\n" . str_repeat('─', 68) . "\n";
    echo "Dashboard Status: ";
    switch ($exitCode) {
        case 0:
            echo "\033[32m✓ All systems healthy\033[0m\n";
            break;
        case 1:
            echo "\033[33m⚠ Warning - check rate limits\033[0m\n";
            break;
        case 2:
            echo "\033[31m✗ Error - services may be degraded\033[0m\n";
            break;
    }

} catch (\Exception $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(2);
}

exit($exitCode);
