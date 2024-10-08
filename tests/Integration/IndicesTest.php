<?php

namespace MarketDataApp\Tests\Integration;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Indices\Candles;
use MarketDataApp\Endpoints\Responses\Indices\Quote;
use MarketDataApp\Enums\Format;
use PHPUnit\Framework\TestCase;

/**
 * Class IndicesTest
 *
 * Integration tests for indices-related functionality in the MarketDataApp.
 */
class IndicesTest extends TestCase
{

    /**
     * @var Client The client instance used for testing.
     */
    private Client $client;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        $token = "your_api_token";
        $client = new Client($token);
        $this->client = $client;
    }

    /**
     * Test successful quote retrieval.
     */
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
     * Test successful quote retrieval in CSV format.
     */
    public function testQuote_csv_success()
    {
        $response = $this->client->indices->quote(symbol: "VIX", parameters: new Parameters(format: Format::CSV));
        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test successful retrieval of multiple quotes.
     */
    public function testQuotes_success()
    {
        $response = $this->client->indices->quotes(['VIX']);

        $this->assertInstanceOf(Quote::class, $response->quotes[0]);
        $this->assertEquals('string', gettype($response->quotes[0]->status));
        $this->assertEquals('string', gettype($response->quotes[0]->symbol));
        $this->assertEquals('double', gettype($response->quotes[0]->last));
        $this->assertTrue(in_array(gettype($response->quotes[0]->change), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->quotes[0]->change_percent), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->quotes[0]->fifty_two_week_high), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->quotes[0]->fifty_two_week_low), ['double', 'NULL']));
        $this->assertInstanceOf(Carbon::class, $response->quotes[0]->updated);
    }

    /**
     * Test successful candles retrieval.
     *
     * @throws GuzzleException
     */
    public function testCandles_success()
    {
        $response = $this->client->indices->candles(
            symbol: "VIX",
            from: '2024-07-15',
            to: '2024-07-17',
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->candles);
        $this->assertEquals('double', gettype($response->candles[0]->open));
        $this->assertEquals('double', gettype($response->candles[0]->high));
        $this->assertEquals('double', gettype($response->candles[0]->low));
        $this->assertEquals('double', gettype($response->candles[0]->close));
        $this->assertInstanceOf(Carbon::class, $response->candles[0]->timestamp);
    }

    /**
     * Test candles retrieval with no data.
     *
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
