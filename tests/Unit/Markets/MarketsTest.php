<?php

namespace MarketDataApp\Tests\Unit\Markets;

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
        // Save original token state before clearing
        $this->saveMarketDataTokenState();
        
        // Clear MARKETDATA_TOKEN environment variable to ensure empty token is used.
        // This prevents real API calls during Client construction by ensuring
        // _setup_rate_limits() skips the /user/ endpoint validation call.
        $this->clearMarketDataToken();
        
        // Use empty token for unit tests to skip validation (tests use mocks anyway)
        $token = "";
        $client = new Client($token);
        $this->client = $client;
    }

    /**
     * Restore original environment variable state after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->restoreMarketDataTokenState();
        parent::tearDown();
    }

    /**
     * Test the status endpoint for a successful response.
     *
     * @return void
     */
    public function testStatus_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
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
        // Mock response: NOT from real API output (synthetic/test data)
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
        // Mock response: NOT from real API output (synthetic/test data)
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
     * Test the status endpoint with human-readable format and non-numeric date string.
     * This covers the Carbon::parse() path for non-numeric date values.
     *
     * @return void
     */
    public function testStatus_humanReadable_nonNumericDate_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'Date' => '2023-04-05',
            'Status' => 'open'
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->markets->status(
            date: '2023-04-05',
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
     * Test the status endpoint with human-readable format where Date field is an array.
     * This covers the array handling path when Date is an array.
     *
     * @return void
     */
    public function testStatus_humanReadable_dateAsArray_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'Date' => ['2023-04-05'],
            'Status' => 'open'
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->markets->status(
            date: '2023-04-05',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Statuses::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->statuses);
        $this->assertInstanceOf(Status::class, $response->statuses[0]);
        $this->assertEquals(Carbon::parse($mocked_response['Date'][0]), $response->statuses[0]->date);
        $this->assertEquals($mocked_response['Status'], $response->statuses[0]->status);
    }

    /**
     * Test multi-date human-readable response returns all dates (BUG-017 fix).
     *
     * When querying multiple dates with human-readable format, all dates should
     * be returned, not just the first one.
     *
     * @return void
     */
    public function testStatus_humanReadable_multiDate_success(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'Date' => ['2023-04-05', '2023-04-06', '2023-04-07'],
            'Status' => ['open', 'closed', 'open']
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->markets->status(
            from: '2023-04-05',
            to: '2023-04-07',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Statuses::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(3, $response->statuses);

        for ($i = 0; $i < 3; $i++) {
            $this->assertInstanceOf(Status::class, $response->statuses[$i]);
            $this->assertEquals(Carbon::parse($mocked_response['Date'][$i]), $response->statuses[$i]->date);
            $this->assertEquals($mocked_response['Status'][$i], $response->statuses[$i]->status);
        }
    }

    /**
     * Test multi-date human-readable response with Unix timestamps (BUG-017 fix).
     *
     * @return void
     */
    public function testStatus_humanReadable_multiDate_timestamps_success(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'Date' => [1680652800, 1680739200],
            'Status' => ['open', 'closed']
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->markets->status(
            from: '2023-04-05',
            to: '2023-04-06',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Statuses::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(2, $response->statuses);

        for ($i = 0; $i < 2; $i++) {
            $this->assertInstanceOf(Status::class, $response->statuses[$i]);
            $this->assertEquals(
                Carbon::createFromTimestamp($mocked_response['Date'][$i]),
                $response->statuses[$i]->date
            );
            $this->assertEquals($mocked_response['Status'][$i], $response->statuses[$i]->status);
        }
    }

    /**
     * Test that date_format parameter can be used with CSV format for markets.
     *
     * @return void
     */
    public function testParameters_dateFormat_withCsv_success(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
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

    /**
     * Test that market status properties are accessible for CSV responses (BUG-013 fix).
     *
     * CSV responses trigger an early return in the constructor. Properties should
     * have default values to prevent "uninitialized property" errors.
     */
    public function testStatus_csv_propertiesAccessible(): void
    {
        // Mock response: NOT from real API output (uses synthetic CSV data)
        $csvResponse = "date,status\n1680580800,open";
        $this->setMockResponses([new Response(200, [], $csvResponse)]);

        $response = $this->client->markets->status(
            date: '1680580800',
            parameters: new Parameters(format: Format::CSV)
        );

        // These should NOT throw "uninitialized property" errors
        $this->assertEquals('no_data', $response->status);
        $this->assertIsArray($response->statuses);
        $this->assertCount(0, $response->statuses);
    }
}
