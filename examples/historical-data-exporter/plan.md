# Historical Data Exporter

## Purpose

Download years of historical price data for backtesting, quantitative analysis, or archival purposes.

## Target Audience

Quantitative analysts, algo traders, and researchers who need bulk historical data.

## SDK Features Demonstrated

### Primary Features
- **Candles Endpoint** (`$client->stocks->candles()`) - Historical OHLCV data
- **Automatic Request Splitting** - SDK handles large date ranges automatically
- **CSV Export** - Direct file export capability

### Secondary Features
- **Resolution Options** - Daily, weekly, monthly, intraday
- **Split Adjustment** - Historical data adjusted for splits
- **Extended Hours** - Include pre/post market data for intraday

## Input

Command-line arguments specifying:
- Symbol(s)
- Date range
- Resolution
- Output format

## Output

CSV files with OHLCV data, organized by symbol:
```
exports/
├── AAPL_2020-2024_daily.csv
├── MSFT_2020-2024_daily.csv
└── ...
```

## Usage

```bash
# Export daily data for one symbol
php exporter.php AAPL --from=2020-01-01 --to=2024-12-31

# Export with specific resolution
php exporter.php AAPL --from=2024-01-01 --resolution=5 # 5-minute bars

# Export multiple symbols
php exporter.php AAPL,MSFT,GOOGL --from=2023-01-01

# Include extended hours for intraday
php exporter.php AAPL --from=2024-01-01 --resolution=1H --extended
```

## Implementation Notes

- For multi-year intraday requests, SDK automatically splits into year-long chunks
- Exports are saved to the `exports/` subdirectory
- Progress indicator shows download status
- Handles partial failures gracefully (some dates may have no data)
