<?php

namespace MarketDataApp\Tests\Unit;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Indices\Candle;
use MarketDataApp\Endpoints\Responses\Indices\Candles;
use MarketDataApp\Endpoints\Responses\Indices\Quote;
use MarketDataApp\Endpoints\Responses\Indices\Quotes;
use MarketDataApp\Enums\Format;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the Indices endpoints of the MarketDataApp.
 *
 * This class tests various scenarios of the quote endpoint for indices data.
 */
class IndicesTest extends TestCase
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
        's'          => 'ok',
        'symbol'     => ['AAPL'],
        'last'       => [50.5],
        'change'     => [30.2],
        'changepct'  => [2.4],
        '52weekHigh' => [4023.5],
        '52weekLow'  => [2035.0],
        'updated'    => ['2020-01-01T00:00:00.000000Z'],
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
     * Test the quote endpoint for a successful JSON response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testQuote_success()
    {
        $mocked_response = [
            's'          => 'ok',
            'symbol'     => ['AAPL'],
            'last'       => [50.5],
            'change'     => [30.2],
            'changepct'  => [2.4],
            '52weekHigh' => [4023.5],
            '52weekLow'  => [2035.0],
            'updated'    => ['2020-01-01T00:00:00.000000Z'],
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->indices->quote("DJI");
        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals($mocked_response['s'], $response->status);
        $this->assertEquals($mocked_response['symbol'][0], $response->symbol);
        $this->assertEquals($mocked_response['last'][0], $response->last);
        $this->assertEquals($mocked_response['change'][0], $response->change);
        $this->assertEquals($mocked_response['changepct'][0], $response->change_percent);
        $this->assertEquals($mocked_response['52weekHigh'][0], $response->fifty_two_week_high);
        $this->assertEquals($mocked_response['52weekLow'][0], $response->fifty_two_week_low);
        $this->assertEquals(Carbon::parse($mocked_response['updated'][0]), $response->updated);
    }

    /**
     * Test the quote endpoint for a successful CSV response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testQuote_csv_success()
    {
        $mocked_response = 's, symbol, last, change, changepct, 52weekHigh, 52weekLow, updated';
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->indices->quote(symbol: "DJI", parameters: new Parameters(Format::CSV));
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the quote endpoint for a successful HTML response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testQuote_HTML_success()
    {
        $mocked_response = '<pre>Hello World</pre>';
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->indices->quote(symbol: "DJI", parameters: new Parameters(Format::HTML));
        $this->assertEquals($mocked_response, $response->getHtml());
    }

    /**
     * Test the quotes endpoint for multiple symbols.
     *
     * @return void
     * @throws GuzzleException
     * @throws \Throwable
     */
    public function testQuotes_success()
    {
        $msft_mocked_response = [
            's'          => 'ok',
            'symbol'     => ['MSFT'],
            'last'       => [300.67],
            'change'     => [5.2],
            'changepct'  => [2.2],
            '52weekHigh' => [320.5],
            '52weekLow'  => [200.0],
            'updated'    => ['2020-01-01T00:00:00.000000Z'],
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($this->aapl_mocked_response)),
            new Response(200, [], json_encode($msft_mocked_response)),
        ]);

        $quotes = $this->client->indices->quotes(['AAPL', 'MSFT']);
        $this->assertInstanceOf(Quotes::class, $quotes);
        foreach ($quotes->quotes as $quote) {
            $this->assertInstanceOf(Quote::class, $quote);
            $mocked_response = $quote->symbol === "AAPL" ? $this->aapl_mocked_response : $msft_mocked_response;

            $this->assertEquals($mocked_response['s'], $quote->status);
            $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
            $this->assertEquals($mocked_response['last'][0], $quote->last);
            $this->assertEquals($mocked_response['change'][0], $quote->change);
            $this->assertEquals($mocked_response['changepct'][0], $quote->change_percent);
            $this->assertEquals($mocked_response['52weekHigh'][0], $quote->fifty_two_week_high);
            $this->assertEquals($mocked_response['52weekLow'][0], $quote->fifty_two_week_low);
            $this->assertEquals(Carbon::parse($mocked_response['updated'][0]), $quote->updated);
        }
    }

    /**
     * Test the quote endpoint for a successful 'no data' response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testQuote_noData_success()
    {
        $mocked_response = [
            's' => 'no_data',
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->indices->quote("DJI");
        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals($mocked_response['s'], $response->status);
        $this->assertFalse(isset($response->symbol));
        $this->assertFalse(isset($response->last));
        $this->assertFalse(isset($response->change));
        $this->assertFalse(isset($response->change_percent));
        $this->assertFalse(isset($response->fifty_two_week_high));
        $this->assertFalse(isset($response->fifty_two_week_low));
        $this->assertFalse(isset($response->updated));
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
            't' => [1659326400, 1659412800, 1659499200, 1659585600, 1659672000]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->indices->candles(
            symbol: "DJI",
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
        $mocked_response = "s, c, h, l, o, t\r\n";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->indices->candles(
            symbol: "DJI",
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

        $response = $this->client->indices->candles(
            symbol: "DJI",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals($mocked_response['s'], $response->status);
        $this->assertEmpty($response->candles);
        $this->assertFalse(isset($response->next_time));
        $this->assertFalse(isset($response->prev_time));
    }

    /**
     * Test the candles endpoint for a successful 'no data' response with next and previous times.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_noDataNextTimePrevTime_success()
    {
        $mocked_response = [
            's'        => 'no_data',
            'nextTime' => 1659326400,
            'prevTime' => 1659326400,
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->indices->candles(
            symbol: "DJI",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEmpty($response->candles);
        $this->assertFalse($response->isCsv());
        $this->assertFalse($response->isHtml());
        $this->assertEquals($mocked_response['s'], $response->status);
        $this->assertEquals(Carbon::parse($mocked_response['nextTime']), $response->next_time);
        $this->assertEquals(Carbon::parse($mocked_response['prevTime']), $response->next_time);
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
        $this->client->indices->quote("INVALID");
    }

    /**
     * Test exception handling for ApiException.
     *
     * @return void
     */
    public function testExceptionHandling_throwsApiException()
    {
        $mocked_response = [
            's'      => 'error',
            'errmsg' => 'Invalid symbol: INVALID',
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $this->expectException(ApiException::class);
        $this->client->indices->quote("INVALID");
    }
}
