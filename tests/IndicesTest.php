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
use MarketDataApp\Endpoints\Responses\IndicesCandle;
use MarketDataApp\Endpoints\Responses\IndicesCandles;
use MarketDataApp\Endpoints\Responses\IndicesQuote;
use PHPUnit\Framework\TestCase;

class IndicesTest extends TestCase
{

    private Client $client;

    protected function setUp(): void
    {
        $token = "your_api_token";
        $client = new Client($token);
        $this->client = $client;
    }

    public function testIndices_quote_success()
    {
        $mocked_response = [
            's'          => 'ok',
            'symbol'     => ['AAPL'],
            'last'       => [50.5],
            'change'     => [30.2],
            'changepct'  => [2.4],
            '52weekHigh' => [4023.5],
            '52weekLow'  => [2035.0],
            'updated'    => '2020-01-01T00:00:00.000000Z',
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->indices->quote("DJI");
        $this->assertInstanceOf(IndicesQuote::class, $response);
        $this->assertEquals($mocked_response['s'], $response->status);
        $this->assertEquals($mocked_response['symbol'][0], $response->symbol);
        $this->assertEquals($mocked_response['last'][0], $response->last);
        $this->assertEquals($mocked_response['change'][0], $response->change);
        $this->assertEquals($mocked_response['changepct'][0], $response->change_percent);
        $this->assertEquals($mocked_response['52weekHigh'][0], $response->fifty_two_week_high);
        $this->assertEquals($mocked_response['52weekLow'][0], $response->fifty_two_week_low);
        $this->assertEquals(Carbon::parse($mocked_response['updated']), $response->updated);
    }

    /**
     * @throws GuzzleException
     */
    public function testIndices_candles_fromTo_success()
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
            from: Carbon::parse('2022-09-01'),
            to: Carbon::parse('2022-09-05'),
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(IndicesCandles::class, $response);
        $this->assertCount(5, $response->candles);

        // Verify each item in the response is an object of the correct type and has the correct values.
        for($i = 0; $i < count($response->candles); $i++) {
            $this->assertInstanceOf(IndicesCandle::class, $response->candles[$i]);
            $this->assertEquals($mocked_response['c'][$i], $response->candles[$i]->close);
            $this->assertEquals($mocked_response['h'][$i], $response->candles[$i]->high);
            $this->assertEquals($mocked_response['l'][$i], $response->candles[$i]->low);
            $this->assertEquals($mocked_response['o'][$i], $response->candles[$i]->open);
            $this->assertEquals(Carbon::parse($mocked_response['t'][$i]), $response->candles[$i]->timestamp);
        }
    }

    public function testExceptionHandling_throwsGuzzleException()
    {
        $this->setMockResponses([
            new RequestException("Error Communicating with Server", new Request('GET', 'test')),
        ]);

        $this->expectException(\GuzzleHttp\Exception\GuzzleException::class);
        $response = $this->client->indices->quote("INVALID");
    }

    private function setMockResponses(array $responses): void
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $this->client->setGuzzle(new GuzzleClient(['handler' => $handlerStack]));
    }
}
