<?php

namespace MarketDataApp\Tests\Unit;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Options\Expirations;
use MarketDataApp\Endpoints\Responses\Options\Lookup;
use MarketDataApp\Endpoints\Responses\Options\OptionChain;
use MarketDataApp\Endpoints\Responses\Options\OptionChains;
use MarketDataApp\Endpoints\Responses\Options\Quote;
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
        $mocked_response = [
            's'           => 'ok',
            'expirations' => ['2022-09-23', '2022-09-30'],
            'updated'     => 1663704000
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->expirations('AAPL');

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertCount(2, $response->expirations);
        $this->assertEquals(Carbon::parse($mocked_response['updated']), $response->updated);

        // Verify each item in the response is an object of the correct type and has the correct values.
        for ($i = 0; $i < count($response->expirations); $i++) {
            $this->assertEquals(Carbon::parse($mocked_response['expirations'][$i]), $response->expirations[$i]);
        }
    }

    public function testExpirations_noData_success()
    {
        $mocked_response = [
            's'        => 'no_data',
            'nextTime' => 1663704000,
            'prevTime' => 1663705000
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->expirations('AAPL');

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertEmpty($response->expirations);
        $this->assertEquals(Carbon::parse($mocked_response['nextTime']), $response->next_time);
        $this->assertEquals(Carbon::parse($mocked_response['prevTime']), $response->prev_time);
    }

    public function testLookup_success()
    {
        $mocked_response = [
            's'            => 'no_data',
            'optionSymbol' => 'AAPL230728C00200000',
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->lookup('AAPL 7/28/23 $200 Call');

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Lookup::class, $response);
        $this->assertEquals($mocked_response['optionSymbol'], $response->option_symbol);
    }

    public function testStrikes_success()
    {
        $mocked_response = [
            's'       => 'ok',
            'updated' => 1663704000,
            '2023-01-20' => [
                30.0,
                35.0
            ]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->strikes(
            symbol: 'AAPL',
            expiration: '2023-01-20',
            date: '2023-01-03',
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Strikes::class, $response);
        $this->assertEquals(Carbon::parse($mocked_response['updated']), $response->updated);
        $this->assertEquals($mocked_response['2023-01-20'], $response->dates['2023-01-20']);
    }

    public function testStrikes_noData_success()
    {
        $mocked_response = [
            's'        => 'no_data',
            'nextTime' => 1663704000,
            'prevTime' => 1663705000
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->strikes(
            symbol: 'AAPL',
            expiration: '2023-01-20',
            date: '2023-01-03',
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Strikes::class, $response);
        $this->assertEmpty($response->dates);
        $this->assertEquals(Carbon::parse($mocked_response['nextTime']), $response->next_time);
        $this->assertEquals(Carbon::parse($mocked_response['prevTime']), $response->prev_time);
    }

    public function testQuotes_success()
    {
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00060000', 'AAPL230616C00065000'],
            'ask'             => [116.9, 112.15],
            'askSize'         => [90, 90],
            'bid'             => [114.1, 108.6],
            'bidSize'         => [90, 90],
            'mid'             => [115.5, 110.38],
            'last'            => [115, 107.82],
            'openInterest'    => [21957, 3012],
            'volume'          => [0, 0],
            'inTheMoney'      => [true, true],
            'underlyingPrice' => [175.13, 175.13],
            'iv'              => [1.629, 1.923],
            'delta'           => [1, 1],
            'gamma'           => [0, 0],
            'theta'           => [-0.009, -0.009],
            'vega'            => [0, 0],
            'rho'             => [0.046, 0.05],
            'intrinsicValue'  => [115.13, 110.13],
            'extrinsicValue'  => [0.37, 0.25],
            'updated'         => [1684702875, 1684702875],
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->quotes('AAPL250117C00150000');

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals($mocked_response['s'], $response->status);
        $this->assertCount(2, $response->quotes);

        // Verify that the response is an object of the correct type.
        for ($i = 0; $i < count($response->quotes); $i++) {
            $this->assertInstanceOf(Quote::class, $response->quotes[$i]);
            $this->assertEquals($mocked_response['optionSymbol'][$i], $response->quotes[$i]->option_symbol);
            $this->assertEquals($mocked_response['ask'][$i], $response->quotes[$i]->ask);
            $this->assertEquals($mocked_response['askSize'][$i], $response->quotes[$i]->ask_size);
            $this->assertEquals($mocked_response['bid'][$i], $response->quotes[$i]->bid);
            $this->assertEquals($mocked_response['bidSize'][$i], $response->quotes[$i]->bid_size);
            $this->assertEquals($mocked_response['mid'][$i], $response->quotes[$i]->mid);
            $this->assertEquals($mocked_response['last'][$i], $response->quotes[$i]->last);
            $this->assertEquals($mocked_response['openInterest'][$i], $response->quotes[$i]->open_interest);
            $this->assertEquals($mocked_response['volume'][$i], $response->quotes[$i]->volume);
            $this->assertEquals($mocked_response['inTheMoney'][$i], $response->quotes[$i]->in_the_money);
            $this->assertEquals($mocked_response['underlyingPrice'][$i], $response->quotes[$i]->underlying_price);
            $this->assertEquals($mocked_response['iv'][$i], $response->quotes[$i]->implied_volatility);
            $this->assertEquals($mocked_response['delta'][$i], $response->quotes[$i]->delta);
            $this->assertEquals($mocked_response['gamma'][$i], $response->quotes[$i]->gamma);
            $this->assertEquals($mocked_response['theta'][$i], $response->quotes[$i]->theta);
            $this->assertEquals($mocked_response['vega'][$i], $response->quotes[$i]->vega);
            $this->assertEquals($mocked_response['rho'][$i], $response->quotes[$i]->rho);
            $this->assertEquals($mocked_response['intrinsicValue'][$i], $response->quotes[$i]->intrinsic_value);
            $this->assertEquals($mocked_response['extrinsicValue'][$i], $response->quotes[$i]->extrinsic_value);
            $this->assertEquals(Carbon::parse($mocked_response['updated'][$i]), $response->quotes[$i]->updated);
        }
    }

    public function testQuotes_noData_success()
    {
        $mocked_response = [
            's'        => 'no_data',
            'nextTime' => 1663704000,
            'prevTime' => 1663705000
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->quotes('AAPL');

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEmpty($response->quotes);
        $this->assertEquals(Carbon::parse($mocked_response['nextTime']), $response->next_time);
        $this->assertEquals(Carbon::parse($mocked_response['prevTime']), $response->prev_time);
    }

    public function testOptionChain_success()
    {
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00060000', 'AAPL230616C00065000'],
            'underlying'      => ['AAPL', 'AAPL'],
            'expiration'      => [1686945600, 1686945600],
            'side'            => ['call', 'call'],
            'strike'          => [60, 65],
            'firstTraded'     => [1617197400, 1616592600],
            'dte'             => [26, 26],
            'updated'         => [1684702875, 1684702875],
            'bid'             => [114.1, 108.6],
            'bidSize'         => [90, 90],
            'mid'             => [115.5, 110.38],
            'ask'             => [116.9, 112.15],
            'askSize'         => [90, 90],
            'last'            => [115, 107.82],
            'openInterest'    => [21957, 3012],
            'volume'          => [0, 0],
            'inTheMoney'      => [true, true],
            'intrinsicValue'  => [115.13, 110.13],
            'extrinsicValue'  => [0.37, 0.25],
            'underlyingPrice' => [175.13, 175.13],
            'iv'              => [1.629, 1.923],
            'delta'           => [1, 1],
            'gamma'           => [0, 0],
            'theta'           => [-0.009, -0.009],
            'vega'            => [0, 0],
            'rho'             => [0.046, 0.05]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: '2025-01-17',
            side: Side::CALL,
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertCount(2, $response->option_chains);

        // Verify each item in the response is an object of the correct type and has the correct values.
        for ($i = 0; $i < count($response->option_chains); $i++) {
            $this->assertInstanceOf(OptionChain::class, $response->option_chains[$i]);
            $this->assertEquals($mocked_response['optionSymbol'][$i], $response->option_chains[$i]->option_symbol);
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

    public function testOptionChain_noData_success()
    {
        $mocked_response = [
            's'        => 'no_data',
            'nextTime' => 1663704000,
            'prevTime' => 1663705000
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain('AAPL');

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEmpty($response->option_chains);
        $this->assertEquals(Carbon::parse($mocked_response['nextTime']), $response->next_time);
        $this->assertEquals(Carbon::parse($mocked_response['prevTime']), $response->prev_time);
    }
}
