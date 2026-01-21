<?php

namespace MarketDataApp\Tests\Unit;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Markets\Status;
use MarketDataApp\Endpoints\Responses\Markets\Statuses;
use InvalidArgumentException;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the Markets endpoints of the MarketDataApp.
 *
 * This class tests the functionality of the market status endpoint.
 */
class MarketsTest extends TestCase
{

    use MockResponses;

    /**
     * The client instance used for testing.
     *
     * @var Client
     */
    private Client $client;

    /**
     * Set up the test environment.
     *
     * This method is called before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        // Use empty token for unit tests to skip validation (tests use mocks anyway)
        $token = "";
        $client = new Client($token);
        $this->client = $client;
    }

    /**
     * Test the status endpoint for a successful response.
     *
     * @return void
     */
    public function testStatus_success()
    {
        $mocked_response = [
            's'      => 'ok',
            'date'   => [1680580800],
            'status' => ['open']
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->markets->status(
            date: '1680580800'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Statuses::class, $response);
        $this->assertCount(1, $response->statuses);

        // Verify each item in the response is an object of the correct type and has the correct values.
        for ($i = 0; $i < count($response->statuses); $i++) {
            $this->assertInstanceOf(Status::class, $response->statuses[$i]);
            $this->assertEquals(Carbon::parse($mocked_response['date'][$i]), $response->statuses[$i]->date);
            $this->assertEquals($mocked_response['status'][$i], $response->statuses[$i]->status);
        }
    }

    /**
     * Test the status endpoint with CSV format for a successful response.
     *
     * @return void
     */
    public function testStatus_csv_success()
    {
        $mocked_response = 's, date, status';
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->markets->status(
            date: '1680580800',
            parameters: new Parameters(Format::CSV)
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Statuses::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the status endpoint with human-readable format.
     *
     * @return void
     */
    public function testStatus_humanReadable_success()
    {
        $mocked_response = [
            'Date' => 1680580800,
            'Status' => 'open'
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->markets->status(
            date: '1680580800',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Statuses::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->statuses);
        $this->assertInstanceOf(Status::class, $response->statuses[0]);
        $this->assertEquals(Carbon::parse($mocked_response['Date']), $response->statuses[0]->date);
        $this->assertEquals($mocked_response['Status'], $response->statuses[0]->status);
    }

    /**
     * Test that date_format parameter can be used with CSV format for markets.
     *
     * @return void
     */
    public function testParameters_dateFormat_withCsv_success(): void
    {
        $mocked_response = 's, date, status';
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->markets->status(
            date: '1680580800',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP)
        );

        $this->assertInstanceOf(Statuses::class, $response);
        $this->assertTrue($response->isCsv());
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test that date_format parameter with JSON format throws InvalidArgumentException.
     *
     * @return void
     */
    public function testParameters_dateFormat_withJson_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('date_format parameter can only be used with CSV or HTML format');

        new Parameters(format: Format::JSON, date_format: DateFormat::UNIX);
    }

    /**
     * Test markets status endpoint with CSV format and dateformat=unix.
     *
     * @return void
     */
    public function testStatus_csv_withDateFormat_unix(): void
    {
        $mocked_response = 's, date, status';
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->markets->status(
            date: '1680580800',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Statuses::class, $response);
        $this->assertTrue($response->isCsv());
    }

    /**
     * Test status endpoint with invalid country code (lowercase).
     */
    public function testStatus_invalidCountryCode_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid country code');

        $this->client->markets->status(country: 'us');
    }

    /**
     * Test status endpoint with invalid country code (wrong length).
     */
    public function testStatus_invalidCountryCodeLength_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid country code');

        $this->client->markets->status(country: 'USA');
    }

    /**
     * Test status endpoint with invalid date range.
     */
    public function testStatus_invalidDateRange_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`from` date must be before `to` date');

        $this->client->markets->status(
            from: '2024-01-31',
            to: '2024-01-01'
        );
    }

    /**
     * Test status endpoint with invalid countback.
     */
    public function testStatus_invalidCountback_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`countback` must be a positive integer');

        $this->client->markets->status(countback: -5);
    }
}
