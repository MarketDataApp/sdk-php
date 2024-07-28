<?php

namespace MarketDataApp\Tests\Integration;

use Carbon\Carbon;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\MutualFunds\Candle;
use MarketDataApp\Endpoints\Responses\MutualFunds\Candles;
use MarketDataApp\Enums\Format;
use PHPUnit\Framework\TestCase;

class MutualFundsTest extends TestCase
{

    private Client $client;

    protected function setUp(): void
    {
        $token = 'your_api_token';
        $client = new Client($token);
        $this->client = $client;
    }

    public function testCandles_success()
    {
        $response = $this->client->mutual_funds->candles(
            symbol: 'VFINX',
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
        $this->assertInstanceOf(Carbon::class, $response->candles[0]->timestamp);
    }

    public function testCandles_csv_success()
    {
        $response = $this->client->mutual_funds->candles(
            symbol: 'VFINX',
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV)
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }
}
