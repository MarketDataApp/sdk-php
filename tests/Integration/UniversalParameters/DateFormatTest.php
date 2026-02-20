<?php

namespace MarketDataApp\Tests\Integration\UniversalParameters;

use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;

/**
 * Integration tests for the DateFormat universal parameter.
 *
 * Tests that the date_format parameter (UNIX, TIMESTAMP, SPREADSHEET) works correctly
 * with the actual API in CSV/HTML format.
 */
class DateFormatTest extends UniversalParametersTestCase
{
    public function testDateFormat_unix_returnsCsvWithUnixTimestamps(): void
    {
        $response = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2024-01-02',
            to: '2024-01-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $lines = explode("\n", trim($csv));
        if (count($lines) > 1) {
            $headerRow = str_getcsv($lines[0], ',', '"', '\\');
            $dateColumnIndex = array_search('t', $headerRow);
            if ($dateColumnIndex === false) {
                $dateColumnIndex = array_search('Date', $headerRow);
            }

            if ($dateColumnIndex !== false && count($lines) > 1) {
                $dataRow = str_getcsv($lines[1], ',', '"', '\\');
                if (isset($dataRow[$dateColumnIndex])) {
                    $dateValue = $dataRow[$dateColumnIndex];
                    $this->assertTrue(is_numeric($dateValue), "Date should be Unix timestamp");
                    $this->assertGreaterThan(1000000000, (int)$dateValue, "Unix timestamp should be > 1000000000");
                }
            }
        }
    }

    public function testDateFormat_timestamp_returnsCsvWithIsoTimestamps(): void
    {
        $response = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2024-01-02',
            to: '2024-01-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $lines = explode("\n", trim($csv));
        if (count($lines) > 1) {
            $headerRow = str_getcsv($lines[0], ',', '"', '\\');
            $dateColumnIndex = array_search('t', $headerRow);
            if ($dateColumnIndex === false) {
                $dateColumnIndex = array_search('Date', $headerRow);
            }

            if ($dateColumnIndex !== false && count($lines) > 1) {
                $dataRow = str_getcsv($lines[1], ',', '"', '\\');
                if (isset($dataRow[$dateColumnIndex])) {
                    $dateValue = $dataRow[$dateColumnIndex];
                    $this->assertFalse(is_numeric($dateValue), "Date should be ISO string");
                    $this->assertTrue(
                        strpos($dateValue, 'T') !== false || strpos($dateValue, '-') !== false,
                        "Date should be ISO format"
                    );
                }
            }
        }
    }

    public function testDateFormat_spreadsheet_returnsCsvWithSpreadsheetDates(): void
    {
        $response = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2024-01-02',
            to: '2024-01-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::SPREADSHEET)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $lines = explode("\n", trim($csv));
        if (count($lines) > 1) {
            $headerRow = str_getcsv($lines[0], ',', '"', '\\');
            $dateColumnIndex = array_search('t', $headerRow);
            if ($dateColumnIndex === false) {
                $dateColumnIndex = array_search('Date', $headerRow);
            }

            if ($dateColumnIndex !== false && count($lines) > 1) {
                $dataRow = str_getcsv($lines[1], ',', '"', '\\');
                if (isset($dataRow[$dateColumnIndex])) {
                    $dateValue = $dataRow[$dateColumnIndex];
                    $this->assertTrue(is_numeric($dateValue), "Date should be spreadsheet number");
                    $numericValue = (float)$dateValue;
                    $this->assertLessThan(1000000, $numericValue, "Spreadsheet date should be < 1000000");
                }
            }
        }
    }
}
