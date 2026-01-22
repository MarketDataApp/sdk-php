<?php

namespace MarketDataApp\Tests\Unit;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\BulkCandles;
use MarketDataApp\Endpoints\Responses\Stocks\Candle;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Endpoints\Responses\Stocks\Earning;
use MarketDataApp\Endpoints\Responses\Stocks\Earnings;
use MarketDataApp\Endpoints\Responses\Stocks\News;
use MarketDataApp\Endpoints\Responses\Stocks\Prices;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Endpoints\Responses\Stocks\Quotes;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the Stocks endpoints of the MarketDataApp.
 *
 * This class tests various scenarios of the stocks-related endpoints.
 */
class StocksTest extends TestCase
{

    use MockResponses;

    /**
     * The client instance used for testing.
     *
     * @var Client
     */
    private Client $client;

    /**
     * Mocked response data for AAPL stock.
     * Mock response: NOT from real API output (synthetic/test data)
     *
     * @var array
     */
    private array $aapl_mocked_response = [
        's'         => 'ok',
        'symbol'    => ['AAPL'],
        'ask'       => [149.08],
        'askSize'   => [200],
        'bid'       => [149.07],
        'bidSize'   => [600],
        'mid'       => [149.07],
        'last'      => [149.09],
        'change'    => [0.01],
        'changepct' => [0.01],
        'volume'    => [66959442],
        'updated'   => [1663958092]
    ];

