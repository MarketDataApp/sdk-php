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
use MarketDataApp\Endpoints\Responses\Stocks\BulkQuote;
use MarketDataApp\Endpoints\Responses\Stocks\BulkQuotes;
use MarketDataApp\Endpoints\Responses\Stocks\Candle;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Endpoints\Responses\Stocks\Earning;
use MarketDataApp\Endpoints\Responses\Stocks\Earnings;
use MarketDataApp\Endpoints\Responses\Stocks\News;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Endpoints\Responses\Stocks\Quotes;
use MarketDataApp\Enums\Format;
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
        $token = "your_api_token";
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
     * Test the candles endpoint for a successful 'no data' response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_noData_success()
    {
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
     * Test the bulkCandles endpoint for a successful 'no data' response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testBulkCandles_noData_success()
    {
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
     * Test the bulkQuotes endpoint for a successful response.
     *
     * @return void
     * @throws \Throwable
     */
    public function testBulkQuotes_success()
    {
        $mocked_response = $this->multiple_mocked_response;
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->bulkQuotes(['AAPL', 'NFLX']);
        $this->assertInstanceOf(BulkQuotes::class, $response);
        $this->assertEquals($response->status, $mocked_response['s']);
        $this->assertCount(2, $response->quotes);

        for ($i = 0; $i < count($response->quotes); $i++) {
            $this->assertInstanceOf(BulkQuote::class, $response->quotes[$i]);

            $this->assertEquals($mocked_response['symbol'][$i], $response->quotes[$i]->symbol);
            $this->assertEquals($mocked_response['ask'][$i], $response->quotes[$i]->ask);
            $this->assertEquals($mocked_response['askSize'][$i], $response->quotes[$i]->ask_size);
            $this->assertEquals($mocked_response['bid'][$i], $response->quotes[$i]->bid);
            $this->assertEquals($mocked_response['bidSize'][$i], $response->quotes[$i]->bid_size);
            $this->assertEquals($mocked_response['mid'][$i], $response->quotes[$i]->mid);
            $this->assertEquals($mocked_response['last'][$i], $response->quotes[$i]->last);
            $this->assertEquals($mocked_response['change'][$i], $response->quotes[$i]->change);
            $this->assertEquals($mocked_response['changepct'][$i], $response->quotes[$i]->change_percent);
            $this->assertNull($response->quotes[$i]->fifty_two_week_high);
            $this->assertNull($response->quotes[$i]->fifty_two_week_low);
            $this->assertEquals($mocked_response['volume'][$i], $response->quotes[$i]->volume);
            $this->assertEquals(Carbon::parse($mocked_response['updated'][$i]), $response->quotes[$i]->updated);
        }
    }

    /**
     * Test the bulkQuotes endpoint for a successful CSV response.
     *
     * @return void
     */
    public function testBulkQuotes_csv_success()
    {
        $mocked_response = "a, b, c";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->bulkQuotes(
            symbols: ['AAPL', 'NFLX'],
            parameters: new Parameters(format: Format::CSV)
        );
        $this->assertInstanceOf(BulkQuotes::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the bulkQuotes endpoint for a successful response with 52-week high/low.
     *
     * @return void
     * @throws \Throwable
     */
    public function testBulkQuotes_52week_success()
    {
        $mocked_response = $this->multiple_mocked_response;
        $mocked_response['52weekHigh'] = [400.0, 410.0];
        $mocked_response['52weekLow'] = [399.99, 390.0];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->bulkQuotes(['AAPL', 'NFLX']);
        $this->assertInstanceOf(BulkQuotes::class, $response);
        $this->assertEquals($response->status, $mocked_response['s']);
        $this->assertCount(2, $response->quotes);

        for ($i = 0; $i < count($response->quotes); $i++) {
            $this->assertInstanceOf(BulkQuote::class, $response->quotes[$i]);

            $this->assertEquals($mocked_response['symbol'][$i], $response->quotes[$i]->symbol);
            $this->assertEquals($mocked_response['ask'][$i], $response->quotes[$i]->ask);
            $this->assertEquals($mocked_response['askSize'][$i], $response->quotes[$i]->ask_size);
            $this->assertEquals($mocked_response['bid'][$i], $response->quotes[$i]->bid);
            $this->assertEquals($mocked_response['bidSize'][$i], $response->quotes[$i]->bid_size);
            $this->assertEquals($mocked_response['mid'][$i], $response->quotes[$i]->mid);
            $this->assertEquals($mocked_response['last'][$i], $response->quotes[$i]->last);
            $this->assertEquals($mocked_response['change'][$i], $response->quotes[$i]->change);
            $this->assertEquals($mocked_response['changepct'][$i], $response->quotes[$i]->change_percent);
            $this->assertEquals($mocked_response['52weekHigh'][$i], $response->quotes[$i]->fifty_two_week_high);
            $this->assertEquals($mocked_response['52weekLow'][$i], $response->quotes[$i]->fifty_two_week_low);
            $this->assertEquals($mocked_response['volume'][$i], $response->quotes[$i]->volume);
            $this->assertEquals(Carbon::parse($mocked_response['updated'][$i]), $response->quotes[$i]->updated);
        }
    }

    /**
     * Test the bulkQuotes endpoint for a successful response with snapshot parameter.
     *
     * @return void
     * @throws GuzzleException
     */
    public function testBulkQuotes_snapshot_success()
    {
        $mocked_response = $this->multiple_mocked_response;
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->bulkQuotes(snapshot: true);
        $this->assertInstanceOf(BulkQuotes::class, $response);
        $this->assertEquals($response->status, $mocked_response['s']);
        $this->assertCount(2, $response->quotes);

        for ($i = 0; $i < count($response->quotes); $i++) {
            $this->assertInstanceOf(BulkQuote::class, $response->quotes[$i]);

            $this->assertEquals($mocked_response['symbol'][$i], $response->quotes[$i]->symbol);
            $this->assertEquals($mocked_response['ask'][$i], $response->quotes[$i]->ask);
            $this->assertEquals($mocked_response['askSize'][$i], $response->quotes[$i]->ask_size);
            $this->assertEquals($mocked_response['bid'][$i], $response->quotes[$i]->bid);
            $this->assertEquals($mocked_response['bidSize'][$i], $response->quotes[$i]->bid_size);
            $this->assertEquals($mocked_response['mid'][$i], $response->quotes[$i]->mid);
            $this->assertEquals($mocked_response['last'][$i], $response->quotes[$i]->last);
            $this->assertEquals($mocked_response['change'][$i], $response->quotes[$i]->change);
            $this->assertEquals($mocked_response['changepct'][$i], $response->quotes[$i]->change_percent);
            $this->assertEquals($mocked_response['volume'][$i], $response->quotes[$i]->volume);
            $this->assertEquals(Carbon::parse($mocked_response['updated'][$i]), $response->quotes[$i]->updated);
        }
    }

    /**
     * Test the bulkQuotes endpoint for an exception when no parameters are provided.
     *
     * @return void
     * @throws GuzzleException
     */
    public function testBulkQuotes_noParameters_throwsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->stocks->bulkQuotes();
    }

    /**
     * Test the earnings endpoint for a successful response.
     *
     * @return void
     */
    public function testEarnings_success()
    {
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
     * @return void
     */
    public function testExceptionHandling_throwsGuzzleException()
    {
        $this->setMockResponses([
            new RequestException("Error Communicating with Server", new Request('GET', 'test')),
        ]);

        $this->expectException(\GuzzleHttp\Exception\GuzzleException::class);
        $response = $this->client->stocks->quote("INVALID");
    }
}
