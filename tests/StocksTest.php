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
use MarketDataApp\Endpoints\Responses\StocksBulkQuote;
use MarketDataApp\Endpoints\Responses\StocksBulkQuotes;
use MarketDataApp\Endpoints\Responses\StocksQuote;
use MarketDataApp\Endpoints\Responses\StocksQuotes;
use PHPUnit\Framework\TestCase;

class StocksTest extends TestCase
{

    private Client $client;

    private $aapl_mocked_response = [
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
        '52weekHigh' => [149.09],
        '52weekLow'  => [149.07],
        'volume'     => [66959442],
        'updated'    => [1663958092]
    ];

    protected function setUp(): void
    {
        $token = "your_api_token";
        $client = new Client($token);
        $this->client = $client;
    }

    public function testStocks_quote_success()
    {
        $this->setMockResponses([
            new Response(200, [], json_encode($this->aapl_mocked_response)),
        ]);
        $quote = $this->client->stocks->quote('AAPL');

        $this->assertInstanceOf(StocksQuote::class, $quote);
        $this->assertEquals($this->aapl_mocked_response['s'], $quote->status);
        $this->assertEquals($this->aapl_mocked_response['symbol'][0], $quote->symbol);
        $this->assertEquals($this->aapl_mocked_response['ask'][0], $quote->ask);
        $this->assertEquals($this->aapl_mocked_response['askSize'][0], $quote->ask_size);
        $this->assertEquals($this->aapl_mocked_response['bid'][0], $quote->bid);
        $this->assertEquals($this->aapl_mocked_response['bidSize'][0], $quote->bid_size);
        $this->assertEquals($this->aapl_mocked_response['mid'][0], $quote->mid);
        $this->assertEquals($this->aapl_mocked_response['last'][0], $quote->last);
        $this->assertEquals($this->aapl_mocked_response['change'][0], $quote->change);
        $this->assertEquals($this->aapl_mocked_response['changepct'][0], $quote->change_percent);
        $this->assertEquals($this->aapl_mocked_response['52weekHigh'][0], $quote->fifty_two_week_high);
        $this->assertEquals($this->aapl_mocked_response['52weekLow'][0], $quote->fifty_two_week_low);
        $this->assertEquals($this->aapl_mocked_response['volume'][0], $quote->volume);
        $this->assertEquals(Carbon::parse($this->aapl_mocked_response['updated'][0]), $quote->updated);
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
            '52weekHigh' => [400.0],
            '52weekLow'  => [399.99],
            'volume'     => [66959442],
            'updated'    => [1663958092]
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($this->aapl_mocked_response)),
            new Response(200, [], json_encode($nflx_mocked_response)),
        ]);

        $quotes = $this->client->stocks->quotes(['AAPL', 'NFLX']);
        $this->assertInstanceOf(StocksQuotes::class, $quotes);
        foreach ($quotes->quotes as $quote) {
            $this->assertInstanceOf(StocksQuote::class, $quote);
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
            $this->assertEquals($mocked_response['52weekHigh'][0], $quote->fifty_two_week_high);
            $this->assertEquals($mocked_response['52weekLow'][0], $quote->fifty_two_week_low);
            $this->assertEquals($mocked_response['volume'][0], $quote->volume);
            $this->assertEquals(Carbon::parse($mocked_response['updated'][0]), $quote->updated);
        }
    }

    /**
     * @throws \Throwable
     */
    public function testStocks_bulkQuotes_success()
    {
        $mocked_response = [
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
            '52weekHigh' => [380.0, 400.0],
            '52weekLow'  => [200.99, 399.99],
            'volume'     => [123123, 66959442],
            'updated'    => [1663958094, 1663958092]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->bulkQuotes(['AAPL', 'NFLX']);
        $this->assertInstanceOf(StocksBulkQuotes::class, $response);
        $this->assertEquals($response->status, $mocked_response['s']);
        $this->assertCount(2, $response->quotes);

        for ($i = 0; $i < count($response->quotes); $i++) {
            $this->assertInstanceOf(StocksBulkQuote::class, $response->quotes[$i]);

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
