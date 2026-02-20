<?php

namespace MarketDataApp\Tests\Integration\UniversalParameters;

use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Enums\Format;

/**
 * Integration tests for the AddHeaders universal parameter.
 *
 * Tests that the add_headers parameter works correctly with the actual API
 * in CSV/HTML format to include or exclude header rows.
 */
class AddHeadersTest extends UniversalParametersTestCase
{
    public function testAddHeaders_true_returnsCsvWithHeaders(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV, add_headers: true)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $lines = explode("\n", trim($csv));
        $this->assertGreaterThanOrEqual(2, count($lines), 'CSV should have header row and data row');

        $headerRow = str_getcsv($lines[0], ',', '"', '\\');
        $this->assertContains('symbol', $headerRow, 'Header row should contain "symbol" column');
    }

    public function testAddHeaders_false_returnsCsvWithoutHeaders(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV, add_headers: false)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $lines = explode("\n", trim($csv));
        $this->assertGreaterThanOrEqual(1, count($lines), 'CSV should have at least one data row');

        $firstRow = str_getcsv($lines[0], ',', '"', '\\');
        // First row should be data, check for numeric values or symbol
        $hasNumericValues = false;
        $firstValue = $firstRow[0] ?? '';
        foreach ($firstRow as $value) {
            if (is_numeric($value)) {
                $hasNumericValues = true;
                break;
            }
        }
        $this->assertTrue(
            $firstValue === 'AAPL' || $hasNumericValues,
            'First row should be data, not headers'
        );
    }
}
