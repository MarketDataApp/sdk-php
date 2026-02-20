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

    /**
     * Test that strikes properties are accessible for CSV responses (BUG-013 fix).
     *
     * CSV responses trigger an early return in the constructor. Properties should
     * have default values to prevent "uninitialized property" errors.
     */
    public function testStrikes_csv_propertiesAccessible(): void
    {
        // Mock response: NOT from real API output (uses synthetic CSV data)
        $csvResponse = "updated,2023-01-20\n1663704000,30.0";
        $this->setMockResponses([new Response(200, [], $csvResponse)]);

        $response = $this->client->options->strikes(
            symbol: 'AAPL',
            expiration: '2023-01-20',
            date: '2023-01-03',
            parameters: new Parameters(format: Format::CSV)
        );

        // These should NOT throw "uninitialized property" errors
        $this->assertEquals('no_data', $response->status);
        $this->assertIsArray($response->dates);
        $this->assertCount(0, $response->dates);
        $this->assertNull($response->updated);
        $this->assertNull($response->next_time);
        $this->assertNull($response->prev_time);
    }

    /**
     * Test that strikes properties are accessible for no_data responses without next/prev times (BUG-013 fix).
     *
     * Some no_data responses may not include nextTime/prevTime fields.
     */
    public function testStrikes_noData_withoutTimes_propertiesAccessible(): void
    {
        // Mock response: NOT from real API output (uses synthetic no_data response)
        $noDataResponse = ['s' => 'no_data'];
        $this->setMockResponses([new Response(200, [], json_encode($noDataResponse))]);

        $response = $this->client->options->strikes(
            symbol: 'INVALID',
            expiration: '2099-01-20',
            date: '2099-01-03'
        );

        // These should NOT throw "uninitialized property" errors
        $this->assertEquals('no_data', $response->status);
        $this->assertIsArray($response->dates);
        $this->assertCount(0, $response->dates);
        $this->assertNull($response->updated);
        $this->assertNull($response->next_time);
        $this->assertNull($response->prev_time);
    }

    /**
     * Test that strikes ignores unknown metadata keys in regular format (Issue #51 fix).
     *
     * When the API returns additional metadata fields, they should not be
     * included in the dates array.
     */
    public function testStrikes_regularFormat_ignoresUnknownKeys(): void
    {
        // Mock response: NOT from real API output (synthetic data with unknown keys)
        $mocked_response = [
            's'          => 'ok',
            'updated'    => 1663704000,
            '2023-01-20' => [30.0, 35.0],
            'Version'    => '1.0',  // Unknown metadata field - should be ignored
            'RequestId'  => 'abc123',  // Unknown metadata field - should be ignored
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->strikes(
            symbol: 'AAPL',
            expiration: '2023-01-20',
            date: '2023-01-03'
        );

        $this->assertInstanceOf(Strikes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->dates);
        $this->assertArrayHasKey('2023-01-20', $response->dates);
        $this->assertArrayNotHasKey('Version', $response->dates);
        $this->assertArrayNotHasKey('RequestId', $response->dates);
    }

    /**
     * Test that strikes ignores unknown metadata keys in human-readable format (Issue #51 fix).
     *
     * When the API returns additional metadata fields, they should not be
     * included in the dates array.
     */
    public function testStrikes_humanReadable_ignoresUnknownKeys(): void
    {
        // Mock response: NOT from real API output (synthetic data with unknown keys)
        $mocked_response = [
            '2023-01-20' => [30.0, 35.0],
            'Date'       => 1663704000,
            'Version'    => '1.0',  // Unknown metadata field - should be ignored
            'Updated'    => 1663704001,  // Similar to Date, should be ignored
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
        $this->assertCount(1, $response->dates);
        $this->assertArrayHasKey('2023-01-20', $response->dates);
        $this->assertArrayNotHasKey('Version', $response->dates);
        $this->assertArrayNotHasKey('Updated', $response->dates);
    }
}
