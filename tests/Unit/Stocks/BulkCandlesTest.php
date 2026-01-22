<?php

namespace MarketDataApp\Tests\Unit\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\BulkCandles;
use MarketDataApp\Endpoints\Responses\Stocks\Candle;
use MarketDataApp\Enums\Format;
use MarketDataApp\Exceptions\ApiException;

/**
 * Test case for the BulkCandles endpoint of the Stocks API.
 */
class BulkCandlesTest extends StocksTestCase
{
    /**
     * Test the bulkCandles endpoint for a successful response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testBulkCandles_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = [
            's' => 'ok',
            'symbol' => ['AAPL', 'MSFT'],
            'o' => [248.7, 452.595],
            'h' => [251.56, 452.69],
            'l' => [245.18, 438.68],
            'c' => [247.65, 444.11],
            'v' => [54933217, 37939952],
            't' => [1768971600, 1768971600]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->bulkCandles(
            symbols: ["AAPL", "MSFT"],
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(BulkCandles::class, $response);
        $this->assertCount(2, $response->candles);

        // Verify each item in the response is an object of the correct type and has the correct values.
        for ($i = 0; $i < count($response->candles); $i++) {
            $this->assertInstanceOf(Candle::class, $response->candles[$i]);
            $this->assertEquals($mocked_response['c'][$i], $response->candles[$i]->close);
            $this->assertEquals($mocked_response['h'][$i], $response->candles[$i]->high);
            $this->assertEquals($mocked_response['l'][$i], $response->candles[$i]->low);
            $this->assertEquals($mocked_response['o'][$i], $response->candles[$i]->open);
            $this->assertEquals($mocked_response['v'][$i], $response->candles[$i]->volume);
            $this->assertEquals(Carbon::parse($mocked_response['t'][$i]), $response->candles[$i]->timestamp);
        }
    }

    /**
     * Test the bulkCandles endpoint for a successful CSV response.
     *
     * @return void
     */
    public function testBulkCandles_csv_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = "symbol,o,h,l,c,v,t\nAAPL,248.7,251.56,245.18,247.65,54933217,1768971600\nMSFT,452.595,452.69,438.68,444.11,37939952,1768971600";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->bulkCandles(
            symbols: ["AAPL", "MSFT"],
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV)
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(BulkCandles::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the bulkCandles endpoint with human-readable format.
     *
     * @return void
     */
    public function testBulkCandles_humanReadable_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        // Note: Using same structure as regular candles human-readable format
        $mocked_response = [
            'Date' => [1662004800, 1662091200],
            'Open' => [156.64, 159.75],
            'High' => [158.42, 160.362],
            'Low' => [154.67, 154.965],
            'Close' => [157.96, 155.81],
            'Volume' => [74229896, 76957768]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->bulkCandles(
            symbols: ["AAPL", "MSFT"],
            resolution: 'D',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(BulkCandles::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(2, $response->candles);
        $this->assertEquals($mocked_response['Open'][0], $response->candles[0]->open);
    }

    /**
     * Test the bulkCandles endpoint for a successful 'no data' response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testBulkCandles_noData_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's' => 'no_data',
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->bulkCandles(
            symbols: ["AAPL", "MSFT"],
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(BulkCandles::class, $response);
        $this->assertEmpty($response->candles);
    }

    /**
     * Test the bulkCandles endpoint for invalid arguments.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testBulkCandles_invalidArguments_throwsInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);

        // Must have snapshot or symbols
        $this->client->stocks->bulkCandles(resolution: 'D');
    }

    /**
     * Test bulkCandles endpoint with invalid resolution.
     */
    public function testBulkCandles_invalidResolution_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid resolution format');

        $this->client->stocks->bulkCandles(
            symbols: ['AAPL'],
            resolution: 'invalid'
        );
    }
}
