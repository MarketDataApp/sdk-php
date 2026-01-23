<?php

namespace MarketDataApp\Tests\Unit\Options;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\Expirations;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;

/**
 * Unit tests for the Options Expirations endpoint.
 */
class ExpirationsTest extends OptionsTestCase
{
    /**
     * Test the expirations endpoint for a successful response.
     */
    public function testExpirations_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'           => 'ok',
            'expirations' => ['2022-09-23', '2022-09-30'],
            'updated'     => 1663704000
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->expirations('AAPL');

        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertCount(2, $response->expirations);
        $this->assertEquals(Carbon::parse($mocked_response['updated']), $response->updated);

        for ($i = 0; $i < count($response->expirations); $i++) {
            $this->assertEquals(Carbon::parse($mocked_response['expirations'][$i]), $response->expirations[$i]);
        }
    }

    /**
     * Test the expirations endpoint for a successful CSV response.
     */
    public function testExpirations_csv_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, expirations, updated\r\n";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->expirations(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the expirations endpoint for a successful 'no data' response.
     */
    public function testExpirations_noData_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'        => 'no_data',
            'nextTime' => 1663704000,
            'prevTime' => 1663705000
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->expirations('AAPL');

        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertEmpty($response->expirations);
        $this->assertEquals(Carbon::parse($mocked_response['nextTime']), $response->next_time);
        $this->assertEquals(Carbon::parse($mocked_response['prevTime']), $response->prev_time);
    }

    /**
     * Test the expirations endpoint with human-readable format.
     */
    public function testExpirations_humanReadable_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'Expirations' => ['2022-09-23', '2022-09-30'],
            'Date' => 1663704000
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->expirations(
            'AAPL',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(2, $response->expirations);
        $this->assertEquals(Carbon::parse($mocked_response['Date']), $response->updated);
    }

    /**
     * Test that date_format parameter can be used with CSV format for options.
     */
    public function testParameters_dateFormat_withCsv_success(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, symbol, ask, bid";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->expirations(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertTrue($response->isCsv());
    }

    /**
     * Test that date_format parameter with JSON format throws InvalidArgumentException.
     */
    public function testParameters_dateFormat_withJson_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('date_format parameter can only be used with CSV or HTML format');

        new Parameters(format: Format::JSON, date_format: DateFormat::TIMESTAMP);
    }

    /**
     * Test expirations endpoint with invalid strike (zero).
     */
    public function testExpirations_invalidStrike_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a positive integer');

        $this->client->options->expirations('AAPL', strike: 0);
    }
}
