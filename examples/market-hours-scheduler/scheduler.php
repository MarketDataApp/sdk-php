#!/usr/bin/env php
<?php

/**
 * Market Hours Scheduler
 *
 * Execute tasks based on market sessions (pre-market, open, after-hours, closed).
 *
 * Usage:
 *   php scheduler.php                    # Run appropriate jobs for current session
 *   php scheduler.php --status-only      # Just show market status
 *   php scheduler.php --calendar         # Show upcoming market calendar
 *   php scheduler.php --force-job=NAME   # Force run a specific job
 *
 * Cron Example (run every 5 minutes during extended market hours 4am-8pm ET):
 *   0,5,10,15,20,25,30,35,40,45,50,55 4-20 * * 1-5 php scheduler.php
 *
 * @see plan.md for detailed documentation
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Exceptions\ApiException;

// Parse arguments
$statusOnly = false;
$showCalendar = false;
$calendarDays = 7;
$forceJob = null;

foreach ($argv as $i => $arg) {
    if ($i === 0) continue;

    if ($arg === '--help' || $arg === '-h') {
        showHelp();
        exit(0);
    } elseif ($arg === '--status-only' || $arg === '-s') {
        $statusOnly = true;
    } elseif ($arg === '--calendar' || $arg === '-c') {
        $showCalendar = true;
    } elseif (str_starts_with($arg, '--days=')) {
        $calendarDays = (int) substr($arg, 7);
    } elseif (str_starts_with($arg, '--force-job=')) {
        $forceJob = substr($arg, 12);
    }
}

function showHelp(): void
{
    echo <<<HELP
Market Hours Scheduler - Execute tasks based on market sessions

Usage:
  php scheduler.php [options]

Options:
  --status-only, -s   Just show market status, don't run jobs
  --calendar, -c      Show upcoming market calendar
  --days=N            Days to show in calendar (default: 7)
  --force-job=NAME    Force run a specific job (premarket-scan, market-close)
  --help, -h          Show this help message

Sessions:
  - Pre-Market: 4:00 AM - 9:30 AM ET
  - Market Open: 9:30 AM - 4:00 PM ET
  - After-Hours: 4:00 PM - 8:00 PM ET
  - Closed: 8:00 PM - 4:00 AM ET, weekends, holidays

Jobs:
  - premarket-scan: Runs during pre-market session
  - market-close: Runs after market close

Cron Example:
  */5 4-20 * * 1-5 php /path/to/scheduler.php >> /var/log/scheduler.log 2>&1

Environment:
  MARKETDATA_TOKEN    Your Market Data API token (required)

HELP;
}

/**
 * Determine current market session based on time
 * Returns: 'premarket', 'open', 'afterhours', 'closed'
 */
function getCurrentSession(): string
{
    // Get current time in Eastern Time
    $et = new DateTimeZone('America/New_York');
    $now = new DateTime('now', $et);
    $hour = (int) $now->format('G');
    $minute = (int) $now->format('i');
    $dayOfWeek = (int) $now->format('N'); // 1=Monday, 7=Sunday

    // Weekend = closed
    if ($dayOfWeek >= 6) {
        return 'closed';
    }

    $time = $hour * 100 + $minute;

    if ($time >= 400 && $time < 930) {
        return 'premarket';
    } elseif ($time >= 930 && $time < 1600) {
        return 'open';
    } elseif ($time >= 1600 && $time < 2000) {
        return 'afterhours';
    } else {
        return 'closed';
    }
}

/**
 * Format session name for display
 */
function formatSession(string $session): string
{
    return match ($session) {
        'premarket' => 'Pre-Market (4:00 AM - 9:30 AM ET)',
        'open' => 'Market Open (9:30 AM - 4:00 PM ET)',
        'afterhours' => 'After-Hours (4:00 PM - 8:00 PM ET)',
        'closed' => 'Closed',
        default => $session,
    };
}

/**
 * Run a job script
 */
