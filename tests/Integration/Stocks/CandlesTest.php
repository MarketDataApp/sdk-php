<?php

namespace MarketDataApp\Tests\Integration\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Candle;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Exceptions\ApiException;

/**
 * Integration tests for the Stocks Candles endpoint.
 */
class CandlesTest extends StocksTestCase
{
    /**
     * Test successful retrieval of stock candles.
     *
     * @throws GuzzleException|ApiException
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testCandles_success()
    {
        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D'
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertSame('ok', $response->status);
        $this->assertNotEmpty($response->candles);

        $this->assertInstanceOf(Candle::class, $response->candles[0]);
        $this->assertSame('AAPL', $response->candles[0]->symbol);
        $this->assertGreaterThanOrEqual('2022-09-01', $response->candles[0]->timestamp->toDateString());
        $this->assertLessThanOrEqual('2022-09-05', $response->candles[array_key_last($response->candles)]->timestamp->toDateString());
        $this->assertEquals('double', gettype($response->candles[0]->close));
        $this->assertEquals('double', gettype($response->candles[0]->high));
        $this->assertEquals('double', gettype($response->candles[0]->low));
        $this->assertEquals('double', gettype($response->candles[0]->open));
        $this->assertEquals('integer', gettype($response->candles[0]->volume));
        $this->assertInstanceOf(Carbon::class, $response->candles[0]->timestamp);
    }

    /**
     * Test successful retrieval of stock candles in CSV format.
     */
    public function testCandles_csv_success()
    {
        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test stocks candles with human-readable format.
     * Verifies that the API returns human-readable JSON keys with spaces.
     */
    public function testCandles_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2024-01-01',
            to: '2024-01-05',
            resolution: 'D',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->candles);
        $this->assertInstanceOf(Candle::class, $response->candles[0]);
        $this->assertEquals('double', gettype($response->candles[0]->open));
        $this->assertEquals('double', gettype($response->candles[0]->high));
        $this->assertEquals('double', gettype($response->candles[0]->low));
        $this->assertEquals('double', gettype($response->candles[0]->close));
        $this->assertEquals('integer', gettype($response->candles[0]->volume));
        $this->assertInstanceOf(Carbon::class, $response->candles[0]->timestamp);
    }

    /**
     * Test candles endpoint with CSV format and dateformat=unix.
     * Verifies that the CSV response contains Unix timestamps.
     *
     * @throws GuzzleException|ApiException
     */
    public function testCandles_csv_dateFormat_unix_returnsCsv(): void
    {
        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2023-01-01',
            to: '2023-01-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        // Parse CSV and verify date column contains numeric values (Unix timestamps)
        $lines = explode("\n", trim($csv));
        if (count($lines) > 1) {
            // Get header row to find date column index
            $headerRow = str_getcsv($lines[0], ',', '"', '\\');
            $dateColumnIndex = array_search('t', $headerRow);
            if ($dateColumnIndex === false) {
                $dateColumnIndex = array_search('Date', $headerRow);
            }

            if ($dateColumnIndex !== false && count($lines) > 1) {
                // Check first data row
                $dataRow = str_getcsv($lines[1], ',', '"', '\\');
                if (isset($dataRow[$dateColumnIndex])) {
                    $dateValue = $dataRow[$dateColumnIndex];
                    // Unix timestamps are numeric
                    $this->assertTrue(is_numeric($dateValue), "Date value should be numeric (Unix timestamp), got: $dateValue");
                    $this->assertGreaterThan(1000000000, (int)$dateValue, "Unix timestamp should be > 1000000000, got: $dateValue");
                }
            }
        }
    }

    /**
     * Test candles endpoint with CSV format and dateformat=timestamp.
     * Verifies that the CSV response contains ISO timestamp strings.
     *
     * @throws GuzzleException|ApiException
     */
    public function testCandles_csv_dateFormat_timestamp_returnsCsv(): void
    {
        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2023-01-01',
            to: '2023-01-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        // Parse CSV and verify date column contains ISO timestamp strings
        $lines = explode("\n", trim($csv));
        if (count($lines) > 1) {
            // Get header row to find date column index
            $headerRow = str_getcsv($lines[0], ',', '"', '\\');
            $dateColumnIndex = array_search('t', $headerRow);
            if ($dateColumnIndex === false) {
                $dateColumnIndex = array_search('Date', $headerRow);
            }

            if ($dateColumnIndex !== false && count($lines) > 1) {
                // Check first data row
                $dataRow = str_getcsv($lines[1], ',', '"', '\\');
                if (isset($dataRow[$dateColumnIndex])) {
                    $dateValue = $dataRow[$dateColumnIndex];
                    // ISO timestamps contain 'T' or '-' and are not purely numeric
                    $this->assertFalse(is_numeric($dateValue), "Date value should be ISO string, got: $dateValue");
                    // Check for ISO format pattern (contains T or -)
                    $this->assertTrue(
                        strpos($dateValue, 'T') !== false || strpos($dateValue, '-') !== false,
                        "Date value should be ISO format, got: $dateValue"
                    );
                }
            }
        }
    }

    /**
     * Test candles endpoint with CSV format and dateformat=spreadsheet.
     * Verifies that the CSV response contains spreadsheet date numbers.
     *
     * @throws GuzzleException|ApiException
     */
    public function testCandles_csv_dateFormat_spreadsheet_returnsCsv(): void
    {
        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2023-01-01',
            to: '2023-01-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::SPREADSHEET)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        // Parse CSV and verify date column contains numeric values (spreadsheet dates are smaller than Unix)
        $lines = explode("\n", trim($csv));
        if (count($lines) > 1) {
            // Get header row to find date column index
            $headerRow = str_getcsv($lines[0], ',', '"', '\\');
            $dateColumnIndex = array_search('t', $headerRow);
            if ($dateColumnIndex === false) {
                $dateColumnIndex = array_search('Date', $headerRow);
            }

            if ($dateColumnIndex !== false && count($lines) > 1) {
                // Check first data row
                $dataRow = str_getcsv($lines[1], ',', '"', '\\');
                if (isset($dataRow[$dateColumnIndex])) {
                    $dateValue = $dataRow[$dateColumnIndex];
                    // Spreadsheet dates are numeric but smaller than Unix timestamps
                    $this->assertTrue(is_numeric($dateValue), "Date value should be numeric (spreadsheet date), got: $dateValue");
                    // Spreadsheet dates are typically < 1000000 (days since 1900)
                    $numericValue = (float)$dateValue;
                    $this->assertLessThan(1000000, $numericValue, "Spreadsheet date should be < 1000000, got: $dateValue");
                }
            }
        }
    }
}
