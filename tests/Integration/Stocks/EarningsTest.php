<?php

namespace MarketDataApp\Tests\Integration\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Earnings;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Exceptions\ApiException;

/**
 * Integration tests for the Stocks Earnings endpoint.
 */
class EarningsTest extends StocksTestCase
{
    /**
     * Test successful retrieval of earnings data.
     */
    public function testEarnings_success()
    {
        $response = $this->client->stocks->earnings(symbol: 'AAPL', from: '2024-01-01');

        $this->assertInstanceOf(Earnings::class, $response);
        $this->assertNotEmpty($response->earnings);

        $this->assertEquals('string', gettype($response->status));
        $this->assertEquals('string', gettype($response->earnings[0]->symbol));
        $this->assertEquals('integer', gettype($response->earnings[0]->fiscal_year));
        $this->assertEquals('integer', gettype($response->earnings[0]->fiscal_quarter));
        $this->assertInstanceOf(Carbon::class, $response->earnings[0]->date);
        $this->assertInstanceOf(Carbon::class, $response->earnings[0]->report_date);
        $this->assertEquals('string', gettype($response->earnings[0]->report_time));
        // Currency may be null for future/estimated earnings reports
        $this->assertTrue(in_array(gettype($response->earnings[0]->currency), ['string', 'NULL']));
        $this->assertEquals('double', gettype($response->earnings[0]->reported_eps));
        $this->assertEquals('double', gettype($response->earnings[0]->estimated_eps));
        $this->assertEquals('double', gettype($response->earnings[0]->surprise_eps));
        $this->assertEquals('double', gettype($response->earnings[0]->surprise_eps_pct));
        $this->assertInstanceOf(Carbon::class, $response->earnings[0]->updated);
    }

    /**
     * Test successful retrieval of earnings data in CSV format.
     */
    public function testEarnings_csv_success()
    {
        $response = $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2024-01-01',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Earnings::class, $response);
        $this->assertNotEmpty($response->getCsv());
    }

    /**
     * Test stocks earnings with human-readable format.
     * Verifies that the API returns human-readable JSON keys with spaces.
     */
    public function testEarnings_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2024-01-01',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Earnings::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->earnings);
        $this->assertEquals('string', gettype($response->earnings[0]->symbol));
        $this->assertEquals('integer', gettype($response->earnings[0]->fiscal_year));
        $this->assertEquals('integer', gettype($response->earnings[0]->fiscal_quarter));
        $this->assertInstanceOf(Carbon::class, $response->earnings[0]->date);
    }

    /**
     * Test earnings endpoint with CSV format and dateformat=timestamp.
     *
     * @throws GuzzleException|ApiException
     */
    public function testEarnings_csv_dateFormat_timestamp_returnsCsv(): void
    {
        $response = $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2023-01-01',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP)
        );

        $this->assertInstanceOf(Earnings::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);
    }
}
