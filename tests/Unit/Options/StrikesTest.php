<?php

namespace MarketDataApp\Tests\Unit\Options;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\Strikes;
use MarketDataApp\Enums\Format;

/**
 * Unit tests for the Options Strikes endpoint.
 */
class StrikesTest extends OptionsTestCase
{
    /**
     * Test the strikes endpoint for a successful response.
     */
    public function testStrikes_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
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

        $this->assertInstanceOf(Strikes::class, $response);
        $this->assertEquals(Carbon::parse($mocked_response['updated']), $response->updated);
        $this->assertEquals($mocked_response['2023-01-20'], $response->dates['2023-01-20']);
    }

    /**
     * Test the strikes endpoint for a successful CSV response.
     */
    public function testStrikes_csv_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, updated, 2023-01-20\r\n";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->strikes(
            symbol: 'AAPL',
            expiration: '2023-01-20',
            date: '2023-01-03',
            parameters: new Parameters(Format::CSV),
        );

        $this->assertInstanceOf(Strikes::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the strikes endpoint for a successful 'no data' response.
     */
    public function testStrikes_noData_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
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

        $this->assertInstanceOf(Strikes::class, $response);
        $this->assertEmpty($response->dates);
        $this->assertEquals(Carbon::parse($mocked_response['nextTime']), $response->next_time);
        $this->assertEquals(Carbon::parse($mocked_response['prevTime']), $response->prev_time);
    }

    /**
     * Test the strikes endpoint with human-readable format.
     */
    public function testStrikes_humanReadable_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
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
}
