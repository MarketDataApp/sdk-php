<?php

namespace MarketDataApp\Tests\Unit;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\Expirations;
use MarketDataApp\Endpoints\Responses\Options\Lookup;
use MarketDataApp\Endpoints\Responses\Options\OptionChainStrike;
use MarketDataApp\Endpoints\Responses\Options\OptionChains;
use MarketDataApp\Endpoints\Responses\Options\Quote;
use MarketDataApp\Endpoints\Responses\Options\Quotes;
use MarketDataApp\Endpoints\Responses\Options\Strikes;
use InvalidArgumentException;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Side;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the Options endpoints of the MarketDataApp.
 *
 * This class tests various scenarios of the options-related endpoints.
 */
class OptionsTest extends TestCase
{

    use MockResponses;

    /**
     * The client instance used for testing.
     *
     * @var Client
     */
    private Client $client;

    /**
     * Set up the test environment.
     *
     * This method is called before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        // Clear MARKETDATA_TOKEN environment variable to ensure empty token is used.
        // This prevents real API calls during Client construction by ensuring
        // _setup_rate_limits() skips the /user/ endpoint validation call.
        $this->clearMarketDataToken();
        
        // Use empty token for unit tests to skip validation (tests use mocks anyway)
        $token = '';
        $client = new Client($token);
        $this->client = $client;
    }

    /**
     * Test the expirations endpoint for a successful response.
     *
     * @return void
     */
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

