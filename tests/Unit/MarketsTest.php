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

class MarketsTest extends TestCase
{

    use MockResponses;

    private Client $client;

    protected function setUp(): void
    {
        $token = "your_api_token";
        $client = new Client($token);
        $this->client = $client;
    }

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
