<?php

namespace MarketDataApp\Tests\Integration;

use Carbon\Carbon;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Options\Expirations;
use MarketDataApp\Endpoints\Responses\Options\Lookup;
use MarketDataApp\Endpoints\Responses\Options\OptionChainStrike;
use MarketDataApp\Endpoints\Responses\Options\OptionChains;
use MarketDataApp\Endpoints\Responses\Options\Quote;
use MarketDataApp\Endpoints\Responses\Options\Quotes;
use MarketDataApp\Endpoints\Responses\Options\Strikes;
use MarketDataApp\Enums\Side;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{

    private Client $client;

    protected function setUp(): void
    {
        $token = 'your_api_token';
        $client = new Client($token);
        $this->client = $client;
    }

    public function testExpirations_success()
    {
        $response = $this->client->options->expirations('AAPL');

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertNotEmpty($response->expirations);
        $this->assertInstanceOf(Carbon::class, $response->updated);
        $this->assertInstanceOf(Carbon::class, $response->expirations[0]);
    }


    public function testLookup_success()
    {
        $response = $this->client->options->lookup('AAPL 7/28/23 $200 Call');

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Lookup::class, $response);
        $this->assertEquals('AAPL230728C00200000', $response->option_symbol);
    }

    public function testStrikes_success()
    {
        $response = $this->client->options->strikes(
            symbol: 'AAPL',
            date: '2023-01-03',
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Strikes::class, $response);
        $this->assertInstanceOf(Carbon::class, $response->updated);
        $this->assertNotEmpty($response->dates);
        $this->assertNotEmpty(array_pop($response->dates));
    }

    public function testQuotes_success()
    {
        $response = $this->client->options->quotes('AAPL250117C00150000');

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->quotes);

        // Verify that the response is an object of the correct type.
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
        $this->assertEquals('double', gettype($response->quotes[0]->rho));
        $this->assertEquals('double', gettype($response->quotes[0]->intrinsic_value));
        $this->assertEquals('double', gettype($response->quotes[0]->extrinsic_value));
        $this->assertInstanceOf(Carbon::class, $response->quotes[0]->updated);
    }

    public function testOptionChain_success()
    {
        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: '2025-01-17',
            side: Side::CALL,
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertNotEmpty($response->option_chains);
        $option_chain = array_pop($response->option_chains);
        $this->assertNotEmpty($option_chain);

        // Verify each item in the response is an object of the correct type and has the correct values.
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
        $this->assertEquals('double', gettype($option_strike->gamma));
        $this->assertEquals('double', gettype($option_strike->theta));
        $this->assertEquals('double', gettype($option_strike->vega));
        $this->assertEquals('double', gettype($option_strike->rho));
        $this->assertEquals('double', gettype($option_strike->underlying_price));
    }
}
