<?php

namespace MarketDataApp\Tests\Integration;

use Carbon\Carbon;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\Expirations;
use MarketDataApp\Endpoints\Responses\Options\Lookup;
use MarketDataApp\Endpoints\Responses\Options\OptionChainStrike;
use MarketDataApp\Endpoints\Responses\Options\OptionChains;
use MarketDataApp\Endpoints\Responses\Options\Quote;
use MarketDataApp\Endpoints\Responses\Options\Quotes;
use MarketDataApp\Endpoints\Responses\Options\Strikes;
use MarketDataApp\Enums\Expiration;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Side;
use PHPUnit\Framework\TestCase;

/**
 * Class OptionsTest
 *
 * Integration tests for options-related functionality in the MarketDataApp.
 * This class tests various API endpoints related to options, including
 * expirations, lookups, strikes, quotes, and option chains.
 */
class OptionsTest extends TestCase
{

    /**
     * @var Client The client instance used for testing.
     */
    private Client $client;

    /**
     * Set up the test environment.
     * Initializes a new Client instance with the API token.
     */
    protected function setUp(): void
    {
        $token = 'your_api_token';
        $client = new Client($token);
        $this->client = $client;
    }

    /**
     * Test successful retrieval of option expirations.
     * Verifies that the response contains valid expiration dates.
     */
    public function testExpirations_success()
    {
        $response = $this->client->options->expirations('AAPL');

        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertNotEmpty($response->expirations);
        $this->assertInstanceOf(Carbon::class, $response->updated);
        $this->assertInstanceOf(Carbon::class, $response->expirations[0]);
    }