    /**
     * Test the expirations endpoint for a successful CSV response.
     *
     * @return void
     */
    public function testExpirations_csv_success()
    {
        $mocked_response = "s, expirations, updated\r\n";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->expirations(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the expirations endpoint for a successful 'no data' response.
     *
     * @return void
     */
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

    /**
     * Test the lookup endpoint for a successful response.
     *
     * @return void
     */
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

    /**
     * Test the lookup endpoint for a successful CSV response.
     *
     * @return void
     */
    public function testLookup_csv_success()
    {
        $mocked_response = "s, optionSymbol\r\n";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->lookup('AAPL 7/28/23 $200 Call', new Parameters(format: Format::CSV));

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Lookup::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the strikes endpoint for a successful response.
     *
     * @return void
     */
    public function testStrikes_success()
    {
        $mocked_response = [
            's'          => 'ok',
            'updated'    => 1663704000,
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

    /**
     * Test the strikes endpoint for a successful CSV response.
     *
     * @return void
     */
    public function testStrikes_csv_success()
    {
        $mocked_response = "s, updated, 2023-01-20\r\n";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->strikes(
            symbol: 'AAPL',
            expiration: '2023-01-20',
            date: '2023-01-03',
            parameters: new Parameters(Format::CSV),
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Strikes::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the strikes endpoint for a successful 'no data' response.
     *
     * @return void
     */
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

    /**
     * Test the quotes endpoint for a successful response.
     *
     * @return void
     */
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
            $this->assertEquals($mocked_response['intrinsicValue'][$i], $response->quotes[$i]->intrinsic_value);
            $this->assertEquals($mocked_response['extrinsicValue'][$i], $response->quotes[$i]->extrinsic_value);
            $this->assertEquals(Carbon::parse($mocked_response['updated'][$i]), $response->quotes[$i]->updated);
        }
    }

    /**
     * Test the quotes endpoint for a successful CSV response.
     *
     * @return void
     */
    public function testQuotes_csv_success()
    {
        $mocked_response = "s, optionSymbol, ask...\r\n";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->quotes(
            option_symbol: 'AAPL250117C00150000',
            parameters: new Parameters(Format::CSV)
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the quotes endpoint for a successful 'no data' response.
     *
     * @return void
     */
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

    /**
     * Test the option_chain endpoint for a successful response.
     *
     * @return void
     */
    public function testOptionChain_success()
    {
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

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertCount(2, $response->option_chains);
        $this->assertCount(2, $response->option_chains['2023-06-16']);
        $this->assertCount(1, $response->option_chains['2023-06-17']);

        foreach (array_merge(...array_values($response->option_chains)) as $i => $option_strike) {
            $this->assertInstanceOf(OptionChainStrike::class, $option_strike);
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
     *
     * @return void
     */
    public function testOptionChain_csv_success()
    {
        $mocked_response = "s, optionSymbol, underlying...\r\n";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            side: Side::CALL,
            parameters: new Parameters(Format::CSV)
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the option_chain endpoint for a successful 'no data' response.
     *
     * @return void
     */
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

    /**
     * Test the option_chain endpoint with human-readable format.
     *
     * @return void
     */
    public function testOptionChain_humanReadable_success()
    {
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

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->option_chains);
        $this->assertCount(2, $response->option_chains['2023-06-16']);

        $option_strikes = $response->option_chains['2023-06-16'];
        for ($i = 0; $i < count($option_strikes); $i++) {
            $option_strike = $option_strikes[$i];
            $this->assertInstanceOf(OptionChainStrike::class, $option_strike);
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
     *
     * @return void
     */
    public function testOptionChain_humanReadableFalse_success()
    {
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
     * Test the expirations endpoint with human-readable format.
     *
     * @return void
     */
    public function testExpirations_humanReadable_success()
    {
        $mocked_response = [
            'Expirations' => ['2022-09-23', '2022-09-30'],
            'Date' => 1663704000
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->expirations(
            'AAPL',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(2, $response->expirations);
        $this->assertEquals(Carbon::parse($mocked_response['Date']), $response->updated);
    }

    /**
     * Test the strikes endpoint with human-readable format.
     *
     * @return void
     */
    public function testStrikes_humanReadable_success()
    {
        $mocked_response = [
            '2023-01-20' => [30.0, 35.0],
            'Date' => 1663704000
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->strikes(
            symbol: 'AAPL',
            expiration: '2023-01-20',
            date: '2023-01-03',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Strikes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals($mocked_response['2023-01-20'], $response->dates['2023-01-20']);
        $this->assertEquals(Carbon::parse($mocked_response['Date']), $response->updated);
    }

    /**
     * Test the lookup endpoint with human-readable format.
     *
     * @return void
     */
    public function testLookup_humanReadable_success()
    {
        $mocked_response = [
            'Symbol' => 'AAPL230728C00200000'
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->lookup(
            'AAPL 7/28/23 $200 Call',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Lookup::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals($mocked_response['Symbol'], $response->option_symbol);
    }

    /**
     * Test the quotes endpoint with human-readable format.
     *
     * @return void
     */
    public function testQuotes_humanReadable_success()
    {
        $mocked_response = [
            'Symbol' => ['AAPL281215C00400000'],
            'Underlying' => ['AAPL'],
            'Expiration Date' => [1840579200],
            'Option Side' => ['call'],
            'Strike' => [400],
            'First Traded' => [1617197400],
            'Days To Expiration' => [100],
            'Date' => [1684702875],
            'Bid' => [114.1],
            'Bid Size' => [90],
            'Mid' => [115.5],
            'Ask' => [116.9],
            'Ask Size' => [90],
            'Last' => [115],
            'Open Interest' => [21957],
            'Volume' => [0],
            'In The Money' => [true],
            'Intrinsic Value' => [115.13],
            'Extrinsic Value' => [0.37],
            'Underlying Price' => [175.13],
            'IV' => [1.629],
            'Delta' => [1],
            'Gamma' => [0],
            'Theta' => [-0.009],
            'Vega' => [0]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->quotes(
            option_symbol: 'AAPL281215C00400000',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->quotes);
        $this->assertInstanceOf(Quote::class, $response->quotes[0]);
        $this->assertEquals($mocked_response['Symbol'][0], $response->quotes[0]->option_symbol);
        $this->assertEquals($mocked_response['Ask'][0], $response->quotes[0]->ask);
        $this->assertEquals($mocked_response['Ask Size'][0], $response->quotes[0]->ask_size);
        $this->assertEquals($mocked_response['Bid'][0], $response->quotes[0]->bid);
        $this->assertEquals($mocked_response['Bid Size'][0], $response->quotes[0]->bid_size);
        $this->assertEquals($mocked_response['Mid'][0], $response->quotes[0]->mid);
        $this->assertEquals($mocked_response['Last'][0], $response->quotes[0]->last);
        $this->assertEquals($mocked_response['Volume'][0], $response->quotes[0]->volume);
        $this->assertEquals($mocked_response['Open Interest'][0], $response->quotes[0]->open_interest);
        $this->assertEquals($mocked_response['Underlying Price'][0], $response->quotes[0]->underlying_price);
        $this->assertEquals($mocked_response['In The Money'][0], $response->quotes[0]->in_the_money);
        $this->assertEquals($mocked_response['Intrinsic Value'][0], $response->quotes[0]->intrinsic_value);
        $this->assertEquals($mocked_response['Extrinsic Value'][0], $response->quotes[0]->extrinsic_value);
        $this->assertEquals($mocked_response['IV'][0], $response->quotes[0]->implied_volatility);
        $this->assertEquals($mocked_response['Delta'][0], $response->quotes[0]->delta);
        $this->assertEquals($mocked_response['Gamma'][0], $response->quotes[0]->gamma);
        $this->assertEquals($mocked_response['Theta'][0], $response->quotes[0]->theta);
        $this->assertEquals($mocked_response['Vega'][0], $response->quotes[0]->vega);
        $this->assertEquals(Carbon::parse($mocked_response['Date'][0]), $response->quotes[0]->updated);
    }

    /**
     * Test that date_format parameter can be used with CSV format for options.
     *
     * @return void
     */
    public function testParameters_dateFormat_withCsv_success(): void
    {
        $mocked_response = "s, symbol, ask, bid";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->expirations(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertTrue($response->isCsv());
    }

    /**
     * Test that date_format parameter with JSON format throws InvalidArgumentException.
     *
     * @return void
     */
    public function testParameters_dateFormat_withJson_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('date_format parameter can only be used with CSV or HTML format');

        new Parameters(format: Format::JSON, date_format: DateFormat::TIMESTAMP);
    }

    /**
     * Test options quotes endpoint with CSV format and dateformat=unix.
     *
     * @return void
     */
    public function testQuotes_csv_withDateFormat_unix(): void
    {
        $mocked_response = "s, symbol, ask, bid";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->quotes(
            option_symbol: 'AAPL250117C00150000',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertTrue($response->isCsv());
    }

    /**
     * Test options quotes endpoint with CSV format and dateformat=spreadsheet.
     *
     * @return void
     */
    public function testQuotes_csv_withDateFormat_spreadsheet(): void
    {
        $mocked_response = "s, symbol, ask, bid";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->quotes(
            option_symbol: 'AAPL250117C00150000',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::SPREADSHEET)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertTrue($response->isCsv());
    }

    /**
     * Test expirations endpoint with invalid strike (zero).
     */
    public function testExpirations_invalidStrike_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a positive integer');

        $this->client->options->expirations('AAPL', strike: 0);
    }

    /**
     * Test lookup endpoint with empty input.
     */
    public function testLookup_emptyInput_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-empty string');

        $this->client->options->lookup('');
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
     * Test quotes endpoint with invalid date range.
     */
    public function testQuotes_invalidDateRange_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`from` date must be before `to` date');

        $this->client->options->quotes(
            option_symbol: 'AAPL250117C00150000',
            from: '2024-01-31',
            to: '2024-01-01'
        );
    }
}
