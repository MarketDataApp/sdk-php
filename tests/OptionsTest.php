<?php

namespace MarketDataApp\Tests;

use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Options\Expirations;
use MarketDataApp\Endpoints\Responses\Options\Lookup;
use MarketDataApp\Endpoints\Responses\Options\OptionChain;
use MarketDataApp\Endpoints\Responses\Options\OptionChains;
use MarketDataApp\Endpoints\Responses\Options\Quotes;
use MarketDataApp\Endpoints\Responses\Options\Strikes;
use MarketDataApp\Enums\Side;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{

    use MockResponses;

    private Client $client;

    protected function setUp(): void
    {
        $token = 'your_api_token';
        $client = new Client($token);
        $this->client = $client;
    }

    public function testExpirations_success()
    {
        // Stub
        $this->assertInstanceOf(Expirations::class, $this->client->options->expirations());
    }

    public function testLookup_success()
    {
        // Stub
        $this->assertInstanceOf(Lookup::class, $this->client->options->lookup());
    }

    public function testStrikes_success()
    {
        // Stub
        $this->assertInstanceOf(Strikes::class, $this->client->options->strikes());
    }

    public function testQuotes_success()
    {
        // Stub
        $this->assertInstanceOf(Quotes::class, $this->client->options->quotes());
    }

    public function testOptionChain_success()
    {
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00060000', 'AAPL230616C00065000'],
            'underlying'      => ['AAPL', 'AAPL',],
            'expiration'      => [1686945600, 1686945600,],
            'side'            => ['call', 'call',],
            'strike'          => [60, 65,],
            'firstTraded'     => [1617197400, 1616592600,],
            'dte'             => [26, 26,],
            'updated'         => [1684702875, 1684702875,],
            'bid'             => [114.1, 108.6,],
            'bidSize'         => [90, 90,],
            'mid'             => [115.5, 110.38,],
            'ask'             => [116.9, 112.15,],
            'askSize'         => [90, 90,],
            'last'            => [115, 107.82,],
            'openInterest'    => [21957, 3012,],
            'volume'          => [0, 0,],
            'inTheMoney'      => [true, true,],
            'intrinsicValue'  => [115.13, 110.13,],
            'extrinsicValue'  => [0.37, 0.25,],
            'underlyingPrice' => [175.13, 175.13,],
            'iv'              => [1.629, 1.923,],
            'delta'           => [1, 1,],
            'gamma'           => [0, 0,],
            'theta'           => [-0.009, -0.009,],
            'vega'            => [0, 0,],
            'rho'             => [0.046, 0.05,]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: Carbon::parse('20225-01-17'),
            side: Side::CALL,
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertCount(2, $response->option_chains);

        // Verify each item in the response is an object of the correct type and has the correct values.
        for ($i = 0; $i < count($response->option_chains); $i++) {
            $this->assertInstanceOf(OptionChain::class, $response->option_chains[$i]);
            $this->assertEquals($mocked_response['optionSymbol'][$i], $response->option_chains[$i]->symbol);
            $this->assertEquals($mocked_response['underlying'][$i], $response->option_chains[$i]->underlying);
            $this->assertEquals(Carbon::parse($mocked_response['expiration'][$i]),
                $response->option_chains[$i]->expiration);
            $this->assertEquals(Side::from($mocked_response['side'][$i]), $response->option_chains[$i]->side);
            $this->assertEquals($mocked_response['strike'][$i], $response->option_chains[$i]->strike);
            $this->assertEquals(Carbon::parse($mocked_response['firstTraded'][$i]),
                $response->option_chains[$i]->first_traded);
            $this->assertEquals($mocked_response['dte'][$i], $response->option_chains[$i]->dte);
            $this->assertEquals(Carbon::parse($mocked_response['updated'][$i]), $response->option_chains[$i]->updated);
            $this->assertEquals($mocked_response['bid'][$i], $response->option_chains[$i]->bid);
            $this->assertEquals($mocked_response['bidSize'][$i], $response->option_chains[$i]->bid_size);
            $this->assertEquals($mocked_response['mid'][$i], $response->option_chains[$i]->mid);
            $this->assertEquals($mocked_response['ask'][$i], $response->option_chains[$i]->ask);
            $this->assertEquals($mocked_response['askSize'][$i], $response->option_chains[$i]->ask_size);
            $this->assertEquals($mocked_response['last'][$i], $response->option_chains[$i]->last);
            $this->assertEquals($mocked_response['openInterest'][$i], $response->option_chains[$i]->open_interest);
            $this->assertEquals($mocked_response['volume'][$i], $response->option_chains[$i]->volume);
            $this->assertEquals($mocked_response['inTheMoney'][$i], $response->option_chains[$i]->in_the_money);
            $this->assertEquals($mocked_response['intrinsicValue'][$i], $response->option_chains[$i]->intrinsic_value);
            $this->assertEquals($mocked_response['extrinsicValue'][$i], $response->option_chains[$i]->extrinsic_value);
            $this->assertEquals($mocked_response['iv'][$i], $response->option_chains[$i]->implied_volatility);
            $this->assertEquals($mocked_response['delta'][$i], $response->option_chains[$i]->delta);
            $this->assertEquals($mocked_response['gamma'][$i], $response->option_chains[$i]->gamma);
            $this->assertEquals($mocked_response['theta'][$i], $response->option_chains[$i]->theta);
            $this->assertEquals($mocked_response['vega'][$i], $response->option_chains[$i]->vega);
            $this->assertEquals($mocked_response['rho'][$i], $response->option_chains[$i]->rho);
            $this->assertEquals($mocked_response['underlyingPrice'][$i],
                $response->option_chains[$i]->underlying_price);
        }
    }
}
