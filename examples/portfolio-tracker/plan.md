# Portfolio Tracker

## Purpose

Track the real-time value of a stock portfolio, calculate daily P&L, and export data for further analysis.

## Target Audience

Individual investors and traders who want to monitor their portfolio positions with real-time data.

## SDK Features Demonstrated

### Primary Features
- **Bulk Quotes** (`$client->stocks->quotes()`) - Fetch quotes for multiple symbols efficiently
- **52-Week High/Low** - Track how positions compare to yearly ranges
- **CSV Export** - Generate spreadsheet-compatible output

### Secondary Features
- **Rate Limit Awareness** - Monitor API usage
- **Error Handling** - Graceful handling of invalid symbols

## Input

A JSON file containing portfolio holdings:
```json
{
  "holdings": [
    {"symbol": "AAPL", "shares": 100, "cost_basis": 150.00},
    {"symbol": "MSFT", "shares": 50, "cost_basis": 280.00}
  ]
}
```

## Output

1. **Console Output** - Real-time portfolio summary
2. **CSV Export** (optional) - Detailed position data

## Usage

```bash
# Basic usage with sample portfolio
php tracker.php

# With custom portfolio file
php tracker.php /path/to/my-portfolio.json

# Export to CSV
php tracker.php --csv
```

## Implementation Notes

- Uses `quotes()` for efficient multi-symbol requests
- Calculates unrealized P&L per position and total
- Shows 52-week positioning (how close to high/low)
- Handles market hours vs after-hours data gracefully
