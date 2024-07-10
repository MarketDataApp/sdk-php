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
use MarketDataApp\Endpoints\Responses\Stocks\BulkQuote;
use MarketDataApp\Endpoints\Responses\Stocks\BulkQuotes;
use MarketDataApp\Endpoints\Responses\Stocks\Candle;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Endpoints\Responses\Stocks\Quotes;
use PHPUnit\Framework\TestCase;

class StocksTest extends TestCase
{

    private Client $client;

    private array $aapl_mocked_response = [
        's'          => 'ok',
        'symbol'     => ['AAPL'],
        'ask'        => [149.08],
        'askSize'    => [200],
        'bid'        => [149.07],
        'bidSize'    => [600],
        'mid'        => [149.07],
        'last'       => [149.09],
        'change'     => [0.01],
        'changepct'  => [0.01],
        'volume'     => [66959442],
        'updated'    => [1663958092]
    ];

    private array $multiple_mocked_response = [
        's'          => 'ok',
        'symbol'     => ['APPL', 'NFLX'],
        'ask'        => [350.0, 400.0],
        'askSize'    => [159, 200],
        'bid'        => [349, 399.99],
        'bidSize'    => [452, 600],
        'mid'        => [349.99, 399.99],
        'last'       => [350.2, 400.0],
        'change'     => [0.03, 0.01],
        'changepct'  => [0.05, 0.01],
        'volume'     => [123123, 66959442],
        'updated'    => [1663958094, 1663958092]
    ];

    protected function setUp(): void
    {
        $token = "your_api_token";
        $client = new Client($token);
        $this->client = $client;
    }

    /**
     * @throws GuzzleException
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
            symbol: "DJI",
            from: Carbon::parse('2022-09-01'),
            to: Carbon::parse('2022-09-05'),
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertCount(5, $response->candles);

        // Verify each item in the response is an object of the correct type and has the correct values.
        for($i = 0; $i < count($response->candles); $i++) {
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
     * @throws GuzzleException
     */
    public function testCandles_noData_success()
    {
        $mocked_response = [
            's' => 'no_data',
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->candles(
            symbol: "DJI",
            from: Carbon::parse('2022-09-01'),
            to: Carbon::parse('2022-09-05'),
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEmpty($response->candles);
        $this->assertFalse(isset($response->next_time));
    }

    /**
     * @throws GuzzleException
     */
    public function testCandles_noDataNextTime_success()
    {
        $mocked_response = [
            's' => 'no_data',
            'nextTime' => 1663958094,
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->candles(
            symbol: "DJI",
            from: Carbon::parse('2022-09-01'),
            to: Carbon::parse('2022-09-05'),
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals($mocked_response['nextTime'], $response->next_time);
        $this->assertEmpty($response->candles);
    }

    public function testStocks_quote_success()
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
        $this->assertEquals($mocked_response['change'][0], $quote->change);
        $this->assertEquals($mocked_response['changepct'][0], $quote->change_percent);
        $this->assertNull($quote->fifty_two_week_high);
        $this->assertNull($quote->fifty_two_week_low);
        $this->assertEquals($mocked_response['volume'][0], $quote->volume);
        $this->assertEquals(Carbon::parse($mocked_response['updated'][0]), $quote->updated);
    }

    public function testStocks_quote_52week_success()
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
     * @throws GuzzleException
     * @throws \Throwable
     */
    public function testStocks_quotes_success()
    {
        $nflx_mocked_response = [
            's'          => 'ok',
            'symbol'     => ['NFLX'],
            'ask'        => [400.0],
            'askSize'    => [200],
            'bid'        => [399.99],
            'bidSize'    => [600],
            'mid'        => [399.99],
            'last'       => [400.0],
            'change'     => [0.01],
            'changepct'  => [0.01],
            'volume'     => [66959442],
            'updated'    => [1663958092]
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
     * @throws \Throwable
     */
    public function testStocks_bulkQuotes_success()
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
            $this->assertEquals($mocked_response['volume'][$i], $response->quotes[$i]->volume);
            $this->assertEquals(Carbon::parse($mocked_response['updated'][$i]), $response->quotes[$i]->updated);
        }
    }

    /**
     * @throws GuzzleException
     */
    public function testStocks_bulkQuotes_snapshot_success()
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
     * @throws GuzzleException
     */
    public function testStocks_bulkQuotes_noParameters_throwsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->stocks->bulkQuotes();
    }

    public function testExceptionHandling_throwsGuzzleException()
    {
        $this->setMockResponses([
            new RequestException("Error Communicating with Server", new Request('GET', 'test')),
        ]);

        $this->expectException(\GuzzleHttp\Exception\GuzzleException::class);
        $response = $this->client->stocks->quote("INVALID");
    }

    private function setMockResponses(array $responses): void
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $this->client->setGuzzle(new GuzzleClient(['handler' => $handlerStack]));
    }
}
