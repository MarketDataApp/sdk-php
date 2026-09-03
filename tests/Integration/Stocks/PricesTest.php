<?php

namespace MarketDataApp\Tests\Integration\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Price;
use MarketDataApp\Endpoints\Responses\Stocks\Prices;
use MarketDataApp\Enums\Format;
use MarketDataApp\Exceptions\ApiException;

/**
 * Integration tests for the Stocks Prices endpoint.
 */
class PricesTest extends StocksTestCase
{
    /**
     * Test successful retrieval of stock prices for a single symbol.
     *
     * @throws GuzzleException|ApiException
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testPrices_singleSymbol_success()
    {
        $response = $this->client->stocks->prices('AAPL');

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->prices);
        $this->assertCount(1, $response->prices);
        $this->assertInstanceOf(Price::class, $response->prices[0]);
        $this->assertEquals('AAPL', $response->prices[0]->symbol);
        $this->assertTrue(in_array(gettype($response->prices[0]->mid), ['double', 'integer']), "Expected mid to be double or integer");
        $this->assertTrue(in_array(gettype($response->prices[0]->change), ['double', 'integer', 'NULL']), "Expected change to be double, integer, or NULL");
        $this->assertTrue(in_array(gettype($response->prices[0]->changepct), ['double', 'integer', 'NULL']), "Expected changepct to be double, integer, or NULL");
        $this->assertInstanceOf(Carbon::class, $response->prices[0]->updated);
    }

    /**
     * Test successful retrieval of stock prices for multiple symbols.
     *
     * @throws GuzzleException|ApiException
     */
    public function testPrices_multipleSymbols_success()
    {
        $response = $this->client->stocks->prices(['AAPL', 'META', 'MSFT']);

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->prices);
        $this->assertCount(3, $response->prices);

        // Collect symbols from prices
        $symbols = array_map(fn($price) => $price->symbol, $response->prices);
        $this->assertContains('AAPL', $symbols);
        $this->assertContains('META', $symbols);
        $this->assertContains('MSFT', $symbols);

        // Verify data types for each price
        foreach ($response->prices as $price) {
            $this->assertInstanceOf(Price::class, $price);
            $this->assertTrue(in_array(gettype($price->mid), ['double', 'integer']), "Expected mid to be double or integer, got " . gettype($price->mid));
            $this->assertTrue(in_array(gettype($price->change), ['double', 'integer', 'NULL']), "Expected change to be double, integer, or NULL, got " . gettype($price->change));
            $this->assertTrue(in_array(gettype($price->changepct), ['double', 'integer', 'NULL']), "Expected changepct to be double, integer, or NULL, got " . gettype($price->changepct));
            $this->assertInstanceOf(Carbon::class, $price->updated);
        }
    }

    /**
     * Test prices endpoint with extended=true parameter.
     *
     * @throws GuzzleException|ApiException
     */
    public function testPrices_extendedTrue_success()
    {
        $response = $this->client->stocks->prices('AAPL', extended: true);

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->prices);
        $this->assertCount(1, $response->prices);
        $this->assertInstanceOf(Price::class, $response->prices[0]);
    }

    /**
     * Test prices endpoint with extended=false parameter.
     *
     * @throws GuzzleException|ApiException
     */
    public function testPrices_extendedFalse_success()
    {
        $response = $this->client->stocks->prices('AAPL', extended: false);

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->prices);
        $this->assertCount(1, $response->prices);
        $this->assertInstanceOf(Price::class, $response->prices[0]);
    }

    /**
     * Test successful retrieval of stock prices in CSV format.
     *
     * @throws GuzzleException|ApiException
     */
    public function testPrices_csv_success()
    {
        $response = $this->client->stocks->prices(
            'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
        $this->assertNotEmpty($response->getCsv());
    }

    /**
     * Test prices endpoint with human-readable format.
     * Verifies that the API returns human-readable JSON keys with spaces.
     *
     * @throws GuzzleException|ApiException
     */
    public function testPrices_humanReadable_success()
    {
        $response = $this->client->stocks->prices(
            ['AAPL', 'META'],
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->prices);
        $this->assertCount(2, $response->prices);

        foreach ($response->prices as $price) {
            $this->assertInstanceOf(Price::class, $price);
            $this->assertInstanceOf(Carbon::class, $price->updated);
        }
    }
}
