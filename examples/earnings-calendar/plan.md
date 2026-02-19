# Earnings Calendar

## Purpose

Generate an earnings calendar for a watchlist of stocks, helping traders identify upcoming volatility events and plan positions accordingly.

## Target Audience

Options traders and fundamental investors who want to track earnings announcements for their watchlist.

## SDK Features Demonstrated

### Primary Features
- **Earnings Endpoint** (`$client->stocks->earnings()`) - Fetch earnings data with date ranges
- **Concurrent Requests** - Efficiently fetch earnings for multiple symbols
- **Human-Readable Output** - Clean formatted output

### Secondary Features
- **Date Range Filtering** - Focus on upcoming earnings
- **CSV Export** - Calendar import compatibility

## Input

A text file containing symbols (one per line):
```
AAPL
MSFT
GOOGL
AMZN
META
```

## Output

1. **Console Output** - Chronological earnings calendar
2. **CSV Export** (optional) - Compatible with Google Calendar import

## Usage

```bash
# Basic usage with sample watchlist
php calendar.php

# With custom watchlist
php calendar.php /path/to/my-watchlist.txt

# Look ahead 60 days instead of default 30
php calendar.php --days=60

# Export to CSV
php calendar.php --csv
```

## Implementation Notes

- Fetches upcoming earnings for each symbol
- Sorts by report date chronologically
- Shows report timing (before market, after market, during hours)
- Groups by week for easy scanning
- Handles symbols without upcoming earnings gracefully
