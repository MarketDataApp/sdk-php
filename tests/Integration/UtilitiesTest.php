<?php

namespace MarketDataApp\Tests\Integration;

use Carbon\Carbon;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatus;
use MarketDataApp\Endpoints\Responses\Utilities\Headers;
use MarketDataApp\Endpoints\Responses\Utilities\ServiceStatus;
use PHPUnit\Framework\TestCase;

class UtilitiesTest extends TestCase
{

    private Client $client;

    protected function setUp(): void
    {
        $token = 'your_api_token';
        $client = new Client($token);
        $this->client = $client;
    }

    public function testApiStatus_success()
    {
        $response = $this->client->utilities->api_status();
        $this->assertInstanceOf(ApiStatus::class, $response);

        $this->assertCount(4, $response->services);

        // Verify each item in the response is an object of the correct type and has the correct values.
        $this->assertInstanceOf(ServiceStatus::class, $response->services[0]);
        $this->assertEquals('string', gettype($response->services[0]->service));
        $this->assertEquals('string', gettype($response->services[0]->status));
        $this->assertEquals('double', gettype($response->services[0]->uptime_percentage_30d));
        $this->assertEquals('double', gettype($response->services[0]->uptime_percentage_90d));
        $this->assertInstanceOf(Carbon::class, $response->services[0]->updated);
    }

    public function testHeaders_success()
    {
        $response = $this->client->utilities->headers();
        $this->assertInstanceOf(Headers::class, $response);
    }
}
