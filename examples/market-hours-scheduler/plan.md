# Market Hours Scheduler

## Purpose

Schedule and execute tasks based on market sessions (pre-market, regular hours, after-hours, closed).

## Target Audience

Algorithmic traders and developers building automated trading systems that need to execute code at specific market times.

## SDK Features Demonstrated

### Primary Features
- **Market Status** (`$client->markets->status()`) - Current market state
- **Holiday Detection** - Check for market holidays
- **Trading Day Calculations** - Determine if today is a trading day

### Secondary Features
- **Date Range Status** - Get market calendar for planning
- **Conditional Execution** - Run tasks only during specific sessions

## Components

### Main Scheduler
- `scheduler.php` - Determines current session and runs appropriate jobs

### Sample Jobs
- `jobs/premarket-scan.php` - Run during pre-market (4:00 AM - 9:30 AM ET)
- `jobs/market-close.php` - Run at market close (4:00 PM ET)

## Usage

```bash
# Check market status and run appropriate jobs
php scheduler.php

# Check status only (no job execution)
php scheduler.php --status-only

# Force run a specific job (testing)
php scheduler.php --force-job=premarket-scan

# Show upcoming market calendar
php scheduler.php --calendar --days=7
```

## Implementation Notes

- Uses Market Data API for authoritative market status
- Respects holidays (Memorial Day, July 4th, etc.)
- Handles early close days
- Designed to be run from cron for continuous scheduling