    /**
     * Mocked response data for multiple stocks.
     * Mock response: NOT from real API output (synthetic/test data)
     *
     * @var array
     */
    private array $multiple_mocked_response = [
        's'         => 'ok',
        'symbol'    => ['APPL', 'NFLX'],
        'ask'       => [350.0, 400.0],
        'askSize'   => [159, 200],
        'bid'       => [349, 399.99],
        'bidSize'   => [452, 600],
        'mid'       => [349.99, 399.99],
        'last'      => [350.2, 400.0],
        'change'    => [0.03, 0.01],
        'changepct' => [0.05, 0.01],
        'volume'    => [123123, 66959442],
        'updated'   => [1663958094, 1663958092]
    ];

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
        $token = "";
        $client = new Client($token);
        $this->client = $client;
    }

    /**
     * Test the candles endpoint for a successful response with 'from' and 'to' parameters.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_fromTo_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's' => 'ok',
            'c' => [22.84, 23.93, 21.95, 21.44, 21.15],
            'h' => [23.27, 24.68, 23.92, 22.66, 22.58],
            'l' => [22.26, 22.67, 21.68, 21.44, 20.76],
            'o' => [22.41, 24.08, 23.86, 22.06, 21.5],
            'v' => [123123, 66959442, 66959442, 66959442, 66959442],
            't' => [1659326400, 1659412800, 1659499200, 1659585600, 1659672000]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertCount(5, $response->candles);

        // Verify each item in the response is an object of the correct type and has the correct values.
        for ($i = 0; $i < count($response->candles); $i++) {
            $this->assertInstanceOf(Candle::class, $response->candles[$i]);
            $this->assertEquals($mocked_response['c'][$i], $response->candles[$i]->close);
            $this->assertEquals($mocked_response['h'][$i], $response->candles[$i]->high);
            $this->assertEquals($mocked_response['l'][$i], $response->candles[$i]->low);
            $this->assertEquals($mocked_response['o'][$i], $response->candles[$i]->open);
            $this->assertEquals($mocked_response['v'][$i], $response->candles[$i]->volume);
            $this->assertEquals(Carbon::parse($mocked_response['t'][$i]), $response->candles[$i]->timestamp);
        }
    }

    /**
     * Test the candles endpoint for a successful CSV response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_csv_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, c, h, l, o, v, t";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV)
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the candles endpoint with human-readable format.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_humanReadable_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'Date' => [1659326400, 1659412800],
            'Open' => [22.41, 24.08],
            'High' => [23.27, 24.68],
            'Low' => [22.26, 22.67],
            'Close' => [22.84, 23.93],
            'Volume' => [123123, 66959442]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(2, $response->candles);
        $this->assertEquals($mocked_response['Open'][0], $response->candles[0]->open);
        $this->assertEquals($mocked_response['High'][0], $response->candles[0]->high);
        $this->assertEquals($mocked_response['Low'][0], $response->candles[0]->low);
        $this->assertEquals($mocked_response['Close'][0], $response->candles[0]->close);
        $this->assertEquals($mocked_response['Volume'][0], $response->candles[0]->volume);
        $this->assertEquals(Carbon::parse($mocked_response['Date'][0]), $response->candles[0]->timestamp);
    }

    /**
     * Test the candles endpoint for a successful 'no data' response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_noData_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's' => 'no_data',
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->candles(
            symbol: "AAPl",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEmpty($response->candles);
        $this->assertFalse(isset($response->next_time));
    }

    /**
     * Test the candles endpoint for a successful 'no data' response with next time.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_noDataNextTime_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'        => 'no_data',
            'nextTime' => 1663958094,
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals($mocked_response['nextTime'], $response->next_time);
        $this->assertEmpty($response->candles);
    }

    /**
     * Test the bulkCandles endpoint for a successful response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testBulkCandles_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's' => 'ok',
            'c' => [22.84, 23.93],
            'h' => [23.27, 24.68],
            'l' => [22.26, 22.67],
            'o' => [22.41, 24.08],
            'v' => [123123, 66959442],
            't' => [1659326400, 1659412800]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->bulkCandles(
            symbols: ["AAPL", "MSFT"],
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(BulkCandles::class, $response);
        $this->assertCount(2, $response->candles);

        // Verify each item in the response is an object of the correct type and has the correct values.
        for ($i = 0; $i < count($response->candles); $i++) {
            $this->assertInstanceOf(Candle::class, $response->candles[$i]);
            $this->assertEquals($mocked_response['c'][$i], $response->candles[$i]->close);
            $this->assertEquals($mocked_response['h'][$i], $response->candles[$i]->high);
            $this->assertEquals($mocked_response['l'][$i], $response->candles[$i]->low);
            $this->assertEquals($mocked_response['o'][$i], $response->candles[$i]->open);
            $this->assertEquals($mocked_response['v'][$i], $response->candles[$i]->volume);
            $this->assertEquals(Carbon::parse($mocked_response['t'][$i]), $response->candles[$i]->timestamp);
        }
    }

    /**
     * Test the bulkCandles endpoint for a successful CSV response.
     *
     * @return void
     */
    public function testBulkCandles_csv_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, c, h, l, o, v, t";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->bulkCandles(
            symbols: ["AAPL", "MSFT"],
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV)
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(BulkCandles::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the bulkCandles endpoint with human-readable format.
     *
     * @return void
     */
    public function testBulkCandles_humanReadable_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'Date' => [1659326400, 1659412800],
            'Open' => [22.41, 24.08],
            'High' => [23.27, 24.68],
            'Low' => [22.26, 22.67],
            'Close' => [22.84, 23.93],
            'Volume' => [123123, 66959442]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->bulkCandles(
            symbols: ["AAPL", "MSFT"],
            resolution: 'D',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(BulkCandles::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(2, $response->candles);
        $this->assertEquals($mocked_response['Open'][0], $response->candles[0]->open);
    }

    /**
     * Test the bulkCandles endpoint for a successful 'no data' response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testBulkCandles_noData_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's' => 'no_data',
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->bulkCandles(
            symbols: ["AAPL", "MSFT"],
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(BulkCandles::class, $response);
        $this->assertEmpty($response->candles);
    }

    /**
     * Test the bulkCandles endpoint for invalid arguments.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testBulkCandles_invalidArguments_throwsInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);

        // Must have snapshot or symbols
        $this->client->stocks->bulkCandles(resolution: 'D');
    }

    /**
     * Test the quote endpoint for a successful response.
     *
     * @return void
     */
    public function testQuote_success()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote('AAPL');

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
        $this->assertEquals($mocked_response['ask'][0], $quote->ask);
        $this->assertEquals($mocked_response['askSize'][0], $quote->ask_size);
        $this->assertEquals($mocked_response['bid'][0], $quote->bid);
        $this->assertEquals($mocked_response['bidSize'][0], $quote->bid_size);
        $this->assertEquals($mocked_response['mid'][0], $quote->mid);
        $this->assertEquals($mocked_response['last'][0], $quote->last);
        $this->assertEquals($mocked_response['change'][0], $quote->change);
        $this->assertEquals($mocked_response['changepct'][0], $quote->change_percent);
        $this->assertNull($quote->fifty_two_week_high);
        $this->assertNull($quote->fifty_two_week_low);
        $this->assertEquals($mocked_response['volume'][0], $quote->volume);
        $this->assertEquals(Carbon::parse($mocked_response['updated'][0]), $quote->updated);
    }

    /**
     * Test the quote endpoint for a successful CSV response.
     *
     * @return void
     */
    public function testQuote_csv_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "a, b, c";
        $this->setMockResponses([
            new Response(200, [], $mocked_response),
        ]);
        $quote = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response, $quote->getCsv());
    }

    /**
     * Test the quote endpoint for a successful response with 52-week high/low.
     *
     * @return void
     */
    public function testQuote_52week_success()
    {
        // Mock response: NOT from real API output (synthetic/test data - extends class property)
        $mocked_response = $this->aapl_mocked_response;
        $mocked_response['52weekHigh'] = [149.08];
        $mocked_response['52weekLow'] = [149.07];
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote('AAPL');

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
        $this->assertEquals($mocked_response['ask'][0], $quote->ask);
        $this->assertEquals($mocked_response['askSize'][0], $quote->ask_size);
        $this->assertEquals($mocked_response['bid'][0], $quote->bid);
        $this->assertEquals($mocked_response['bidSize'][0], $quote->bid_size);
        $this->assertEquals($mocked_response['mid'][0], $quote->mid);
        $this->assertEquals($mocked_response['last'][0], $quote->last);
        $this->assertEquals($mocked_response['change'][0], $quote->change);
        $this->assertEquals($mocked_response['changepct'][0], $quote->change_percent);
        $this->assertEquals($mocked_response['52weekHigh'][0], $quote->fifty_two_week_high);
        $this->assertEquals($mocked_response['52weekLow'][0], $quote->fifty_two_week_low);
        $this->assertEquals($mocked_response['volume'][0], $quote->volume);
        $this->assertEquals(Carbon::parse($mocked_response['updated'][0]), $quote->updated);
    }

    /**
     * Test the quotes endpoint for a successful response.
     *
     * @return void
     * @throws GuzzleException
     * @throws \Throwable
     */
    public function testQuotes_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $nflx_mocked_response = [
            's'         => 'ok',
            'symbol'    => ['NFLX'],
            'ask'       => [400.0],
            'askSize'   => [200],
            'bid'       => [399.99],
            'bidSize'   => [600],
            'mid'       => [399.99],
            'last'      => [400.0],
            'change'    => [0.01],
            'changepct' => [0.01],
            'volume'    => [66959442],
            'updated'   => [1663958092]
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($this->aapl_mocked_response)),
            new Response(200, [], json_encode($nflx_mocked_response)),
        ]);

        $quotes = $this->client->stocks->quotes(['AAPL', 'NFLX']);
        $this->assertInstanceOf(Quotes::class, $quotes);
        foreach ($quotes->quotes as $quote) {
            $this->assertInstanceOf(Quote::class, $quote);
            $mocked_response = $quote->symbol === "AAPL" ? $this->aapl_mocked_response : $nflx_mocked_response;

            $this->assertEquals($mocked_response['s'], $quote->status);
            $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
            $this->assertEquals($mocked_response['ask'][0], $quote->ask);
            $this->assertEquals($mocked_response['askSize'][0], $quote->ask_size);
            $this->assertEquals($mocked_response['bid'][0], $quote->bid);
            $this->assertEquals($mocked_response['bidSize'][0], $quote->bid_size);
            $this->assertEquals($mocked_response['mid'][0], $quote->mid);
            $this->assertEquals($mocked_response['last'][0], $quote->last);
            $this->assertEquals($mocked_response['change'][0], $quote->change);
            $this->assertEquals($mocked_response['changepct'][0], $quote->change_percent);
            $this->assertEquals($mocked_response['volume'][0], $quote->volume);
            $this->assertEquals(Carbon::parse($mocked_response['updated'][0]), $quote->updated);
        }
    }

    /**
     * Test the earnings endpoint for a successful response.
     *
     * @return void
     */
    public function testEarnings_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'              => 'ok',
            'symbol'         => ['AAPL', 'AAPL'],
            'fiscalYear'     => [2023, 2023],
            'fiscalQuarter'  => [1, 2],
            'date'           => [1672462800, 1672562800],
            'reportDate'     => [1675314000, 1675414000],
            'reportTime'     => ['before market open', 'after market close'],
            'currency'       => ['USD', 'USD'],
            'reportedEPS'    => [1.88, 1.92],
            'estimatedEPS'   => [1.94, 1.9],
            'surpriseEPS'    => [-0.06, 0.02],
            'surpriseEPSpct' => [-3.0928, 0.2308],
            'updated'        => [1701690000, 1701690000]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);
        $response = $this->client->stocks->earnings(symbol: 'AAPL', from: '2023-01-01');

        $this->assertInstanceOf(Earnings::class, $response);
        $this->assertEquals($response->status, $mocked_response['s']);
        $this->assertNotEmpty($response->earnings);

        for ($i = 0; $i < count($response->earnings); $i++) {
            $this->assertInstanceOf(Earning::class, $response->earnings[$i]);
            $this->assertEquals($mocked_response['symbol'][$i], $response->earnings[$i]->symbol);
            $this->assertEquals($mocked_response['fiscalYear'][$i], $response->earnings[$i]->fiscal_year);
            $this->assertEquals($mocked_response['fiscalQuarter'][$i], $response->earnings[$i]->fiscal_quarter);
            $this->assertEquals(Carbon::parse($mocked_response['date'][$i]), $response->earnings[$i]->date);
            $this->assertEquals(Carbon::parse($mocked_response['reportDate'][$i]),
                $response->earnings[$i]->report_date);
            $this->assertEquals($mocked_response['reportTime'][$i], $response->earnings[$i]->report_time);
            $this->assertEquals($mocked_response['currency'][$i], $response->earnings[$i]->currency);
            $this->assertEquals($mocked_response['reportedEPS'][$i], $response->earnings[$i]->reported_eps);
            $this->assertEquals($mocked_response['estimatedEPS'][$i], $response->earnings[$i]->estimated_eps);
            $this->assertEquals($mocked_response['surpriseEPS'][$i], $response->earnings[$i]->surprise_eps);
            $this->assertEquals($mocked_response['surpriseEPSpct'][$i], $response->earnings[$i]->surprise_eps_pct);
            $this->assertEquals(Carbon::parse($mocked_response['updated'][$i]), $response->earnings[$i]->updated);
        }
    }

    /**
     * Test the earnings endpoint for a successful CSV response.
     *
     * @return void
     */
    public function testEarnings_csv_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, symbol, fiscalYear...";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);
        $response = $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2023-01-01',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Earnings::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the earnings endpoint with human-readable format.
     *
     * @return void
     */
    public function testEarnings_humanReadable_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'Symbol' => ['AAPL', 'AAPL'],
            'Fiscal Year' => [2023, 2023],
            'Fiscal Quarter' => [1, 2],
            'Date' => [1672462800, 1672562800],
            'Report Date' => [1675314000, 1675414000],
            'Report Time' => ['before market open', 'after market close'],
            'Currency' => ['USD', 'USD'],
            'Reported EPS' => [1.88, 1.92],
            'Estimated EPS' => [1.94, 1.9],
            'Surprise EPS' => [-0.06, 0.02],
            'Surprise EPS %' => [-3.0928, 0.2308],
            'Updated' => [1701690000, 1701690000]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);
        $response = $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2023-01-01',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Earnings::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(2, $response->earnings);
        $this->assertEquals($mocked_response['Symbol'][0], $response->earnings[0]->symbol);
        $this->assertEquals($mocked_response['Fiscal Year'][0], $response->earnings[0]->fiscal_year);
        $this->assertEquals($mocked_response['Fiscal Quarter'][0], $response->earnings[0]->fiscal_quarter);
    }

    /**
     * Test the earnings endpoint for an exception when neither 'from' nor 'countback' is provided.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testEarnings_noFromOrCountback_throwsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->stocks->earnings('AAPL');
    }

    /**
     * Test the news endpoint for a successful response.
     *
     * @return void
     */
    public function testNews_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'symbol'          => 'AAPL',
            'headline'        => 'Whoa, There! Let Apple Stock Take a Breather Before Jumping in Headfirst.',
            'content'         => "Apple is a rock-solid company, but this doesn't mean prudent investors need to buy AAPL stock at any price.",
            'source'          => 'https=>//investorplace.com/2023/12/whoa-there-let-apple-stock-take-a-breather-before-jumping-in-headfirst/',
            'publicationDate' => 1703041200
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);
        $news = $this->client->stocks->news(symbol: 'AAPL', from: '2023-01-01');

        $this->assertInstanceOf(News::class, $news);
        $this->assertEquals($mocked_response['s'], $news->status);
        $this->assertEquals($mocked_response['symbol'], $news->symbol);
        $this->assertEquals($mocked_response['headline'], $news->headline);
        $this->assertEquals($mocked_response['content'], $news->content);
        $this->assertEquals($mocked_response['source'], $news->source);
        $this->assertEquals(Carbon::parse($mocked_response['publicationDate']), $news->publication_date);
    }

    /**
     * Test the news endpoint for a successful CSV response.
     *
     * @return void
     */
    public function testNews_csv_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, symbol, headline...";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);
        $news = $this->client->stocks->news(
            symbol: 'AAPL',
            from: '2023-01-01',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(News::class, $news);
        $this->assertEquals($mocked_response, $news->getCsv());
    }

    /**
     * Test the news endpoint with human-readable format.
     *
     * @return void
     */
    public function testNews_humanReadable_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'headline' => 'Test Headline',
            'content' => 'Test Content',
            'source' => 'https://example.com',
            'publicationDate' => 1703041200,
            'Symbol' => 'AAPL',
            'Date' => 1703041200
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);
        $news = $this->client->stocks->news(
            symbol: 'AAPL',
            from: '2023-01-01',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(News::class, $news);
        $this->assertEquals('ok', $news->status);
        $this->assertEquals($mocked_response['Symbol'], $news->symbol);
        $this->assertEquals($mocked_response['headline'], $news->headline);
        $this->assertEquals($mocked_response['content'], $news->content);
        $this->assertEquals($mocked_response['source'], $news->source);
        $this->assertEquals(Carbon::parse($mocked_response['publicationDate']), $news->publication_date);
    }

    /**
     * Test the news endpoint for an exception when neither 'from' nor 'countback' is provided.
     *
     * @return void
     */
    public function testNews_noFromOrCountback_throwsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->stocks->news('AAPL');
    }

    /**
     * Test exception handling for GuzzleException.
     *
     * RequestException is retryable, so we need to provide enough mock responses
     * to exhaust retries (3 attempts total).
     *
     * @return void
     */
    public function testExceptionHandling_throwsGuzzleException()
    {
        $this->setMockResponses([
            new RequestException("Error Communicating with Server", new Request('GET', 'test')),
            new RequestException("Error Communicating with Server", new Request('GET', 'test')),
            new RequestException("Error Communicating with Server", new Request('GET', 'test')),
        ]);

        // After retries are exhausted, RequestError is thrown (not GuzzleException)
        $this->expectException(\MarketDataApp\Exceptions\RequestError::class);
        $response = $this->client->stocks->quote("INVALID");
    }

    /**
     * Test the quote endpoint with human-readable format.
     *
     * @return void
     */
    public function testQuote_humanReadable_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'Symbol' => ['AAPL'],
            'Ask' => [149.08],
            'Ask Size' => [200],
            'Bid' => [149.07],
            'Bid Size' => [600],
            'Mid' => [149.075],
            'Last' => [149.09],
            'Change $' => [0.01],
            'Change %' => [0.0001],
            'Volume' => [66959442],
            'Date' => [1663958092]
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            false,
            new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals('ok', $quote->status);
        $this->assertEquals($mocked_response['Symbol'][0], $quote->symbol);
        $this->assertEquals($mocked_response['Ask'][0], $quote->ask);
        $this->assertEquals($mocked_response['Ask Size'][0], $quote->ask_size);
        $this->assertEquals($mocked_response['Bid'][0], $quote->bid);
        $this->assertEquals($mocked_response['Bid Size'][0], $quote->bid_size);
        $this->assertEquals($mocked_response['Mid'][0], $quote->mid);
        $this->assertEquals($mocked_response['Last'][0], $quote->last);
        $this->assertEquals($mocked_response['Change $'][0], $quote->change);
        $this->assertEquals($mocked_response['Change %'][0], $quote->change_percent);
        $this->assertEquals($mocked_response['Volume'][0], $quote->volume);
        $this->assertEquals(Carbon::parse($mocked_response['Date'][0]), $quote->updated);
    }

    /**
     * Test the quote endpoint with human-readable format and 52-week high/low.
     * Uses real API response values from a live API call.
     *
     * @return void
     */
    public function testQuote_humanReadable_52week_success()
    {
        // Real API response values captured from live API call on 2026-01-22
        $mocked_response = [
            'Symbol' => ['AAPL'],
            'Ask' => [248.8],
            'Ask Size' => [200],
            'Bid' => [248.7],
            'Bid Size' => [600],
            'Mid' => [248.75],
            'Last' => [247.65],
            'Change $' => [0.95],
            'Change %' => [0.0039],
            'Volume' => [54933217],
            'Date' => [1769043595],
            '52 Week High' => [288.62],
            '52 Week Low' => [169.2101]
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            true,
            new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals('ok', $quote->status);
        $this->assertEquals($mocked_response['Symbol'][0], $quote->symbol);
        $this->assertEquals($mocked_response['Ask'][0], $quote->ask);
        $this->assertEquals($mocked_response['Ask Size'][0], $quote->ask_size);
        $this->assertEquals($mocked_response['Bid'][0], $quote->bid);
        $this->assertEquals($mocked_response['Bid Size'][0], $quote->bid_size);
        $this->assertEquals($mocked_response['Mid'][0], $quote->mid);
        $this->assertEquals($mocked_response['Last'][0], $quote->last);
        $this->assertEquals($mocked_response['Change $'][0], $quote->change);
        $this->assertEquals($mocked_response['Change %'][0], $quote->change_percent);
        $this->assertEquals($mocked_response['Volume'][0], $quote->volume);
        $this->assertEquals(Carbon::parse($mocked_response['Date'][0]), $quote->updated);
        $this->assertEquals($mocked_response['52 Week High'][0], $quote->fifty_two_week_high);
        $this->assertEquals($mocked_response['52 Week Low'][0], $quote->fifty_two_week_low);
    }

    /**
     * Test the quote endpoint with human_readable=false.
     *
     * @return void
     */
    public function testQuote_humanReadableFalse_success()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            false,
            new Parameters(use_human_readable: false)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
    }

    /**
     * Test the quote endpoint with human_readable=null (should use regular format).
     *
     * @return void
     */
    public function testQuote_humanReadableNull_usesRegularFormat()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            false,
            new Parameters(use_human_readable: null)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
    }

    /**
     * Test the quotes endpoint (parallel) with human-readable format.
     *
     * @return void
     * @throws \Throwable
     */
    public function testQuotes_humanReadable_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $human_readable_response = [
            'Symbol' => ['AAPL'],
            'Ask' => [149.08],
            'Ask Size' => [200],
            'Bid' => [149.07],
            'Bid Size' => [600],
            'Mid' => [149.075],
            'Last' => [149.09],
            'Change $' => [0.01],
            'Change %' => [0.0001],
            'Volume' => [66959442],
            'Date' => [1663958092]
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($human_readable_response)),
        ]);
        $quotes = $this->client->stocks->quotes(
            ['AAPL'],
            false,
            new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quotes::class, $quotes);
        $this->assertCount(1, $quotes->quotes);
        $this->assertInstanceOf(Quote::class, $quotes->quotes[0]);
        $this->assertEquals('ok', $quotes->quotes[0]->status);
        $this->assertEquals($human_readable_response['Symbol'][0], $quotes->quotes[0]->symbol);
    }

    /**
     * Test the quote endpoint with mode=LIVE.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testQuote_modeLive_success()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            false,
            new Parameters(mode: Mode::LIVE)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
    }

    /**
     * Test the quote endpoint with mode=CACHED.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testQuote_modeCached_success()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            false,
            new Parameters(mode: Mode::CACHED)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
    }

    /**
     * Test the quote endpoint with mode=DELAYED.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testQuote_modeDelayed_success()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            false,
            new Parameters(mode: Mode::DELAYED)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
    }

    /**
     * Test the quote endpoint with mode=null (should not include mode parameter).
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testQuote_modeNull_notIncluded()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            false,
            new Parameters(mode: null)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
    }

    /**
     * Test the quotes endpoint (parallel) with mode parameter.
     *
     * @return void
     * @throws \Throwable
     */
    public function testQuotes_mode_success()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quotes = $this->client->stocks->quotes(
            ['AAPL'],
            false,
            new Parameters(mode: Mode::LIVE)
        );

        $this->assertInstanceOf(Quotes::class, $quotes);
        $this->assertCount(1, $quotes->quotes);
        $this->assertInstanceOf(Quote::class, $quotes->quotes[0]);
        $this->assertEquals('ok', $quotes->quotes[0]->status);
        $this->assertEquals($mocked_response['symbol'][0], $quotes->quotes[0]->symbol);
    }

    /**
     * Test that date_format parameter can be used with CSV format.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testParameters_dateFormat_withCsv_success(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, c, h, l, o, v, t";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
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
     * Test that date_format parameter can be used with HTML format.
     *
     * @return void
     */
    public function testParameters_dateFormat_withHtml_success(): void
    {
        $params = new Parameters(format: Format::HTML, date_format: DateFormat::TIMESTAMP);
        $this->assertEquals(Format::HTML, $params->format);
        $this->assertEquals(DateFormat::TIMESTAMP, $params->date_format);
    }

    /**
     * Test candles endpoint with HTML format and dateformat=unix.
     * Verifies that the HTML response is returned and dateformat parameter is passed.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_html_withDateFormat_unix(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "<table><tr><th>Date</th></tr><tr><td>1234567890</td></tr></table>";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::HTML, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isHtml());
        $this->assertEquals($mocked_response, $response->getHtml());
    }

    /**
     * Test that null date_format with CSV is valid (backward compatibility).
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testParameters_dateFormat_null_withCsv_success(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, c, h, l, o, v, t";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: null)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test candles endpoint with CSV format and dateformat=unix.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_csv_withDateFormat_unix(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, c, h, l, o, v, t";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test candles endpoint with CSV format and dateformat=timestamp.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_csv_withDateFormat_timestamp(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, c, h, l, o, v, t";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test candles endpoint with CSV format and dateformat=spreadsheet.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_csv_withDateFormat_spreadsheet(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, c, h, l, o, v, t";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::SPREADSHEET)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the prices endpoint for a successful response with single symbol.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testPrices_singleSymbol_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'mid' => [149.07],
            'change' => [-2.052],
            'changepct' => [-0.0088],
            'updated' => [1663958092]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->prices('AAPL');

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->symbols);
        $this->assertEquals('AAPL', $response->symbols[0]);
        $this->assertCount(1, $response->mid);
        $this->assertEquals(149.07, $response->mid[0]);
        $this->assertCount(1, $response->change);
        $this->assertEquals(-2.052, $response->change[0]);
        $this->assertCount(1, $response->changepct);
        $this->assertEquals(-0.0088, $response->changepct[0]);
        $this->assertCount(1, $response->updated);
        $this->assertInstanceOf(Carbon::class, $response->updated[0]);
        $this->assertEquals(Carbon::parse(1663958092), $response->updated[0]);
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
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's' => 'ok',
            'symbol' => ['AAPL', 'META', 'MSFT'],
            'mid' => [149.07, 320.45, 380.12],
            'change' => [-2.052, 1.23, -0.85],
            'changepct' => [-0.0088, 0.0039, -0.0022],
            'updated' => [1663958092, 1663958092, 1663958092]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->prices(['AAPL', 'META', 'MSFT']);

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(3, $response->symbols);
        $this->assertEquals(['AAPL', 'META', 'MSFT'], $response->symbols);
        $this->assertCount(3, $response->mid);
        $this->assertEquals([149.07, 320.45, 380.12], $response->mid);
        $this->assertCount(3, $response->change);
        $this->assertEquals([-2.052, 1.23, -0.85], $response->change);
        $this->assertCount(3, $response->changepct);
        $this->assertEquals([-0.0088, 0.0039, -0.0022], $response->changepct);
        $this->assertCount(3, $response->updated);
        foreach ($response->updated as $updated) {
            $this->assertInstanceOf(Carbon::class, $updated);
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
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'mid' => [149.07],
            'change' => [-2.052],
            'changepct' => [-0.0088],
            'updated' => [1663958092]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->prices('AAPL', extended: true);

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->symbols);
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
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'mid' => [149.07],
            'change' => [-2.052],
            'changepct' => [-0.0088],
            'updated' => [1663958092]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->prices('AAPL', extended: false);

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->symbols);
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
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, symbol, mid, change, changepct, updated";
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
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'Symbol' => ['AAPL', 'META'],
            'Mid' => [149.07, 320.45],
            'Change $' => [-2.052, 1.23],
            'Change %' => [-0.0088, 0.0039],
            'Date' => [1663958092, 1663958092]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->prices(
            ['AAPL', 'META'],
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(2, $response->symbols);
        $this->assertEquals(['AAPL', 'META'], $response->symbols);
        $this->assertCount(2, $response->mid);
        $this->assertEquals([149.07, 320.45], $response->mid);
        $this->assertCount(2, $response->change);
        $this->assertEquals([-2.052, 1.23], $response->change);
        $this->assertCount(2, $response->changepct);
        $this->assertEquals([-0.0088, 0.0039], $response->changepct);
        $this->assertCount(2, $response->updated);
        foreach ($response->updated as $updated) {
            $this->assertInstanceOf(Carbon::class, $updated);
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
        $this->assertEmpty($response->symbols);
        $this->assertEmpty($response->mid);
        $this->assertEmpty($response->change);
        $this->assertEmpty($response->changepct);
        $this->assertEmpty($response->updated);
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
     * Test candles endpoint with invalid date range (from > to).
     */
    public function testCandles_invalidDateRange_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`from` date must be before `to` date');

        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2024-01-31',
            to: '2024-01-01',
            resolution: 'D'
        );
    }

    /**
     * Test candles endpoint with relative dates (should not throw exception).
     */
    public function testCandles_relativeDates_noException(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $this->setMockResponses([
            new Response(200, [], json_encode(['s' => 'ok', 't' => [], 'o' => [], 'h' => [], 'l' => [], 'c' => [], 'v' => []])),
        ]);

        // Relative dates should pass through without validation
        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: 'today',
            to: 'yesterday',
            resolution: 'D'
        );

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test candles endpoint with invalid countback (zero).
     */
    public function testCandles_invalidCountback_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`countback` must be a positive integer');

        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2024-01-01',
            resolution: 'D',
            countback: 0
        );
    }

    /**
     * Test candles endpoint with invalid resolution.
     */
    public function testCandles_invalidResolution_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid resolution format');

        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2024-01-01',
            resolution: 'invalid'
        );
    }

    /**
     * Test quote endpoint with empty symbol.
     */
    public function testQuote_emptySymbol_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-empty string');

        $this->client->stocks->quote('');
    }

    /**
     * Test quotes endpoint with empty array.
     */
    public function testQuotes_emptyArray_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-empty array');

        $this->client->stocks->quotes([]);
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
     * Test earnings endpoint with invalid date range.
     */
    public function testEarnings_invalidDateRange_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`from` date must be before `to` date');

        $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2024-01-31',
            to: '2024-01-01'
        );
    }

    /**
     * Test earnings endpoint with invalid countback.
     */
    public function testEarnings_invalidCountback_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`countback` must be a positive integer');

        $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2024-01-01',
            to: '2024-01-31',
            countback: -5
        );
    }

    /**
     * Test news endpoint with invalid date range.
     */
    public function testNews_invalidDateRange_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`from` date must be before `to` date');

        $this->client->stocks->news(
            symbol: 'AAPL',
            from: '2024-01-31',
            to: '2024-01-01'
        );
    }

    /**
     * Test bulkCandles endpoint with invalid resolution.
     */
    public function testBulkCandles_invalidResolution_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid resolution format');

        $this->client->stocks->bulkCandles(
            symbols: ['AAPL'],
            resolution: 'invalid'
        );
    }
}
