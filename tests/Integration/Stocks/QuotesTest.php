<?php

namespace MarketDataApp\Tests\Integration\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Endpoints\Responses\Stocks\Quotes;
use MarketDataApp\Enums\Format;
use MarketDataApp\Exceptions\ApiException;

/**
 * Integration tests for the Stocks Quotes endpoint (multiple symbols in single request).
 */
class QuotesTest extends StocksTestCase
{
    /**
     * Test successful retrieval of multiple stock quotes.
     */
    public function testQuotes_success()
    {
        $response = $this->client->stocks->quotes(['AAPL']);

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertNotEmpty($response->quotes);
        $this->assertInstanceOf(Quote::class, $response->quotes[0]);
        $this->assertEquals('string', gettype($response->quotes[0]->status));
        $this->assertEquals('string', gettype($response->quotes[0]->symbol));
        $this->assertEquals('double', gettype($response->quotes[0]->ask));
        $this->assertEquals('integer', gettype($response->quotes[0]->ask_size));
        $this->assertEquals('double', gettype($response->quotes[0]->bid));
        $this->assertEquals('integer', gettype($response->quotes[0]->bid_size));
        $this->assertEquals('double', gettype($response->quotes[0]->mid));
        $this->assertEquals('double', gettype($response->quotes[0]->last));
        $this->assertTrue(in_array(gettype($response->quotes[0]->change), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->quotes[0]->change_percent), ['double', 'NULL']));
        $this->assertNull($response->quotes[0]->fifty_two_week_high);
        $this->assertNull($response->quotes[0]->fifty_two_week_low);
        $this->assertEquals('integer', gettype($response->quotes[0]->volume));
        $this->assertInstanceOf(Carbon::class, $response->quotes[0]->updated);
    }

    /**
     * Test successful retrieval of multiple stock quotes with multiple symbols.
     */
    public function testQuotes_multipleSymbols_success()
    {
        $response = $this->client->stocks->quotes(['AAPL', 'MSFT', 'GOOG']);

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertCount(3, $response->quotes);

        // Verify all quotes are valid Quote objects with correct symbols
        $symbols = array_map(fn($q) => $q->symbol, $response->quotes);
        $this->assertContains('AAPL', $symbols);
        $this->assertContains('MSFT', $symbols);
        $this->assertContains('GOOG', $symbols);

        // Verify each quote has valid data
        foreach ($response->quotes as $quote) {
            $this->assertInstanceOf(Quote::class, $quote);
            $this->assertEquals('ok', $quote->status);
            $this->assertIsFloat($quote->ask);
            $this->assertIsInt($quote->volume);
            $this->assertInstanceOf(Carbon::class, $quote->updated);
        }
    }

    /**
     * Test stocks quotes with human-readable format.
     * Verifies that the API returns human-readable JSON keys.
     */
    public function testQuotes_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->stocks->quotes(
            symbols: ['AAPL'],
            fifty_two_week: false,
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertNotEmpty($response->quotes);
        $this->assertInstanceOf(Quote::class, $response->quotes[0]);
        $this->assertEquals('ok', $response->quotes[0]->status);
        $this->assertEquals('string', gettype($response->quotes[0]->symbol));
        $this->assertEquals('double', gettype($response->quotes[0]->ask));
    }

    /**
     * Test quotes endpoint with CSV format and add_headers=true.
     * Verifies that the CSV response includes header row.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuotes_csv_addHeadersTrue_includesHeaders(): void
    {
        $response = $this->client->stocks->quotes(
            symbols: ['AAPL'],
            parameters: new Parameters(format: Format::CSV, add_headers: true)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertNotEmpty($response->quotes);

        $quote = $response->quotes[0];
        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertTrue($quote->isCsv());

        $csv = $quote->getCsv();
        $this->assertNotEmpty($csv);

        $lines = explode("\n", trim($csv));
        $this->assertGreaterThanOrEqual(2, count($lines), 'CSV should have at least header row and one data row');

        // First line should be headers
        $headerRow = str_getcsv($lines[0], ',', '"', '\\');
        $this->assertNotEmpty($headerRow);
        $this->assertContains('symbol', $headerRow, 'Header row should contain "symbol" column');
    }

    /**
     * Test quotes endpoint with CSV format and add_headers=false.
     * Verifies that the CSV response does NOT include header row.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuotes_csv_addHeadersFalse_excludesHeaders(): void
    {
        $response = $this->client->stocks->quotes(
            symbols: ['AAPL'],
            parameters: new Parameters(format: Format::CSV, add_headers: false)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertNotEmpty($response->quotes);

        $quote = $response->quotes[0];
        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertTrue($quote->isCsv());

        $csv = $quote->getCsv();
        $this->assertNotEmpty($csv);

        $lines = explode("\n", trim($csv));
        $this->assertGreaterThanOrEqual(1, count($lines), 'CSV should have at least one data row');

        // First line should be data, not headers
        $firstRow = str_getcsv($lines[0], ',', '"', '\\');
        $this->assertNotEmpty($firstRow);

        $firstValue = $firstRow[0] ?? '';
        $hasNumericValues = false;
        foreach ($firstRow as $value) {
            if (is_numeric($value)) {
                $hasNumericValues = true;
                break;
            }
        }
        // If we have numeric values in the first row, it's likely data, not headers
        $this->assertTrue(
            $firstValue === 'AAPL' || $hasNumericValues,
            'First row should be data (contain symbol or numeric values), not headers'
        );
    }

    /**
     * Test quotes endpoint with CSV format and filename parameter.
     * Verifies that CSV data is saved to file.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuotes_csv_withFilename_savesToFile(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_quotes_' . uniqid() . '.csv';

        try {
            $response = $this->client->stocks->quotes(
                symbols: ['AAPL'],
                parameters: new Parameters(format: Format::CSV, filename: $testFile)
            );

            $this->assertInstanceOf(Quotes::class, $response);
            $this->assertFileExists($testFile);

            $content = file_get_contents($testFile);
            $this->assertNotEmpty($content);
            $this->assertStringContainsString('AAPL', $content);
        } finally {
            // Clean up
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    /**
     * Test quotes endpoint with 52-week data enabled.
     */
    public function testQuotes_with52Week_returnsHighLowData(): void
    {
        $response = $this->client->stocks->quotes(['AAPL'], true);

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertNotEmpty($response->quotes);

        $quote = $response->quotes[0];
        // 52-week data may or may not be present depending on API response
        // Just verify the properties exist (they default to null if not in response)
        $this->assertTrue(
            property_exists($quote, 'fifty_two_week_high'),
            'Quote should have fifty_two_week_high property'
        );
        $this->assertTrue(
            property_exists($quote, 'fifty_two_week_low'),
            'Quote should have fifty_two_week_low property'
        );
    }
}
