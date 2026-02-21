<?php

namespace MarketDataApp\Tests\Unit\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Price;
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
        $this->assertCount(1, $response->prices);
        $this->assertInstanceOf(Price::class, $response->prices[0]);
        $this->assertEquals('AAPL', $response->prices[0]->symbol);
        $this->assertEquals(248.4436, $response->prices[0]->mid);
        $this->assertEquals(1.7436, $response->prices[0]->change);
        $this->assertEquals(0.0071, $response->prices[0]->changepct);
        $this->assertInstanceOf(Carbon::class, $response->prices[0]->updated);
        $this->assertEquals(Carbon::parse(1769043587), $response->prices[0]->updated);
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
        $this->assertCount(3, $response->prices);

        // Verify each Price object
        $this->assertInstanceOf(Price::class, $response->prices[0]);
        $this->assertEquals('AAPL', $response->prices[0]->symbol);
        $this->assertEquals(248.4436, $response->prices[0]->mid);
        $this->assertEquals(1.7436, $response->prices[0]->change);
        $this->assertEquals(0.0071, $response->prices[0]->changepct);

        $this->assertInstanceOf(Price::class, $response->prices[1]);
        $this->assertEquals('META', $response->prices[1]->symbol);
        $this->assertEquals(615.8339, $response->prices[1]->mid);
        $this->assertEquals(11.7139, $response->prices[1]->change);
        $this->assertEquals(0.0194, $response->prices[1]->changepct);

        $this->assertInstanceOf(Price::class, $response->prices[2]);
        $this->assertEquals('MSFT', $response->prices[2]->symbol);
        $this->assertEquals(446.1768, $response->prices[2]->mid);
        $this->assertEquals(-8.3432, $response->prices[2]->change);
        $this->assertEquals(-0.0184, $response->prices[2]->changepct);

        foreach ($response->prices as $price) {
            $this->assertInstanceOf(Carbon::class, $price->updated);
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
        $this->assertCount(1, $response->prices);
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
        $this->assertCount(1, $response->prices);
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
        $this->assertCount(2, $response->prices);

        $this->assertEquals('AAPL', $response->prices[0]->symbol);
        $this->assertEquals(248.4436, $response->prices[0]->mid);
        $this->assertEquals(1.7436, $response->prices[0]->change);
        $this->assertEquals(0.0071, $response->prices[0]->changepct);

        $this->assertEquals('META', $response->prices[1]->symbol);
        $this->assertEquals(615.8339, $response->prices[1]->mid);
        $this->assertEquals(11.7139, $response->prices[1]->change);
        $this->assertEquals(0.0194, $response->prices[1]->changepct);

        foreach ($response->prices as $price) {
            $this->assertInstanceOf(Carbon::class, $price->updated);
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
        $this->assertEmpty($response->prices);
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

    /**
     * Test that prices properties are accessible for CSV responses (BUG-013 fix).
     *
     * CSV responses trigger an early return in the constructor. Properties should
     * have default values to prevent "uninitialized property" errors.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testPrices_csv_propertiesAccessible(): void
    {
        // Mock response: NOT from real API output (uses synthetic CSV data)
        $csvResponse = "symbol,mid,change,changepct,updated\nAAPL,248.44,1.74,0.0071,1769043587";
        $this->setMockResponses([new Response(200, [], $csvResponse)]);

        $response = $this->client->stocks->prices(
            'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        // These should NOT throw "uninitialized property" errors
        $this->assertEquals('no_data', $response->status);
        $this->assertIsArray($response->prices);
        $this->assertCount(0, $response->prices);
    }

    /**
     * Test that Price object has correct __toString output.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testPrice_toString_returnsFormattedString(): void
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
        $price = $response->prices[0];

        $string = (string) $price;
        $this->assertStringContainsString('AAPL', $string);
        $this->assertStringContainsString('$248.44', $string);
        $this->assertStringContainsString('+0.71%', $string);
    }
}
