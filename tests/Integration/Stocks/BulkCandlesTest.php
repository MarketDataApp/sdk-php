<?php

namespace MarketDataApp\Tests\Integration\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\BulkCandles;
use MarketDataApp\Endpoints\Responses\Stocks\Candle;
use MarketDataApp\Enums\Format;
use MarketDataApp\Exceptions\ApiException;

/**
 * Integration tests for the Stocks Bulk Candles endpoint.
 */
class BulkCandlesTest extends StocksTestCase
{
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
     * Test stocks bulkCandles with human-readable format.
     * Verifies that the API returns human-readable JSON keys with spaces.
     */
    public function testBulkCandles_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->stocks->bulkCandles(
            symbols: ["AAPL"],
            resolution: 'D',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(BulkCandles::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->candles);
        $this->assertInstanceOf(Candle::class, $response->candles[0]);
        $this->assertEquals('double', gettype($response->candles[0]->open));
        $this->assertEquals('double', gettype($response->candles[0]->close));
    }
}