    /**
     * Test successful retrieval of option expirations in CSV format.
     * Verifies that the response is a string containing CSV data.
     */
    public function testExpirations_csv_success()
    {
        $response = $this->client->options->expirations(
            symbol: 'AAPL', parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test successful lookup of an option symbol.
     * Verifies that the response contains the correct option symbol.
     */
    public function testLookup_success()
    {
        $response = $this->client->options->lookup('AAPL 7/28/23 $200 Call');

        $this->assertInstanceOf(Lookup::class, $response);
        $this->assertEquals('AAPL230728C00200000', $response->option_symbol);
    }

    /**
     * Test successful retrieval of option strikes.
     * Verifies that the response contains valid strike prices.
     */
    public function testStrikes_success()
    {
        $response = $this->client->options->strikes(
            symbol: 'AAPL',
            date: '2023-01-03',
        );

        $this->assertInstanceOf(Strikes::class, $response);
        $this->assertInstanceOf(Carbon::class, $response->updated);
        $this->assertNotEmpty($response->dates);
        $this->assertNotEmpty(array_pop($response->dates));
    }

    /**
     * Test successful retrieval of option strikes in CSV format.
     * Verifies that the response is a string containing CSV data.
     */
    public function testStrikes_csv_success()
    {
        $response = $this->client->options->strikes(
            symbol: 'AAPL',
            date: '2023-01-03',
            parameters: new Parameters(format: Format::CSV),
        );

        $this->assertInstanceOf(Strikes::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test successful retrieval of option quotes.
     * Verifies that the response contains valid quote data with correct types.
     */
    public function testQuotes_success()
    {
        $response = $this->client->options->quotes('AAPL250117C00150000');

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->quotes);

        $this->assertInstanceOf(Quote::class, $response->quotes[0]);
        $this->assertEquals('string', gettype($response->quotes[0]->option_symbol));
        $this->assertEquals('double', gettype($response->quotes[0]->ask));
        $this->assertEquals('integer', gettype($response->quotes[0]->ask_size));
        $this->assertEquals('double', gettype($response->quotes[0]->bid));
        $this->assertEquals('integer', gettype($response->quotes[0]->bid_size));
        $this->assertEquals('double', gettype($response->quotes[0]->mid));
        $this->assertEquals('double', gettype($response->quotes[0]->last));
        $this->assertEquals('integer', gettype($response->quotes[0]->open_interest));
        $this->assertEquals('integer', gettype($response->quotes[0]->volume));
        $this->assertEquals('boolean', gettype($response->quotes[0]->in_the_money));
        $this->assertEquals('double', gettype($response->quotes[0]->underlying_price));
        $this->assertEquals('double', gettype($response->quotes[0]->implied_volatility));
        $this->assertEquals('double', gettype($response->quotes[0]->delta));
        $this->assertEquals('double', gettype($response->quotes[0]->gamma));
        $this->assertEquals('double', gettype($response->quotes[0]->theta));
        $this->assertEquals('double', gettype($response->quotes[0]->vega));
        $this->assertTrue(in_array(gettype($response->quotes[0]->rho), ['double', 'NULL']));
        $this->assertEquals('double', gettype($response->quotes[0]->intrinsic_value));
        $this->assertEquals('double', gettype($response->quotes[0]->extrinsic_value));
        $this->assertInstanceOf(Carbon::class, $response->quotes[0]->updated);
    }

    /**
     * Test successful retrieval of option quotes in CSV format.
     * Verifies that the response is a string containing CSV data.
     */
    public function testQuotes_csv_success()
    {
        $response = $this->client->options->quotes(
            option_symbol: 'AAPL250117C00150000',
            parameters: new Parameters(format: Format::CSV),
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test successful retrieval of option chain.
     * Verifies that the response contains valid option chain data with correct types.
     */
    public function testOptionChain_success()
    {
        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: '2025-01-17',
            side: Side::CALL,
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertNotEmpty($response->option_chains);
        $option_chain = array_pop($response->option_chains);
        $this->assertNotEmpty($option_chain);

        $option_strike = array_pop($option_chain);
        $this->assertInstanceOf(OptionChainStrike::class, $option_strike);
        $this->assertEquals('string', gettype($option_strike->option_symbol));
        $this->assertEquals('string', gettype($option_strike->underlying));
        $this->assertInstanceOf(Carbon::class, $option_strike->expiration);
        $this->assertInstanceOf(Side::class, $option_strike->side);
        $this->assertEquals('double', gettype($option_strike->strike));
        $this->assertInstanceOf(Carbon::class, $option_strike->first_traded);
        $this->assertEquals('integer', gettype($option_strike->dte));
        $this->assertInstanceOf(Carbon::class, $option_strike->updated);
        $this->assertEquals('double', gettype($option_strike->bid));
        $this->assertEquals('integer', gettype($option_strike->bid_size));
        $this->assertEquals('double', gettype($option_strike->mid));
        $this->assertEquals('double', gettype($option_strike->ask));
        $this->assertEquals('integer', gettype($option_strike->ask_size));
        $this->assertTrue(in_array(gettype($option_strike->last), ['double', 'NULL']));
        $this->assertEquals('integer', gettype($option_strike->open_interest));
        $this->assertEquals('integer', gettype($option_strike->volume));
        $this->assertEquals('boolean', gettype($option_strike->in_the_money));
        $this->assertEquals('double', gettype($option_strike->intrinsic_value));
        $this->assertEquals('double', gettype($option_strike->extrinsic_value));
        $this->assertEquals('double', gettype($option_strike->implied_volatility));
        $this->assertTrue(in_array(gettype($option_strike->delta), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($option_strike->gamma), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($option_strike->theta), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($option_strike->vega), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($option_strike->rho), ['double', 'NULL']));
        $this->assertEquals('double', gettype($option_strike->underlying_price));
    }

    /**
     * Test successful retrieval of option chain in CSV format.
     * Verifies that the response is a string containing CSV data.
     */
    public function testOptionChain_csv_success()
    {
        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: '2025-01-17',
            side: Side::CALL,
            parameters: new Parameters(format: Format::CSV),
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test successful retrieval of option chain using Expiration enum.
     * Verifies that the response contains valid option chain data with correct types
     * when using the Expiration::ALL enum value.
     */
    public function testOptionChain_expirationEnum_success()
    {
        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: Expiration::ALL,
            side: Side::CALL,
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertNotEmpty($response->option_chains);
        $option_chain = array_pop($response->option_chains);
        $this->assertNotEmpty($option_chain);

        $option_strike = array_pop($option_chain);
        $this->assertInstanceOf(OptionChainStrike::class, $option_strike);
        $this->assertEquals('string', gettype($option_strike->option_symbol));
        $this->assertEquals('string', gettype($option_strike->underlying));
        $this->assertInstanceOf(Carbon::class, $option_strike->expiration);
        $this->assertInstanceOf(Side::class, $option_strike->side);
        $this->assertEquals('double', gettype($option_strike->strike));
        $this->assertInstanceOf(Carbon::class, $option_strike->first_traded);
        $this->assertEquals('integer', gettype($option_strike->dte));
        $this->assertInstanceOf(Carbon::class, $option_strike->updated);
        $this->assertEquals('double', gettype($option_strike->bid));
        $this->assertEquals('integer', gettype($option_strike->bid_size));
        $this->assertEquals('double', gettype($option_strike->mid));
        $this->assertEquals('double', gettype($option_strike->ask));
        $this->assertEquals('integer', gettype($option_strike->ask_size));
        $this->assertTrue(in_array(gettype($option_strike->last), ['double', 'NULL']));
        $this->assertEquals('integer', gettype($option_strike->open_interest));
        $this->assertEquals('integer', gettype($option_strike->volume));
        $this->assertEquals('boolean', gettype($option_strike->in_the_money));
        $this->assertEquals('double', gettype($option_strike->intrinsic_value));
        $this->assertEquals('double', gettype($option_strike->extrinsic_value));
        $this->assertEquals('double', gettype($option_strike->implied_volatility));
        $this->assertTrue(in_array(gettype($option_strike->delta), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($option_strike->gamma), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($option_strike->theta), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($option_strike->vega), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($option_strike->rho), ['double', 'NULL']));
        $this->assertEquals('double', gettype($option_strike->underlying_price));
    }
}
