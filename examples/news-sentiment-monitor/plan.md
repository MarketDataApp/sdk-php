# News Sentiment Monitor

## Purpose

Monitor news flow for a watchlist of stocks and generate daily digests.

## Target Audience

Research analysts and traders who want to stay informed about news affecting their positions.

## SDK Features Demonstrated

### Primary Features
- **News Endpoint** (`$client->stocks->news()`) - Fetch news articles (beta)
- **Human-Readable Format** - Clean formatted output
- **HTML Format** - Rich text output for reports

### Secondary Features
- **Date Range Filtering** - Focus on recent news
- **File Export** - Save digests for archival

## Input

A text file containing symbols (one per line):
```
AAPL
MSFT
GOOGL
```

## Output

1. **Console Output** - News summary by symbol
2. **HTML Export** (optional) - Formatted daily digest

## Usage

```bash
# Basic usage with sample watchlist
php monitor.php

# With custom watchlist
php monitor.php /path/to/watchlist.txt

# Get news for specific date range
php monitor.php --from=2024-01-01 --to=2024-01-15

# Export as HTML
php monitor.php --html
```

## Implementation Notes

- News endpoint is in beta; handle gracefully if unavailable
- Groups news by symbol for easy scanning
- Shows publication date and headline
- Option to save digests to output/ directory
