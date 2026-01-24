<?php

namespace MarketDataApp\Tests\Unit\Options;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\OptionQuote;
use MarketDataApp\Endpoints\Responses\Options\OptionChains;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Side;

/**
 * Unit tests for the Options OptionChain endpoint.
 */
class OptionChainTest extends OptionsTestCase
{
    /**
     * Test the option_chain endpoint for a successful response.
     */
    public function testOptionChain_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00060000', 'AAPL230616C00065000', 'AAPL230616C00075000'],
            'underlying'      => ['AAPL', 'AAPL', 'AAPL'],
            'expiration'      => [1686945600, 1686945600, 1687045600],
            'side'            => ['call', 'call', 'call'],
            'strike'          => [60, 65, 60],
            'firstTraded'     => [1617197400, 1616592600, 1616602600],
            'dte'             => [26, 26, 33],
            'updated'         => [1684702875, 1684702875, 1684702876],
            'bid'             => [114.1, 108.6, 120.5],
            'bidSize'         => [90, 90, 95],
            'mid'             => [115.5, 110.38, 120.5],
            'ask'             => [116.9, 112.15, 118.5],
            'askSize'         => [90, 90, 95],
            'last'            => [115, 107.82, 119.3],
            'openInterest'    => [21957, 3012, 5000],
            'volume'          => [0, 0, 100],
            'inTheMoney'      => [true, true, true],
            'intrinsicValue'  => [115.13, 110.13, 119.13],
            'extrinsicValue'  => [0.37, 0.25, 0.13],
            'underlyingPrice' => [175.13, 175.13, 118.5],
            'iv'              => [1.629, 1.923, 1.753],
            'delta'           => [1, 1, -0.95],
            'gamma'           => [0, 0, 0.3],
            'theta'           => [-0.009, -0.009, -.3],
            'vega'            => [0, 0, 0.3]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            side: Side::CALL,
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertCount(2, $response->option_chains);
        $this->assertCount(2, $response->option_chains['2023-06-16']);
        $this->assertCount(1, $response->option_chains['2023-06-17']);