function runJob(string $jobName): bool
{
    $jobFile = __DIR__ . "/jobs/{$jobName}.php";

    if (!file_exists($jobFile)) {
        fprintf(STDERR, "Job not found: %s\n", $jobName);
        return false;
    }

    echo "Running job: {$jobName}\n";
    echo str_repeat('-', 40) . "\n";

    // Include the job script
    try {
        include $jobFile;
        return true;
    } catch (\Exception $e) {
        fprintf(STDERR, "Job error: %s\n", $e->getMessage());
        return false;
    }
}

try {
    $client = new Client();

    // Current time info
    $et = new DateTimeZone('America/New_York');
    $now = new DateTime('now', $et);
    $currentSession = getCurrentSession();

    echo "=== Market Hours Scheduler ===\n\n";
    echo "Current Time (ET): " . $now->format('Y-m-d H:i:s') . "\n";
    echo "Session: " . formatSession($currentSession) . "\n\n";

    // Get market status from API
    $status = $client->markets->status(date: $now->format('Y-m-d'));

    if ($status->status === 'ok' && !empty($status->statuses)) {
        $todayStatus = $status->statuses[0];
        echo "Market Status (API): " . ucfirst($todayStatus->status) . "\n";

        if ($todayStatus->status === 'closed') {
            echo "Reason: Market is closed today\n";
        }
    }

    // Show calendar if requested
    if ($showCalendar) {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "MARKET CALENDAR (Next {$calendarDays} Days)\n";
        echo str_repeat('-', 50) . "\n";

        $calendar = $client->markets->status(
            from: $now->format('Y-m-d'),
            to: $now->modify("+{$calendarDays} days")->format('Y-m-d')
        );

        if ($calendar->status === 'ok') {
            printf("%-12s %-10s %s\n", 'Date', 'Day', 'Status');
            echo str_repeat('-', 50) . "\n";

            foreach ($calendar->statuses as $day) {
                $date = $day->date;
                $dayName = $date->format('D');
                $statusIcon = $day->status === 'open' ? '  Open' : '  CLOSED';

                printf("%-12s %-10s %s\n",
                    $date->format('Y-m-d'),
                    $dayName,
                    $statusIcon
                );
            }
        }
        exit(0);
    }

    // Status only mode
    if ($statusOnly) {
        exit(0);
    }

    // Force specific job
    if ($forceJob) {
        echo "\nForcing job execution: {$forceJob}\n\n";
        $success = runJob($forceJob);
        exit($success ? 0 : 1);
    }

    // Determine which jobs to run based on session
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "JOB EXECUTION\n";
    echo str_repeat('-', 50) . "\n";

    // Check if market is closed (holiday or weekend)
    $marketClosed = false;
    if ($status->status === 'ok' && !empty($status->statuses)) {
        $marketClosed = $status->statuses[0]->status === 'closed';
    }

    if ($marketClosed && $currentSession !== 'closed') {
        echo "Market is closed (holiday). Skipping scheduled jobs.\n";
        exit(0);
    }

    // Run session-appropriate jobs
    $jobsRun = 0;

    switch ($currentSession) {
        case 'premarket':
            echo "Pre-market session - running pre-market jobs\n\n";
            if (runJob('premarket-scan')) $jobsRun++;
            break;

        case 'afterhours':
            echo "After-hours session - running close jobs\n\n";
            if (runJob('market-close')) $jobsRun++;
            break;

        case 'open':
            echo "Market is open - no scheduled jobs for this session\n";
            echo "Tip: Add jobs/market-open.php for open-hours tasks\n";
            break;

        case 'closed':
            echo "Market is closed - no jobs to run\n";
            break;
    }

    echo "\n" . str_repeat('=', 50) . "\n";
    echo "Jobs executed: {$jobsRun}\n";

} catch (ApiException $e) {
    fprintf(STDERR, "API Error: %s\n", $e->getMessage());
    exit(1);
} catch (\Exception $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}

echo "\nDone.\n";
