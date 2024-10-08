<?php

namespace MarketDataApp\Tests\Integration;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\BulkCandles;
use MarketDataApp\Endpoints\Responses\Stocks\BulkQuote;
use MarketDataApp\Endpoints\Responses\Stocks\BulkQuotes;
use MarketDataApp\Endpoints\Responses\Stocks\Candle;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Endpoints\Responses\Stocks\Earnings;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Enums\Format;
use MarketDataApp\Exceptions\ApiException;
use PHPUnit\Framework\TestCase;

/**
 * Class StocksTest
 *
 * Integration tests for stocks-related functionality in the MarketDataApp.
 * This class tests various API endpoints related to stocks, including
 * candles, quotes, bulk quotes, and earnings data.
 */
class StocksTest extends TestCase
{

    /**
     * @var Client The client instance used for testing.
     */
    private Client $client;

    /**
     * Set up the test environment.
     * Initializes a new Client instance with the API token.
     */
    protected function setUp(): void
    {
        error_reporting(E_ALL);
        $token = "your_api_token";
        $client = new Client($token);
        $this->client = $client;
    }

    /**
     * Test successful retrieval of stock candles.
     *
     * @throws GuzzleException|ApiException
     */
    public function testCandles_success()
    {
        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D'
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertNotEmpty($response->candles);

        $this->assertInstanceOf(Candle::class, $response->candles[0]);
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
     * Test successful retrieval of bulk stock candles.
     *
     * @throws GuzzleException|ApiException
     */
    public function testBulkCandles_success()
    {
        $response = $this->client->stocks->bulkCandles(
            symbols: ["AAPL"],
            resolution: 'D'
        );

        $this->assertInstanceOf(BulkCandles::class, $response);
        $this->assertNotEmpty($response->candles);

        $this->assertInstanceOf(Candle::class, $response->candles[0]);
        $this->assertEquals('double', gettype($response->candles[0]->close));
        $this->assertEquals('double', gettype($response->candles[0]->high));
        $this->assertEquals('double', gettype($response->candles[0]->low));
        $this->assertEquals('double', gettype($response->candles[0]->open));
        $this->assertEquals('integer', gettype($response->candles[0]->volume));
        $this->assertInstanceOf(Carbon::class, $response->candles[0]->timestamp);
    }

    /**
     * Test successful retrieval of bulk stock candles in CSV format.
     *
     * @throws GuzzleException|ApiException
     */
    public function testBulkCandles_csv_success()
    {
        $response = $this->client->stocks->bulkCandles(
            symbols: ["AAPL"],
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(BulkCandles::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test successful retrieval of a stock quote.
     */
    public function testQuote_success()
    {
        $response = $this->client->stocks->quote('AAPL');

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('string', gettype($response->status));
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('integer', gettype($response->ask_size));
        $this->assertEquals('double', gettype($response->bid));
        $this->assertEquals('integer', gettype($response->bid_size));
        $this->assertEquals('double', gettype($response->mid));
        $this->assertEquals('double', gettype($response->last));
        $this->assertTrue(in_array(gettype($response->change), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->change_percent), ['double', 'NULL']));
        $this->assertNull($response->fifty_two_week_high);
        $this->assertNull($response->fifty_two_week_low);
        $this->assertEquals('integer', gettype($response->volume));
        $this->assertInstanceOf(Carbon::class, $response->updated);
    }

    /**
     * Test successful retrieval of a stock quote in CSV format.
     */
    public function testQuote_csv_success()
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

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
     * Test successful retrieval of bulk stock quotes.
     *
     * @throws \Throwable
     */
    public function testBulkQuotes_success()
    {
        $response = $this->client->stocks->bulkQuotes(['AAPL']);
        $this->assertInstanceOf(BulkQuotes::class, $response);
        $this->assertNotEmpty($response->quotes);

        $this->assertInstanceOf(BulkQuote::class, $response->quotes[0]);

        $this->assertEquals('string', gettype($response->quotes[0]->symbol));
        $this->assertEquals('double', gettype($response->quotes[0]->ask));
        $this->assertEquals('integer', gettype($response->quotes[0]->ask_size));
        $this->assertEquals('double', gettype($response->quotes[0]->bid));
        $this->assertEquals('integer', gettype($response->quotes[0]->bid_size));
        $this->assertEquals('double', gettype($response->quotes[0]->mid));
        $this->assertEquals('double', gettype($response->quotes[0]->last));
        $this->assertTrue(in_array(gettype($response->quotes[0]->change), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->quotes[0]->change_percent), ['double', 'NULL']));
        $this->assertEquals('integer', gettype($response->quotes[0]->volume));
        $this->assertInstanceOf(Carbon::class, $response->quotes[0]->updated);
    }

    /**
     * Test successful retrieval of bulk stock quotes in CSV format.
     *
     * @throws \Throwable
     */
    public function testBulkQuotes_csv_success()
    {
        $response = $this->client->stocks->bulkQuotes(
            symbols: ['AAPL'],
            parameters: new Parameters(format: Format::CSV)
        );
        $this->assertInstanceOf(BulkQuotes::class, $response);
        $this->assertNotEmpty($response->getCsv());
    }

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
        $this->assertEquals('string', gettype($response->earnings[0]->currency));
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
}