        foreach (array_merge(...array_values($response->option_chains)) as $i => $option_strike) {
            $this->assertInstanceOf(OptionQuote::class, $option_strike);
            $this->assertEquals($mocked_response['optionSymbol'][$i], $option_strike->option_symbol);
            $this->assertEquals($mocked_response['underlying'][$i], $option_strike->underlying);
            $this->assertEquals(Carbon::parse($mocked_response['expiration'][$i]),
                $option_strike->expiration);
            $this->assertEquals(Side::from($mocked_response['side'][$i]), $option_strike->side);
            $this->assertEquals($mocked_response['strike'][$i], $option_strike->strike);
            $this->assertEquals(Carbon::parse($mocked_response['firstTraded'][$i]),
                $option_strike->first_traded);
            $this->assertEquals($mocked_response['dte'][$i], $option_strike->dte);
            $this->assertEquals(Carbon::parse($mocked_response['updated'][$i]), $option_strike->updated);
            $this->assertEquals($mocked_response['bid'][$i], $option_strike->bid);
            $this->assertEquals($mocked_response['bidSize'][$i], $option_strike->bid_size);
            $this->assertEquals($mocked_response['mid'][$i], $option_strike->mid);
            $this->assertEquals($mocked_response['ask'][$i], $option_strike->ask);
            $this->assertEquals($mocked_response['askSize'][$i], $option_strike->ask_size);
            $this->assertEquals($mocked_response['last'][$i], $option_strike->last);
            $this->assertEquals($mocked_response['openInterest'][$i], $option_strike->open_interest);
            $this->assertEquals($mocked_response['volume'][$i], $option_strike->volume);
            $this->assertEquals($mocked_response['inTheMoney'][$i], $option_strike->in_the_money);
            $this->assertEquals($mocked_response['intrinsicValue'][$i], $option_strike->intrinsic_value);
            $this->assertEquals($mocked_response['extrinsicValue'][$i], $option_strike->extrinsic_value);
            $this->assertEquals($mocked_response['iv'][$i], $option_strike->implied_volatility);
            $this->assertEquals($mocked_response['delta'][$i], $option_strike->delta);
            $this->assertEquals($mocked_response['gamma'][$i], $option_strike->gamma);
            $this->assertEquals($mocked_response['theta'][$i], $option_strike->theta);
            $this->assertEquals($mocked_response['vega'][$i], $option_strike->vega);
            $this->assertEquals($mocked_response['underlyingPrice'][$i],
                $option_strike->underlying_price);
        }
    }

    /**
     * Test the option_chain endpoint for a successful CSV response.
     */
    public function testOptionChain_csv_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, optionSymbol, underlying...\r\n";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            side: Side::CALL,
            parameters: new Parameters(Format::CSV)
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the option_chain endpoint for a successful 'no data' response.
     */
    public function testOptionChain_noData_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'        => 'no_data',
            'nextTime' => 1663704000,
            'prevTime' => 1663705000
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain('AAPL');

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEmpty($response->option_chains);
        $this->assertEquals(Carbon::parse($mocked_response['nextTime']), $response->next_time);
        $this->assertEquals(Carbon::parse($mocked_response['prevTime']), $response->prev_time);
    }

    /**
     * Test the option_chain endpoint with human-readable format.
     */
    public function testOptionChain_humanReadable_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'Symbol' => ['AAPL230616C00060000', 'AAPL230616C00065000'],
            'Underlying' => ['AAPL', 'AAPL'],
            'Expiration Date' => [1686945600, 1686945600],
            'Option Side' => ['call', 'call'],
            'Strike' => [60, 65],
            'First Traded' => [1617197400, 1616592600],
            'Days To Expiration' => [26, 26],
            'Date' => [1684702875, 1684702875],
            'Bid' => [114.1, 108.6],
            'Bid Size' => [90, 90],
            'Mid' => [115.5, 110.38],
            'Ask' => [116.9, 112.15],
            'Ask Size' => [90, 90],
            'Last' => [115, 107.82],
            'Open Interest' => [21957, 3012],
            'Volume' => [0, 0],
            'In The Money' => [true, true],
            'Intrinsic Value' => [115.13, 110.13],
            'Extrinsic Value' => [0.37, 0.25],
            'Underlying Price' => [175.13, 175.13],
            'IV' => [1.629, 1.923],
            'Delta' => [1, 1],
            'Gamma' => [0, 0],
            'Theta' => [-0.009, -0.009],
            'Vega' => [0, 0]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            side: Side::CALL,
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->option_chains);
        $this->assertCount(2, $response->option_chains['2023-06-16']);

        $option_strikes = $response->option_chains['2023-06-16'];
        for ($i = 0; $i < count($option_strikes); $i++) {
            $option_strike = $option_strikes[$i];
            $this->assertInstanceOf(OptionQuote::class, $option_strike);
            $this->assertEquals($mocked_response['Symbol'][$i], $option_strike->option_symbol);
            $this->assertEquals($mocked_response['Underlying'][$i], $option_strike->underlying);
            $this->assertEquals(Carbon::parse($mocked_response['Expiration Date'][$i]),
                $option_strike->expiration);
            $this->assertEquals(Side::from($mocked_response['Option Side'][$i]), $option_strike->side);
            $this->assertEquals($mocked_response['Strike'][$i], $option_strike->strike);
            $this->assertEquals(Carbon::parse($mocked_response['First Traded'][$i]),
                $option_strike->first_traded);
            $this->assertEquals($mocked_response['Days To Expiration'][$i], $option_strike->dte);
            $this->assertEquals(Carbon::parse($mocked_response['Date'][$i]), $option_strike->updated);
            $this->assertEquals($mocked_response['Bid'][$i], $option_strike->bid);
            $this->assertEquals($mocked_response['Bid Size'][$i], $option_strike->bid_size);
            $this->assertEquals($mocked_response['Mid'][$i], $option_strike->mid);
            $this->assertEquals($mocked_response['Ask'][$i], $option_strike->ask);
            $this->assertEquals($mocked_response['Ask Size'][$i], $option_strike->ask_size);
            $this->assertEquals($mocked_response['Last'][$i], $option_strike->last);
            $this->assertEquals($mocked_response['Open Interest'][$i], $option_strike->open_interest);
            $this->assertEquals($mocked_response['Volume'][$i], $option_strike->volume);
            $this->assertEquals($mocked_response['In The Money'][$i], $option_strike->in_the_money);
            $this->assertEquals($mocked_response['Intrinsic Value'][$i], $option_strike->intrinsic_value);
            $this->assertEquals($mocked_response['Extrinsic Value'][$i], $option_strike->extrinsic_value);
            $this->assertEquals($mocked_response['IV'][$i], $option_strike->implied_volatility);
            $this->assertEquals($mocked_response['Delta'][$i], $option_strike->delta);
            $this->assertEquals($mocked_response['Gamma'][$i], $option_strike->gamma);
            $this->assertEquals($mocked_response['Theta'][$i], $option_strike->theta);
            $this->assertEquals($mocked_response['Vega'][$i], $option_strike->vega);
            $this->assertEquals($mocked_response['Underlying Price'][$i],
                $option_strike->underlying_price);
        }
    }

    /**
     * Test the option_chain endpoint with human_readable=false.
     */
    public function testOptionChain_humanReadableFalse_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00060000'],
            'underlying'      => ['AAPL'],
            'expiration'      => [1686945600],
            'side'            => ['call'],
            'strike'          => [60],
            'firstTraded'     => [1617197400],
            'dte'             => [26],
            'updated'         => [1684702875],
            'bid'             => [114.1],
            'bidSize'         => [90],
            'mid'             => [115.5],
            'ask'             => [116.9],
            'askSize'         => [90],
            'last'            => [115],
            'openInterest'    => [21957],
            'volume'          => [0],
            'inTheMoney'      => [true],
            'intrinsicValue'  => [115.13],
            'extrinsicValue'  => [0.37],
            'underlyingPrice' => [175.13],
            'iv'              => [1.629],
            'delta'           => [1],
            'gamma'           => [0],
            'theta'           => [-0.009],
            'vega'            => [0]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            side: Side::CALL,
            parameters: new Parameters(use_human_readable: false)
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals($mocked_response['s'], $response->status);
    }

    /**
     * Test option_chain endpoint with invalid date range.
     */
    public function testOptionChain_invalidDateRange_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`from` date must be before `to` date');

        $this->client->options->option_chain(
            symbol: 'AAPL',
            from: '2024-01-31',
            to: '2024-01-01'
        );
    }

    /**
     * Test option_chain endpoint with invalid month.
     */
    public function testOptionChain_invalidMonth_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`month` must be between 1 and 12');

        $this->client->options->option_chain(
            symbol: 'AAPL',
            month: 13
        );
    }

    /**
     * Test option_chain endpoint with invalid numeric ranges.
     */
    public function testOptionChain_invalidNumericRanges_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be less than');

        $this->client->options->option_chain(
            symbol: 'AAPL',
            min_bid: 100.0,
            max_bid: 50.0
        );
    }
}
