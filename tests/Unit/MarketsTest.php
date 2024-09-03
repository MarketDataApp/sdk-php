<?php

namespace MarketDataApp\Tests\Unit;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Markets\Status;
use MarketDataApp\Endpoints\Responses\Markets\Statuses;
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
        $token = "your_api_token";
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
}
