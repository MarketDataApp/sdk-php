<?php

/**
 * Output Formats
 *
 * @see output_formats.md for detailed documentation
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\DateFormat;
use Psr\Log\NullLogger;

$client = new Client(logger: new NullLogger());

// JSON format (default) - returns typed objects
$jsonQuote = $client->stocks->quote('AAPL');
echo "JSON Format:\n";
echo "  Type: " . get_class($jsonQuote) . "\n";
echo "  Symbol: {$jsonQuote->symbol}, Price: \${$jsonQuote->last}\n\n";

// CSV format
$csvParams = new Parameters(format: Format::CSV);
$csvQuote = $client->stocks->quote('AAPL', parameters: $csvParams);
echo "CSV Format:\n{$csvQuote->getCsv()}\n";

// CSV with custom columns
$customParams = new Parameters(format: Format::CSV, columns: ['symbol', 'last', 'bid', 'ask', 'volume']);
$customCsv = $client->stocks->quote('AAPL', parameters: $customParams);
echo "CSV (Custom Columns):\n{$customCsv->getCsv()}\n";

// CSV without headers
$noHeaderParams = new Parameters(format: Format::CSV, add_headers: false);
$noHeaderCsv = $client->stocks->quote('AAPL', parameters: $noHeaderParams);
echo "CSV (No Headers):\n{$noHeaderCsv->getCsv()}\n";

// CSV with Unix timestamps
$unixParams = new Parameters(format: Format::CSV, date_format: DateFormat::UNIX);
$unixCsv = $client->stocks->candles('AAPL', '2024-01-02', '2024-01-05', 'D', parameters: $unixParams);
echo "CSV (Unix Timestamps):\n{$unixCsv->getCsv()}\n";

// CSV with spreadsheet date format (Excel-compatible)
$spreadsheetParams = new Parameters(format: Format::CSV, date_format: DateFormat::SPREADSHEET);
$spreadsheetCsv = $client->stocks->candles('AAPL', '2024-01-02', '2024-01-05', 'D', parameters: $spreadsheetParams);
echo "CSV (Spreadsheet Dates):\n{$spreadsheetCsv->getCsv()}\n";

// Save CSV to file
$tempFile = sys_get_temp_dir() . '/aapl_candles.csv';
$fileParams = new Parameters(format: Format::CSV, filename: $tempFile);
$client->stocks->candles('AAPL', '2024-01-02', '2024-01-10', 'D', parameters: $fileParams);
echo "Saved to: {$tempFile}\n" . file_get_contents($tempFile) . "\n";
unlink($tempFile);

// Multiple quotes as CSV
$multiParams = new Parameters(format: Format::CSV);
$multiCsv = $client->stocks->quotes(['AAPL', 'MSFT', 'GOOGL'], parameters: $multiParams);
echo "Multiple Quotes CSV:\n{$multiCsv->getCsv()}";
