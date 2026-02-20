<?php

namespace MarketDataApp\Tests\Integration\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Endpoints\Requests\Parameters;
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
    public function testPrices_singleSymbol_success()
    {
        $response = $this->client->stocks->prices('AAPL');

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->symbols);
        $this->assertCount(1, $response->symbols);
        $this->assertEquals('AAPL', $response->symbols[0]);
        $this->assertNotEmpty($response->mid);
        $this->assertCount(1, $response->mid);
        $this->assertTrue(in_array(gettype($response->mid[0]), ['double', 'integer']), "Expected mid to be double or integer");
        $this->assertNotEmpty($response->change);
        $this->assertCount(1, $response->change);
        $this->assertTrue(in_array(gettype($response->change[0]), ['double', 'integer', 'NULL']));
        $this->assertNotEmpty($response->changepct);
        $this->assertCount(1, $response->changepct);
        $this->assertTrue(in_array(gettype($response->changepct[0]), ['double', 'integer', 'NULL']));
        $this->assertNotEmpty($response->updated);
        $this->assertCount(1, $response->updated);
        $this->assertInstanceOf(Carbon::class, $response->updated[0]);
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
        $this->assertNotEmpty($response->symbols);
        $this->assertCount(3, $response->symbols);
        $this->assertContains('AAPL', $response->symbols);
        $this->assertContains('META', $response->symbols);
        $this->assertContains('MSFT', $response->symbols);

        // Verify all arrays have the same length
        $this->assertCount(3, $response->mid);
        $this->assertCount(3, $response->change);
        $this->assertCount(3, $response->changepct);
        $this->assertCount(3, $response->updated);

        // Verify data types (API may return integer for round numbers or double for decimals)
        foreach ($response->mid as $mid) {
            $this->assertTrue(in_array(gettype($mid), ['double', 'integer']), "Expected mid to be double or integer, got " . gettype($mid));
        }
        foreach ($response->change as $change) {
            $this->assertTrue(in_array(gettype($change), ['double', 'integer', 'NULL']), "Expected change to be double, integer, or NULL, got " . gettype($change));
        }
        foreach ($response->changepct as $changepct) {
            $this->assertTrue(in_array(gettype($changepct), ['double', 'integer', 'NULL']), "Expected changepct to be double, integer, or NULL, got " . gettype($changepct));
        }
        foreach ($response->updated as $updated) {
            $this->assertInstanceOf(Carbon::class, $updated);
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
        $this->assertNotEmpty($response->symbols);
        $this->assertCount(1, $response->symbols);
        $this->assertNotEmpty($response->mid);
        $this->assertNotEmpty($response->updated);
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
        $this->assertNotEmpty($response->symbols);
        $this->assertCount(1, $response->symbols);
        $this->assertNotEmpty($response->mid);
        $this->assertNotEmpty($response->updated);
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
        $this->assertNotEmpty($response->symbols);
        $this->assertCount(2, $response->symbols);
        $this->assertNotEmpty($response->mid);
        $this->assertCount(2, $response->mid);
        $this->assertNotEmpty($response->change);
        $this->assertCount(2, $response->change);
        $this->assertNotEmpty($response->changepct);
        $this->assertCount(2, $response->changepct);
        $this->assertNotEmpty($response->updated);
        $this->assertCount(2, $response->updated);
        foreach ($response->updated as $updated) {
            $this->assertInstanceOf(Carbon::class, $updated);
        }
    }
}
