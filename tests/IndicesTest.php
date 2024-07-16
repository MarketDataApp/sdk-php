<?php

namespace MarketDataApp\Tests;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Indices\Candle;
use MarketDataApp\Endpoints\Responses\Indices\Candles;
use MarketDataApp\Endpoints\Responses\Indices\Quote;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

class IndicesTest extends TestCase
{

    use MockResponses;

    private Client $client;

    protected function setUp(): void
    {
        $token = "your_api_token";
        $client = new Client($token);
        $this->client = $client;
    }

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
            'updated'    => '2020-01-01T00:00:00.000000Z',
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
        $this->assertEquals(Carbon::parse($mocked_response['updated']), $response->updated);
    }

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
     * @throws GuzzleException
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
    }

    /**
     * @throws GuzzleException
     */
    public function testCandles_noDataNextTime_success()
    {
        $mocked_response = [
            's'        => 'no_data',
            'nextTime' => 1659326400,
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
        $this->assertEquals($mocked_response['s'], $response->status);
        $this->assertEquals($mocked_response['nextTime'], $response->next_time);
    }

    public function testExceptionHandling_throwsGuzzleException()
    {
        $this->setMockResponses([
            new RequestException("Error Communicating with Server", new Request('GET', 'test')),
        ]);

        $this->expectException(\GuzzleHttp\Exception\GuzzleException::class);
        $response = $this->client->indices->quote("INVALID");
    }
}
