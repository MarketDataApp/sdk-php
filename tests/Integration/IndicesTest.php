<?php

namespace MarketDataApp\Tests\Integration;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Indices\Candle;
use MarketDataApp\Endpoints\Responses\Indices\Candles;
use MarketDataApp\Endpoints\Responses\Indices\Quote;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

class IndicesTest extends TestCase
{

    use MockResponses;

    private Client $client;

    protected function setUp(): void
    {
        error_reporting(E_ALL);
        $token = "your_api_token";
        $client = new Client($token);
        $this->client = $client;
    }

    public function testQuote_success()
    {
        $response = $this->client->indices->quote("VIX");
        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('string', gettype($response->status));
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('double', gettype($response->last));
        $this->assertTrue(in_array(gettype($response->change), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->change_percent), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->fifty_two_week_high), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->fifty_two_week_low), ['double', 'NULL']));
        $this->assertInstanceOf(Carbon::class, $response->updated);
    }


    /**
     * @throws GuzzleException
     */
    public function testCandles_noData()
    {
        $response = $this->client->indices->candles(
            symbol: "VIX",
            from: '2022-09-01',
            to: '2022-09-06',
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals('no_data', $response->status);
        $this->assertInstanceOf(Carbon::class, $response->next_time);
        $this->assertInstanceOf(Carbon::class, $response->prev_time);
    }
}
