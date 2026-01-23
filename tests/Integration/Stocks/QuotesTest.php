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
 * Integration tests for the Stocks Quotes endpoint (parallel/multiple symbols).
 */
class QuotesTest extends StocksTestCase
{
    /**
     * Test successful retrieval of multiple stock quotes.
     */
    public function testQuotes_success()
    {
        $response = $this->client->stocks->quotes(['AAPL']);

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
     * Test stocks quotes (parallel) with human-readable format.
     * Verifies that the API returns human-readable JSON keys for parallel requests.
     */
    public function testQuotes_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->stocks->quotes(
            ['AAPL'],
            false,
            new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertNotEmpty($response->quotes);
        $this->assertInstanceOf(Quote::class, $response->quotes[0]);
        $this->assertEquals('ok', $response->quotes[0]->status);
        $this->assertEquals('string', gettype($response->quotes[0]->symbol));
        $this->assertEquals('double', gettype($response->quotes[0]->ask));
    }

    /**
     * Test quotes endpoint (parallel) with CSV format and add_headers=true.
     * Verifies that the CSV response includes header row for parallel requests.
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
     * Test quotes endpoint (parallel) with CSV format and add_headers=false.
     * Verifies that the CSV response does NOT include header row for parallel requests.
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
     * Test quotes endpoint (parallel) with filename parameter.
     * Verifies that exception is thrown for parallel requests with filename.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuotes_csv_withFilename_throwsException(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_parallel_' . uniqid() . '.csv';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('filename parameter cannot be used with parallel requests');

        $this->client->stocks->quotes(
            symbols: ['AAPL'],
            parameters: new Parameters(format: Format::CSV, filename: $testFile)
        );
    }
}
