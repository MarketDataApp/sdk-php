# Output Formats

This example demonstrates using different output formats, particularly CSV for data export and spreadsheet integration.

## Running the Example

```bash
php examples/output_formats.php
```

## What It Covers

- JSON format (default) - typed PHP objects
- CSV format - raw CSV string output
- Custom column selection
- Header row control
- Date format options
- Saving directly to files

## Available Formats

| Format | Description |
|--------|-------------|
| `Format::JSON` | Returns typed PHP objects (default) |
| `Format::CSV` | Returns raw CSV string |

## JSON Format (Default)

The default format returns fully typed PHP objects:

```php
$quote = $client->stocks->quote('AAPL');

// Returns a Quote object with typed properties
echo get_class($quote);  // MarketDataApp\Endpoints\Responses\Stocks\Quote
echo $quote->symbol;     // AAPL
echo $quote->last;       // 185.92
echo $quote->updated->format('Y-m-d');  // Carbon date object
```

## CSV Format

Request CSV output using Parameters:

```php
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\Format;

$params = new Parameters(format: Format::CSV);
$quote = $client->stocks->quote('AAPL', parameters: $params);

echo $quote->getCsv();
```

Output:
```csv
symbol,ask,askSize,bid,bidSize,mid,last,change,changepct,volume,updated
AAPL,185.95,100,185.92,200,185.935,185.92,-0.31,-0.0017,42841809,1704830398
```

## Select Specific Columns

Choose only the columns you need:

```php
$params = new Parameters(
    format: Format::CSV,
    columns: ['symbol', 'last', 'bid', 'ask', 'volume']
);

$quote = $client->stocks->quote('AAPL', parameters: $params);
echo $quote->getCsv();
```

Output:
```csv
symbol,last,bid,ask,volume
AAPL,185.92,185.92,185.95,42841809
```

## Remove Header Row

Get data without the header row:

```php
$params = new Parameters(
    format: Format::CSV,
    add_headers: false
);

$quote = $client->stocks->quote('AAPL', parameters: $params);
echo $quote->getCsv();
```

Output:
```csv
AAPL,185.95,100,185.92,200,185.935,185.92,-0.31,-0.0017,42841809,1704830398
```

## Date Format Options

Control how timestamps are formatted in CSV output:

```php
use MarketDataApp\Enums\DateFormat;

// Unix timestamps (seconds since epoch)
$params = new Parameters(
    format: Format::CSV,
    date_format: DateFormat::UNIX
);

$candles = $client->stocks->candles('AAPL', '2024-01-02', '2024-01-05', 'D', parameters: $params);
echo $candles->getCsv();
```

Output:
```csv
t,o,h,l,c,v
1704171600,187.15,188.44,183.88,185.64,81964874
1704258000,184.22,185.88,183.43,184.25,58414460
```

### Spreadsheet Format (Excel-compatible)

```php
$params = new Parameters(
    format: Format::CSV,
    date_format: DateFormat::SPREADSHEET
);

$candles = $client->stocks->candles('AAPL', '2024-01-02', '2024-01-05', 'D', parameters: $params);
echo $candles->getCsv();
```

Output:
```csv
t,o,h,l,c,v
45293.0,187.15,188.44,183.88,185.64,81964874
45294.0,184.22,185.88,183.43,184.25,58414460
```

The spreadsheet format uses Excel serial date numbers, making it easy to import into Excel or Google Sheets.

## Save to File

Write CSV directly to a file:

```php
$params = new Parameters(
    format: Format::CSV,
    filename: '/path/to/output.csv'
);

$candles = $client->stocks->candles('AAPL', '2024-01-01', '2024-01-31', 'D', parameters: $params);

// File is written automatically
echo "Saved to: /path/to/output.csv\n";
```

## Multiple Quotes as CSV

```php
$params = new Parameters(format: Format::CSV);
$quotes = $client->stocks->quotes(['AAPL', 'MSFT', 'GOOGL'], parameters: $params);
echo $quotes->getCsv();
```

Output:
```csv
symbol,ask,askSize,bid,bidSize,mid,last,change,changepct,volume,updated
AAPL,185.95,100,185.92,200,185.935,185.92,-0.31,-0.0017,42841809,1704830398
MSFT,388.50,300,388.45,100,388.475,388.47,2.15,0.0056,28456123,1704830398
GOOGL,140.25,200,140.20,150,140.225,140.22,-0.85,-0.0060,19234567,1704830395
```

## Parameters Reference

| Parameter | Type | Description |
|-----------|------|-------------|
| `format` | Format | Output format (JSON or CSV) |
| `columns` | array | Select specific columns |
| `add_headers` | bool | Include header row (default: true) |
| `date_format` | DateFormat | UNIX or SPREADSHEET |
| `filename` | string | Save directly to file |

## Using getCsv()

When using CSV format, call `getCsv()` on the response to get the raw CSV string:

```php
$response = $client->stocks->quote('AAPL', parameters: $params);
$csvString = $response->getCsv();
```

## See Also

- [quick_start.md](quick_start.md) - Basic SDK usage
- [stock_candles.md](stock_candles.md) - Historical data to export
- [bulk_quotes.md](bulk_quotes.md) - Quote data to export
