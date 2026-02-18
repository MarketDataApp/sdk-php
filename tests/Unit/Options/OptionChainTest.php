<?php

namespace MarketDataApp\Tests\Unit\Options;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\OptionQuote;
use MarketDataApp\Endpoints\Responses\Options\OptionChains;
use MarketDataApp\Endpoints\Responses\Options\Quotes;
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

    /**
     * Test getAllQuotes returns a flat array of all quotes.
     */
    public function testOptionChain_getAllQuotes(): void
    {
        // Mock response: NOT from real API output (synthetic/test data with calls and puts)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00060000', 'AAPL230616P00060000', 'AAPL230617C00065000'],
            'underlying'      => ['AAPL', 'AAPL', 'AAPL'],
            'expiration'      => [1686945600, 1686945600, 1687045600],
            'side'            => ['call', 'put', 'call'],
            'strike'          => [60, 60, 65],
            'firstTraded'     => [1617197400, 1617197400, 1616592600],
            'dte'             => [26, 26, 33],
            'updated'         => [1684702875, 1684702875, 1684702876],
            'bid'             => [114.1, 0.05, 108.6],
            'bidSize'         => [90, 100, 90],
            'mid'             => [115.5, 0.06, 110.38],
            'ask'             => [116.9, 0.07, 112.15],
            'askSize'         => [90, 100, 90],
            'last'            => [115, 0.05, 107.82],
            'openInterest'    => [21957, 5000, 3012],
            'volume'          => [0, 100, 0],
            'inTheMoney'      => [true, false, true],
            'intrinsicValue'  => [115.13, 0, 110.13],
            'extrinsicValue'  => [0.37, 0.05, 0.25],
            'underlyingPrice' => [175.13, 175.13, 175.13],
            'iv'              => [1.629, 0.5, 1.923],
            'delta'           => [1, -0.01, 1],
            'gamma'           => [0, 0.001, 0],
            'theta'           => [-0.009, -0.001, -0.009],
            'vega'            => [0, 0.001, 0]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(symbol: 'AAPL');

        $allQuotes = $response->getAllQuotes();
        $this->assertCount(3, $allQuotes);
        $this->assertContainsOnlyInstancesOf(OptionQuote::class, $allQuotes);
        $this->assertEquals('AAPL230616C00060000', $allQuotes[0]->option_symbol);
        $this->assertEquals('AAPL230616P00060000', $allQuotes[1]->option_symbol);
        $this->assertEquals('AAPL230617C00065000', $allQuotes[2]->option_symbol);
    }

    /**
     * Test getExpirationDates returns all unique expiration dates.
     */
    public function testOptionChain_getExpirationDates(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00060000', 'AAPL230617C00065000'],
            'underlying'      => ['AAPL', 'AAPL'],
            'expiration'      => [1686945600, 1687045600],
            'side'            => ['call', 'call'],
            'strike'          => [60, 65],
            'firstTraded'     => [1617197400, 1616592600],
            'dte'             => [26, 33],
            'updated'         => [1684702875, 1684702876],
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
            'vega'            => [0, 0]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(symbol: 'AAPL');

        $expirationDates = $response->getExpirationDates();
        $this->assertCount(2, $expirationDates);
        $this->assertEquals(['2023-06-16', '2023-06-17'], $expirationDates);
    }

    /**
     * Test getQuotesByExpiration returns quotes for a specific date.
     */
    public function testOptionChain_getQuotesByExpiration(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00060000', 'AAPL230616C00065000', 'AAPL230617C00070000'],
            'underlying'      => ['AAPL', 'AAPL', 'AAPL'],
            'expiration'      => [1686945600, 1686945600, 1687045600],
            'side'            => ['call', 'call', 'call'],
            'strike'          => [60, 65, 70],
            'firstTraded'     => [1617197400, 1617197400, 1616592600],
            'dte'             => [26, 26, 33],
            'updated'         => [1684702875, 1684702875, 1684702876],
            'bid'             => [114.1, 108.6, 100.0],
            'bidSize'         => [90, 90, 90],
            'mid'             => [115.5, 110.38, 101.0],
            'ask'             => [116.9, 112.15, 102.0],
            'askSize'         => [90, 90, 90],
            'last'            => [115, 107.82, 100.5],
            'openInterest'    => [21957, 3012, 5000],
            'volume'          => [0, 0, 100],
            'inTheMoney'      => [true, true, true],
            'intrinsicValue'  => [115.13, 110.13, 105.13],
            'extrinsicValue'  => [0.37, 0.25, 0.13],
            'underlyingPrice' => [175.13, 175.13, 175.13],
            'iv'              => [1.629, 1.923, 1.5],
            'delta'           => [1, 1, 1],
            'gamma'           => [0, 0, 0],
            'theta'           => [-0.009, -0.009, -0.009],
            'vega'            => [0, 0, 0]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(symbol: 'AAPL');

        // Test getting quotes for existing date
        $quotesFor0616 = $response->getQuotesByExpiration('2023-06-16');
        $this->assertCount(2, $quotesFor0616);
        $this->assertEquals('AAPL230616C00060000', $quotesFor0616[0]->option_symbol);
        $this->assertEquals('AAPL230616C00065000', $quotesFor0616[1]->option_symbol);

        // Test getting quotes for non-existent date returns empty array
        $quotesForMissing = $response->getQuotesByExpiration('2023-06-20');
        $this->assertEmpty($quotesForMissing);
    }

    /**
     * Test count returns total number of quotes.
     */
    public function testOptionChain_count(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00060000', 'AAPL230616C00065000', 'AAPL230617C00070000'],
            'underlying'      => ['AAPL', 'AAPL', 'AAPL'],
            'expiration'      => [1686945600, 1686945600, 1687045600],
            'side'            => ['call', 'call', 'call'],
            'strike'          => [60, 65, 70],
            'firstTraded'     => [1617197400, 1617197400, 1616592600],
            'dte'             => [26, 26, 33],
            'updated'         => [1684702875, 1684702875, 1684702876],
            'bid'             => [114.1, 108.6, 100.0],
            'bidSize'         => [90, 90, 90],
            'mid'             => [115.5, 110.38, 101.0],
            'ask'             => [116.9, 112.15, 102.0],
            'askSize'         => [90, 90, 90],
            'last'            => [115, 107.82, 100.5],
            'openInterest'    => [21957, 3012, 5000],
            'volume'          => [0, 0, 100],
            'inTheMoney'      => [true, true, true],
            'intrinsicValue'  => [115.13, 110.13, 105.13],
            'extrinsicValue'  => [0.37, 0.25, 0.13],
            'underlyingPrice' => [175.13, 175.13, 175.13],
            'iv'              => [1.629, 1.923, 1.5],
            'delta'           => [1, 1, 1],
            'gamma'           => [0, 0, 0],
            'theta'           => [-0.009, -0.009, -0.009],
            'vega'            => [0, 0, 0]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(symbol: 'AAPL');

        $this->assertEquals(3, $response->count());
    }

    /**
     * Test getCalls returns only call options.
     */
    public function testOptionChain_getCalls(): void
    {
        // Mock response: NOT from real API output (synthetic/test data with calls and puts)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00060000', 'AAPL230616P00060000', 'AAPL230616C00065000'],
            'underlying'      => ['AAPL', 'AAPL', 'AAPL'],
            'expiration'      => [1686945600, 1686945600, 1686945600],
            'side'            => ['call', 'put', 'call'],
            'strike'          => [60, 60, 65],
            'firstTraded'     => [1617197400, 1617197400, 1617197400],
            'dte'             => [26, 26, 26],
            'updated'         => [1684702875, 1684702875, 1684702875],
            'bid'             => [114.1, 0.05, 108.6],
            'bidSize'         => [90, 100, 90],
            'mid'             => [115.5, 0.06, 110.38],
            'ask'             => [116.9, 0.07, 112.15],
            'askSize'         => [90, 100, 90],
            'last'            => [115, 0.05, 107.82],
            'openInterest'    => [21957, 5000, 3012],
            'volume'          => [0, 100, 0],
            'inTheMoney'      => [true, false, true],
            'intrinsicValue'  => [115.13, 0, 110.13],
            'extrinsicValue'  => [0.37, 0.05, 0.25],
            'underlyingPrice' => [175.13, 175.13, 175.13],
            'iv'              => [1.629, 0.5, 1.923],
            'delta'           => [1, -0.01, 1],
            'gamma'           => [0, 0.001, 0],
            'theta'           => [-0.009, -0.001, -0.009],
            'vega'            => [0, 0.001, 0]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(symbol: 'AAPL');

        $calls = $response->getCalls();
        $this->assertCount(2, $calls);
        foreach ($calls as $call) {
            $this->assertEquals(Side::CALL, $call->side);
        }
    }

    /**
     * Test getPuts returns only put options.
     */
    public function testOptionChain_getPuts(): void
    {
        // Mock response: NOT from real API output (synthetic/test data with calls and puts)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00060000', 'AAPL230616P00060000', 'AAPL230616P00065000'],
            'underlying'      => ['AAPL', 'AAPL', 'AAPL'],
            'expiration'      => [1686945600, 1686945600, 1686945600],
            'side'            => ['call', 'put', 'put'],
            'strike'          => [60, 60, 65],
            'firstTraded'     => [1617197400, 1617197400, 1617197400],
            'dte'             => [26, 26, 26],
            'updated'         => [1684702875, 1684702875, 1684702875],
            'bid'             => [114.1, 0.05, 0.10],
            'bidSize'         => [90, 100, 100],
            'mid'             => [115.5, 0.06, 0.12],
            'ask'             => [116.9, 0.07, 0.14],
            'askSize'         => [90, 100, 100],
            'last'            => [115, 0.05, 0.11],
            'openInterest'    => [21957, 5000, 4000],
            'volume'          => [0, 100, 50],
            'inTheMoney'      => [true, false, false],
            'intrinsicValue'  => [115.13, 0, 0],
            'extrinsicValue'  => [0.37, 0.05, 0.11],
            'underlyingPrice' => [175.13, 175.13, 175.13],
            'iv'              => [1.629, 0.5, 0.6],
            'delta'           => [1, -0.01, -0.02],
            'gamma'           => [0, 0.001, 0.001],
            'theta'           => [-0.009, -0.001, -0.001],
            'vega'            => [0, 0.001, 0.001]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(symbol: 'AAPL');

        $puts = $response->getPuts();
        $this->assertCount(2, $puts);
        foreach ($puts as $put) {
            $this->assertEquals(Side::PUT, $put->side);
        }
    }

    /**
     * Test getByStrike returns quotes for a specific strike price.
     */
    public function testOptionChain_getByStrike(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00060000', 'AAPL230616P00060000', 'AAPL230616C00065000'],
            'underlying'      => ['AAPL', 'AAPL', 'AAPL'],
            'expiration'      => [1686945600, 1686945600, 1686945600],
            'side'            => ['call', 'put', 'call'],
            'strike'          => [60, 60, 65],
            'firstTraded'     => [1617197400, 1617197400, 1617197400],
            'dte'             => [26, 26, 26],
            'updated'         => [1684702875, 1684702875, 1684702875],
            'bid'             => [114.1, 0.05, 108.6],
            'bidSize'         => [90, 100, 90],
            'mid'             => [115.5, 0.06, 110.38],
            'ask'             => [116.9, 0.07, 112.15],
            'askSize'         => [90, 100, 90],
            'last'            => [115, 0.05, 107.82],
            'openInterest'    => [21957, 5000, 3012],
            'volume'          => [0, 100, 0],
            'inTheMoney'      => [true, false, true],
            'intrinsicValue'  => [115.13, 0, 110.13],
            'extrinsicValue'  => [0.37, 0.05, 0.25],
            'underlyingPrice' => [175.13, 175.13, 175.13],
            'iv'              => [1.629, 0.5, 1.923],
            'delta'           => [1, -0.01, 1],
            'gamma'           => [0, 0.001, 0],
            'theta'           => [-0.009, -0.001, -0.009],
            'vega'            => [0, 0.001, 0]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(symbol: 'AAPL');

        $quotesAt60 = $response->getByStrike(60);
        $this->assertCount(2, $quotesAt60);
        foreach ($quotesAt60 as $quote) {
            $this->assertEquals(60, $quote->strike);
        }

        $quotesAt65 = $response->getByStrike(65);
        $this->assertCount(1, $quotesAt65);
    }

    /**
     * Test getStrikes returns all unique strike prices sorted ascending.
     */
    public function testOptionChain_getStrikes(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00070000', 'AAPL230616P00060000', 'AAPL230616C00065000', 'AAPL230616P00070000'],
            'underlying'      => ['AAPL', 'AAPL', 'AAPL', 'AAPL'],
            'expiration'      => [1686945600, 1686945600, 1686945600, 1686945600],
            'side'            => ['call', 'put', 'call', 'put'],
            'strike'          => [70, 60, 65, 70],
            'firstTraded'     => [1617197400, 1617197400, 1617197400, 1617197400],
            'dte'             => [26, 26, 26, 26],
            'updated'         => [1684702875, 1684702875, 1684702875, 1684702875],
            'bid'             => [100.0, 0.05, 108.6, 0.10],
            'bidSize'         => [90, 100, 90, 100],
            'mid'             => [101.0, 0.06, 110.38, 0.12],
            'ask'             => [102.0, 0.07, 112.15, 0.14],
            'askSize'         => [90, 100, 90, 100],
            'last'            => [100.5, 0.05, 107.82, 0.11],
            'openInterest'    => [5000, 5000, 3012, 4000],
            'volume'          => [100, 100, 0, 50],
            'inTheMoney'      => [true, false, true, false],
            'intrinsicValue'  => [105.13, 0, 110.13, 0],
            'extrinsicValue'  => [0.13, 0.05, 0.25, 0.11],
            'underlyingPrice' => [175.13, 175.13, 175.13, 175.13],
            'iv'              => [1.5, 0.5, 1.923, 0.6],
            'delta'           => [1, -0.01, 1, -0.02],
            'gamma'           => [0, 0.001, 0, 0.001],
            'theta'           => [-0.009, -0.001, -0.009, -0.001],
            'vega'            => [0, 0.001, 0, 0.001]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(symbol: 'AAPL');

        $strikes = $response->getStrikes();
        $this->assertCount(3, $strikes);
        $this->assertEquals([60, 65, 70], $strikes);
    }

    /**
     * Test toQuotes converts option chain to a Quotes object.
     */
    public function testOptionChain_toQuotes(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00060000', 'AAPL230617C00065000'],
            'underlying'      => ['AAPL', 'AAPL'],
            'expiration'      => [1686945600, 1687045600],
            'side'            => ['call', 'call'],
            'strike'          => [60, 65],
            'firstTraded'     => [1617197400, 1616592600],
            'dte'             => [26, 33],
            'updated'         => [1684702875, 1684702876],
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
            'vega'            => [0, 0]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(symbol: 'AAPL');

        $quotes = $response->toQuotes();
        $this->assertInstanceOf(Quotes::class, $quotes);
        $this->assertEquals('ok', $quotes->status);
        $this->assertCount(2, $quotes->quotes);
        $this->assertEquals('AAPL230616C00060000', $quotes->quotes[0]->option_symbol);
        $this->assertEquals('AAPL230617C00065000', $quotes->quotes[1]->option_symbol);
    }

    /**
     * Test toQuotes preserves next_time and prev_time from no_data response.
     */
    public function testOptionChain_toQuotes_withNoData(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'        => 'no_data',
            'nextTime' => 1663704000,
            'prevTime' => 1663705000
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain('AAPL');

        $quotes = $response->toQuotes();
        $this->assertInstanceOf(Quotes::class, $quotes);
        $this->assertEquals('no_data', $quotes->status);
        $this->assertEmpty($quotes->quotes);
        $this->assertEquals(Carbon::parse(1663704000), $quotes->next_time);
        $this->assertEquals(Carbon::parse(1663705000), $quotes->prev_time);
    }

    /**
     * Test option_chain endpoint with weekly=false parameter.
     */
    public function testOptionChain_withWeeklyFalse_success(): void
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
            weekly: false
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status);
    }

    /**
     * Test option_chain endpoint with monthly=false parameter.
     */
    public function testOptionChain_withMonthlyFalse_success(): void
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
            monthly: false
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status);
    }

    /**
     * Test option_chain endpoint with quarterly=false parameter.
     */
    public function testOptionChain_withQuarterlyFalse_success(): void
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
            quarterly: false
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status);
    }

    /**
     * Test option_chain endpoint with max_bid_ask_spread parameter.
     */
    public function testOptionChain_withMaxBidAskSpread_success(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        // Options with tight bid-ask spreads (all <= 0.30)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00060000', 'AAPL230616C00065000'],
            'underlying'      => ['AAPL', 'AAPL'],
            'expiration'      => [1686945600, 1686945600],
            'side'            => ['call', 'call'],
            'strike'          => [60, 65],
            'firstTraded'     => [1617197400, 1617197400],
            'dte'             => [26, 26],
            'updated'         => [1684702875, 1684702875],
            'bid'             => [114.10, 108.60],
            'bidSize'         => [90, 90],
            'mid'             => [114.20, 108.75],
            'ask'             => [114.30, 108.90],  // Spreads: 0.20, 0.30
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
            'vega'            => [0, 0]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            max_bid_ask_spread: 0.30
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->option_chains);
        $this->assertCount(2, $response->option_chains['2023-06-16']);
    }

    /**
     * Test option_chain endpoint with am=true parameter for AM-settled index options.
     */
    public function testOptionChain_withAmTrue_success(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        // SPX AM-settled options (standard SPX, not SPXW)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['SPX230616C06000000'],
            'underlying'      => ['SPX'],
            'expiration'      => [1686945600],
            'side'            => ['call'],
            'strike'          => [6000],
            'firstTraded'     => [1617197400],
            'dte'             => [26],
            'updated'         => [1684702875],
            'bid'             => [100.00],
            'bidSize'         => [50],
            'mid'             => [102.50],
            'ask'             => [105.00],
            'askSize'         => [50],
            'last'            => [101.00],
            'openInterest'    => [5000],
            'volume'          => [100],
            'inTheMoney'      => [false],
            'intrinsicValue'  => [0],
            'extrinsicValue'  => [102.50],
            'underlyingPrice' => [5800],
            'iv'              => [0.20],
            'delta'           => [0.45],
            'gamma'           => [0.001],
            'theta'           => [-1.50],
            'vega'            => [5.00]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(
            symbol: 'SPX',
            am: true
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->option_chains);
    }

    /**
     * Test option_chain endpoint with pm=true parameter for PM-settled index options.
     */
    public function testOptionChain_withPmTrue_success(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        // SPXW PM-settled options (weekly SPX)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['SPXW230616C06000000'],
            'underlying'      => ['SPX'],
            'expiration'      => [1686945600],
            'side'            => ['call'],
            'strike'          => [6000],
            'firstTraded'     => [1617197400],
            'dte'             => [26],
            'updated'         => [1684702875],
            'bid'             => [100.00],
            'bidSize'         => [50],
            'mid'             => [102.50],
            'ask'             => [105.00],
            'askSize'         => [50],
            'last'            => [101.00],
            'openInterest'    => [5000],
            'volume'          => [100],
            'inTheMoney'      => [false],
            'intrinsicValue'  => [0],
            'extrinsicValue'  => [102.50],
            'underlyingPrice' => [5800],
            'iv'              => [0.20],
            'delta'           => [0.45],
            'gamma'           => [0.001],
            'theta'           => [-1.50],
            'vega'            => [5.00]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(
            symbol: 'SPX',
            pm: true
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->option_chains);
    }

    /**
     * Test option_chain endpoint with am=false parameter.
     */
    public function testOptionChain_withAmFalse_success(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['SPXW230616C06000000'],
            'underlying'      => ['SPX'],
            'expiration'      => [1686945600],
            'side'            => ['call'],
            'strike'          => [6000],
            'firstTraded'     => [1617197400],
            'dte'             => [26],
            'updated'         => [1684702875],
            'bid'             => [100.00],
            'bidSize'         => [50],
            'mid'             => [102.50],
            'ask'             => [105.00],
            'askSize'         => [50],
            'last'            => [101.00],
            'openInterest'    => [5000],
            'volume'          => [100],
            'inTheMoney'      => [false],
            'intrinsicValue'  => [0],
            'extrinsicValue'  => [102.50],
            'underlyingPrice' => [5800],
            'iv'              => [0.20],
            'delta'           => [0.45],
            'gamma'           => [0.001],
            'theta'           => [-1.50],
            'vega'            => [5.00]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(
            symbol: 'SPX',
            am: false
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status);
    }

    /**
     * Test option_chain endpoint with pm=false parameter.
     */
    public function testOptionChain_withPmFalse_success(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['SPX230616C06000000'],
            'underlying'      => ['SPX'],
            'expiration'      => [1686945600],
            'side'            => ['call'],
            'strike'          => [6000],
            'firstTraded'     => [1617197400],
            'dte'             => [26],
            'updated'         => [1684702875],
            'bid'             => [100.00],
            'bidSize'         => [50],
            'mid'             => [102.50],
            'ask'             => [105.00],
            'askSize'         => [50],
            'last'            => [101.00],
            'openInterest'    => [5000],
            'volume'          => [100],
            'inTheMoney'      => [false],
            'intrinsicValue'  => [0],
            'extrinsicValue'  => [102.50],
            'underlyingPrice' => [5800],
            'iv'              => [0.20],
            'delta'           => [0.45],
            'gamma'           => [0.001],
            'theta'           => [-1.50],
            'vega'            => [5.00]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(
            symbol: 'SPX',
            pm: false
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status);
    }

    /**
     * Test that option_chain properties are accessible for CSV responses (BUG-013 fix).
     *
     * CSV responses trigger an early return in the constructor. Properties should
     * have default values to prevent "uninitialized property" errors.
     */
    public function testOptionChain_csv_propertiesAccessible(): void
    {
        // Mock response: NOT from real API output (uses synthetic CSV data)
        $csvResponse = "optionSymbol,underlying,expiration\nAAPL230616C00060000,AAPL,1686945600";
        $this->setMockResponses([new Response(200, [], $csvResponse)]);

        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        // These should NOT throw "uninitialized property" errors
        $this->assertEquals('no_data', $response->status);
        $this->assertIsArray($response->option_chains);
        $this->assertCount(0, $response->option_chains);
        $this->assertNull($response->next_time);
        $this->assertNull($response->prev_time);
    }

    /**
     * Test that option_chain handles missing optional fields without crashing (BUG-031 fix).
     *
     * When the API omits optional fields (last, iv, delta, gamma, theta, vega) from
     * regular JSON format responses, the response class should handle this gracefully
     * using null guards instead of causing PHP errors.
     */
    public function testOptionChain_missingOptionalFields_handledGracefully(): void
    {
        // Mock response: NOT from real API output (synthetic/test data with optional fields omitted)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL250117C00150000'],
            'underlying'      => ['AAPL'],
            'expiration'      => [1737072000],
            'side'            => ['call'],
            'strike'          => [150.0],
            'firstTraded'     => [1617197400],
            'dte'             => [30],
            'ask'             => [5.50],
            'askSize'         => [10],
            'bid'             => [5.20],
            'bidSize'         => [12],
            'mid'             => [5.35],
            'volume'          => [100],
            'openInterest'    => [500],
            'underlyingPrice' => [150.00],
            'inTheMoney'      => [true],
            'intrinsicValue'  => [1.00],
            'extrinsicValue'  => [4.35],
            'updated'         => [1617197400],
            // Optional fields intentionally omitted: last, iv, delta, gamma, theta, vega
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->option_chain(symbol: 'AAPL');

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->option_chains);

        $quote = $response->getAllQuotes()[0];
        $this->assertNull($quote->last);
        $this->assertNull($quote->implied_volatility);
        $this->assertNull($quote->delta);
        $this->assertNull($quote->gamma);
        $this->assertNull($quote->theta);
        $this->assertNull($quote->vega);
    }

    /**
     * Test that option_chain properties are accessible for no_data responses without next/prev times (BUG-013 fix).
     *
     * Some no_data responses may not include nextTime/prevTime fields.
     */
    public function testOptionChain_noData_withoutTimes_propertiesAccessible(): void
    {
        // Mock response: NOT from real API output (uses synthetic no_data response)
        $noDataResponse = ['s' => 'no_data'];
        $this->setMockResponses([new Response(200, [], json_encode($noDataResponse))]);

        $response = $this->client->options->option_chain('INVALID');

        // These should NOT throw "uninitialized property" errors
        $this->assertEquals('no_data', $response->status);
        $this->assertIsArray($response->option_chains);
        $this->assertCount(0, $response->option_chains);
        $this->assertNull($response->next_time);
        $this->assertNull($response->prev_time);
    }
}
