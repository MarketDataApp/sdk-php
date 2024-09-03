<?php

namespace MarketDataApp\Tests\Unit;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatus;
use MarketDataApp\Endpoints\Responses\Utilities\Headers;
use MarketDataApp\Endpoints\Responses\Utilities\ServiceStatus;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the Utilities endpoints of the MarketDataApp.
 *
 * This class tests the functionality of the API status and headers endpoints.
 */
class UtilitiesTest extends TestCase
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
        $token = 'your_api_token';
        $client = new Client($token);
        $this->client = $client;
    }

    /**
     * Test the API status endpoint for a successful response.
     *
     * @return void
     */
    public function testApiStatus_success()
    {
        $mocked_response = [
            's'            => 'ok',
            'service'      => ['Customer Dashboard', 'Historical Data API', 'Real-time Data API', 'Website'],
            'status'       => ['online', 'online', 'online', 'online'],
            'online'       => [true, true, true, true],
            'uptimePct30d' => [1, 0.99769, 0.99804, 1],
            'uptimePct90d' => [1, 0.99866, 0.99919, 1],
            'updated'      => [1708972840, 1708972840, 1708972840, 1708972840]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->utilities->api_status();
        $this->assertInstanceOf(ApiStatus::class, $response);

        $this->assertCount(4, $response->services);

        // Verify each item in the response is an object of the correct type and has the correct values.
        for ($i = 0; $i < count($response->services); $i++) {
            $this->assertInstanceOf(ServiceStatus::class, $response->services[$i]);
            $this->assertEquals($mocked_response['service'][$i], $response->services[$i]->service);
            $this->assertEquals($mocked_response['status'][$i], $response->services[$i]->status);
            $this->assertEquals($mocked_response['uptimePct30d'][$i], $response->services[$i]->uptime_percentage_30d);
            $this->assertEquals($mocked_response['uptimePct90d'][$i], $response->services[$i]->uptime_percentage_90d);
            $this->assertEquals(Carbon::createFromTimestamp($mocked_response['updated'][$i]),
                $response->services[$i]->updated);
        }
    }

    /**
     * Test the headers endpoint for a successful response.
     *
     * @return void
     */
    public function testHeaders_success()
    {
        $mocked_response = [
            'accept'            => '*/*',
            'accept-encoding'   => 'gzip',
            'authorization'     => 'Bearer *******************************************************YKT0',
            'cache-control'     => 'no-cache',
            'cf-connecting-ip'  => '132.43.100.7',
            'cf-ipcountry'      => 'US',
            'cf-ray'            => '85bc0c2bef389lo9',
            'cf-visitor'        => '{"scheme"=>"https"}',
            'connection'        => 'Keep-Alive',
            'host'              => 'api.marketdata.app',
            'postman-token'     => '09efc901-97q5-46h0-930a-7618d910b9f8',
            'user-agent'        => 'PostmanRuntime/7.36.3',
            'x-forwarded-proto' => 'https',
            'x-real-ip'         => '53.43.221.49'
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->utilities->headers();
        $this->assertInstanceOf(Headers::class, $response);
        foreach ($mocked_response as $key => $value) {
            $this->assertEquals($value, $response->{$key});
        }
    }
}
