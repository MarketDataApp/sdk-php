<?php

namespace MarketDataApp\Tests;

use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
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

    public function testIndicesQuote_success()
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

        $response = $this->client->indices->quote("AAPL");
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
