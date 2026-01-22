<?php

namespace MarketDataApp\Tests\Unit\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Prices;
use MarketDataApp\Enums\Format;
use MarketDataApp\Exceptions\ApiException;

/**
 * Test case for the Prices endpoint of the Stocks API.
 */
class PricesTest extends StocksTestCase
{
    /**
     * Test the prices endpoint for a successful response with single symbol.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testPrices_singleSymbol_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'mid' => [248.4436],
            'change' => [1.7436],
            'changepct' => [0.0071],
            'updated' => [1769043587]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->prices('AAPL');

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->symbols);
        $this->assertEquals('AAPL', $response->symbols[0]);
        $this->assertCount(1, $response->mid);
        $this->assertEquals(248.4436, $response->mid[0]);
        $this->assertCount(1, $response->change);
        $this->assertEquals(1.7436, $response->change[0]);
        $this->assertCount(1, $response->changepct);
        $this->assertEquals(0.0071, $response->changepct[0]);
        $this->assertCount(1, $response->updated);
        $this->assertInstanceOf(Carbon::class, $response->updated[0]);
        $this->assertEquals(Carbon::parse(1769043587), $response->updated[0]);
    }

    /**
     * Test the prices endpoint for a successful response with multiple symbols.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testPrices_multipleSymbols_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = [
            's' => 'ok',
            'symbol' => ['AAPL', 'META', 'MSFT'],
            'mid' => [248.4436, 615.8339, 446.1768],
            'change' => [1.7436, 11.7139, -8.3432],
            'changepct' => [0.0071, 0.0194, -0.0184],
            'updated' => [1769043587, 1769043590, 1769043597]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->prices(['AAPL', 'META', 'MSFT']);

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(3, $response->symbols);
        $this->assertEquals(['AAPL', 'META', 'MSFT'], $response->symbols);
        $this->assertCount(3, $response->mid);
        $this->assertEquals([248.4436, 615.8339, 446.1768], $response->mid);
        $this->assertCount(3, $response->change);
        $this->assertEquals([1.7436, 11.7139, -8.3432], $response->change);
        $this->assertCount(3, $response->changepct);
        $this->assertEquals([0.0071, 0.0194, -0.0184], $response->changepct);
        $this->assertCount(3, $response->updated);
        foreach ($response->updated as $updated) {
            $this->assertInstanceOf(Carbon::class, $updated);
        }
    }

    /**
     * Test the prices endpoint with extended=true parameter.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testPrices_extendedTrue_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'mid' => [248.4436],
            'change' => [1.7436],
            'changepct' => [0.0071],
            'updated' => [1769043587]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->prices('AAPL', extended: true);

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->symbols);
    }

    /**
     * Test the prices endpoint with extended=false parameter.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testPrices_extendedFalse_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'mid' => [247.65],
            'change' => [0.95],
            'changepct' => [0.0039],
            'updated' => [1769043587]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->prices('AAPL', extended: false);

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->symbols);
    }

    /**
     * Test the prices endpoint for a successful CSV response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testPrices_csv_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = "symbol,mid,change,changepct,updated\nAAPL,248.4436,1.7436,0.0071,1769043587";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->prices(
            'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the prices endpoint with human-readable format.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testPrices_humanReadable_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = [
            'Symbol' => ['AAPL', 'META'],
            'Mid' => [248.4436, 615.8339],
            'Change $' => [1.7436, 11.7139],
            'Change %' => [0.0071, 0.0194],
            'Date' => [1769043587, 1769043590]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->prices(
            ['AAPL', 'META'],
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(2, $response->symbols);
        $this->assertEquals(['AAPL', 'META'], $response->symbols);
        $this->assertCount(2, $response->mid);
        $this->assertEquals([248.4436, 615.8339], $response->mid);
        $this->assertCount(2, $response->change);
        $this->assertEquals([1.7436, 11.7139], $response->change);
        $this->assertCount(2, $response->changepct);
        $this->assertEquals([0.0071, 0.0194], $response->changepct);
        $this->assertCount(2, $response->updated);
        foreach ($response->updated as $updated) {
            $this->assertInstanceOf(Carbon::class, $updated);
        }
    }

    /**
     * Test the prices endpoint for a successful 'no data' response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testPrices_noData_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's' => 'no_data',
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->prices('INVALID');

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('no_data', $response->status);
        $this->assertEmpty($response->symbols);
        $this->assertEmpty($response->mid);
        $this->assertEmpty($response->change);
        $this->assertEmpty($response->changepct);
        $this->assertEmpty($response->updated);
    }

    /**
     * Test the prices endpoint for an error response.
     *
     * @return void
     * @throws GuzzleException
     */
    public function testPrices_errorResponse_throwsApiException()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's' => 'error',
            'errmsg' => 'Invalid request'
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid request');

        $this->client->stocks->prices('INVALID');
    }

    /**
     * Test prices endpoint with empty string symbol.
     */
    public function testPrices_emptyStringSymbol_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-empty string');

        $this->client->stocks->prices('');
    }

    /**
     * Test prices endpoint with empty array.
     */
    public function testPrices_emptyArray_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-empty array');

        $this->client->stocks->prices([]);
    }
}
