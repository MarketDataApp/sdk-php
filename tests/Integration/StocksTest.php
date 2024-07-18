<?php

namespace MarketDataApp\Tests\Integration;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Stocks\BulkCandles;
use MarketDataApp\Endpoints\Responses\Stocks\BulkQuote;
use MarketDataApp\Endpoints\Responses\Stocks\BulkQuotes;
use MarketDataApp\Endpoints\Responses\Stocks\Candle;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Endpoints\Responses\Stocks\Earnings;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Exceptions\ApiException;
use PHPUnit\Framework\TestCase;

class StocksTest extends TestCase
{

    private Client $client;

    protected function setUp(): void
    {
        error_reporting(E_ALL);
        $token = "your_api_token";
        $client = new Client($token);
        $this->client = $client;
    }

    /**
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

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertNotEmpty($response->candles);

        // Verify each item in the response is an object of the correct type and has the correct values.
        $this->assertInstanceOf(Candle::class, $response->candles[0]);
        $this->assertEquals('double', gettype($response->candles[0]->close));
        $this->assertEquals('double', gettype($response->candles[0]->high));
        $this->assertEquals('double', gettype($response->candles[0]->low));
        $this->assertEquals('double', gettype($response->candles[0]->open));
        $this->assertEquals('integer', gettype($response->candles[0]->volume));
        $this->assertInstanceOf(Carbon::class, $response->candles[0]->timestamp);
    }

    /**
     * @throws GuzzleException|ApiException
     */
    public function testBulkCandles_success()
    {
        $response = $this->client->stocks->bulkCandles(
            symbols: ["AAPL"],
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(BulkCandles::class, $response);
        $this->assertNotEmpty($response->candles);

        // Verify each item in the response is an object of the correct type and has the correct values.
        $this->assertInstanceOf(Candle::class, $response->candles[0]);
        $this->assertEquals('double', gettype($response->candles[0]->close));
        $this->assertEquals('double', gettype($response->candles[0]->high));
        $this->assertEquals('double', gettype($response->candles[0]->low));
        $this->assertEquals('double', gettype($response->candles[0]->open));
        $this->assertEquals('integer', gettype($response->candles[0]->volume));
        $this->assertInstanceOf(Carbon::class, $response->candles[0]->timestamp);
    }

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

    public function testEarnings_success()
    {
        $response = $this->client->stocks->earnings(symbol: 'AAPL', from: '2023-01-01');

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
}
