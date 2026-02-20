# API Health Dashboard

## Purpose

Monitor API health, track rate limits, and diagnose integration issues.

## Target Audience

DevOps engineers and developers who need to monitor their Market Data API integration.

## SDK Features Demonstrated

### Primary Features
- **API Status** (`$client->utilities->api_status()`) - Service health and uptime
- **Rate Limit Tracking** (`$client->utilities->user()`) - Usage monitoring
- **Headers** (`$client->utilities->headers()`) - Debug request headers

### Secondary Features
- **Service Status** (`$client->utilities->getServiceStatus()`) - Per-endpoint status
- **Exception Handling** - Robust error handling patterns
- **Logging** - PSR-3 logging integration

## Components

### Dashboard
- `dashboard.php` - Interactive status display

### Health Check
- `health-check.php` - Cron-compatible health check script

## Usage

```bash
# Show API status dashboard
php dashboard.php

# Run health check (for monitoring/cron)
php health-check.php

# Health check with JSON output
php health-check.php --json

# Test specific endpoints
php health-check.php --test-endpoints
```

## Implementation Notes

- Designed to be run periodically (cron) or on-demand
- Returns exit codes for use in monitoring systems
- JSON output option for integration with alerting systems
- Includes rate limit warnings when usage is high
