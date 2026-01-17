<?php

namespace MarketDataApp\Tests\Unit;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatus;
use MarketDataApp\Endpoints\Responses\Utilities\Headers;
use MarketDataApp\Endpoints\Responses\Utilities\ServiceStatus;
use MarketDataApp\Endpoints\Responses\Utilities\User;
use MarketDataApp\Exceptions\ApiException;
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

    /**
     * Test the user endpoint for a successful response.
     *
     * @return void
     */
    public function testUser_success()
    {
        $resetTimestamp = 1734567890;
        $mocked_headers = [
            'x-api-ratelimit-limit'     => ['60'],
            'x-api-ratelimit-remaining' => ['59'],
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $response = $this->client->utilities->user();
        $this->assertInstanceOf(User::class, $response);
        $this->assertInstanceOf(\MarketDataApp\RateLimits::class, $response->rate_limits);

        // Verify all rate limit fields are correctly extracted and converted
        $this->assertEquals(60, $response->rate_limits->limit);
        $this->assertEquals(59, $response->rate_limits->remaining);
        $this->assertEquals(1, $response->rate_limits->consumed);
        
        // Verify that reset is properly converted to Carbon datetime
        $this->assertInstanceOf(Carbon::class, $response->rate_limits->reset);
        $this->assertEquals(
            Carbon::createFromTimestamp($resetTimestamp),
            $response->rate_limits->reset
        );
    }

    /**
     * Test the user endpoint with missing rate limit headers.
     *
     * @return void
     */
    public function testUser_missingHeaders_throwsException()
    {
        // Response with no rate limit headers
        $this->setMockResponses([new Response(200, [], json_encode([]))]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("Rate limit headers not found in response");
        
        $this->client->utilities->user();
    }

    /**
     * Test the user endpoint with partial rate limit headers.
     *
     * @return void
     */
    public function testUser_partialHeaders_throwsException()
    {
        // Response with only some headers (missing x-api-ratelimit-reset)
        $mocked_headers = [
            'x-api-ratelimit-limit'     => ['60'],
            'x-api-ratelimit-remaining' => ['59'],
            'x-api-ratelimit-consumed'  => ['1'],
            // Missing x-api-ratelimit-reset
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("Rate limit headers not found in response");
        
        $this->client->utilities->user();
    }

    /**
     * Test the user endpoint with invalid non-numeric header values.
     *
     * @return void
     */
    public function testUser_invalidNumericHeaders_throwsException()
    {
        // Headers with non-numeric values
        $mocked_headers = [
            'x-api-ratelimit-limit'     => ['abc'], // Invalid
            'x-api-ratelimit-remaining' => ['59'],
            'x-api-ratelimit-reset'     => ['1734567890'],
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("Rate limit headers not found in response");
        
        $this->client->utilities->user();
    }

    /**
     * Test the user endpoint with empty header values.
     *
     * @return void
     */
    public function testUser_emptyHeaderValues_throwsException()
    {
        // Headers present but with empty string values
        $mocked_headers = [
            'x-api-ratelimit-limit'     => [''],
            'x-api-ratelimit-remaining' => ['59'],
            'x-api-ratelimit-reset'     => ['1734567890'],
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("Rate limit headers not found in response");
        
        $this->client->utilities->user();
    }

    /**
     * Test the user endpoint with case-insensitive header matching.
     *
     * @return void
     */
    public function testUser_caseInsensitiveHeaders_success()
    {
        $resetTimestamp = 1734567890;
        // Headers with different case
        $mocked_headers = [
            'X-Api-Ratelimit-Limit'     => ['60'], // Different case
            'X-API-RATELIMIT-REMAINING' => ['59'], // All uppercase
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp], // Lowercase
            'X-Api-Ratelimit-Consumed'  => ['1'], // Mixed case
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $response = $this->client->utilities->user();
        $this->assertInstanceOf(User::class, $response);
        $this->assertEquals(60, $response->rate_limits->limit);
        $this->assertEquals(59, $response->rate_limits->remaining);
        $this->assertEquals(1, $response->rate_limits->consumed);
    }

    /**
     * Test the user endpoint with different numeric formats.
     *
     * @return void
     */
    public function testUser_differentNumericFormats_success()
    {
        $resetTimestamp = 1734567890;
        // Headers with string numbers that can be converted
        $mocked_headers = [
            'x-api-ratelimit-limit'     => [' 60 '], // With spaces
            'x-api-ratelimit-remaining' => ['059'], // With leading zero
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $response = $this->client->utilities->user();
        $this->assertInstanceOf(User::class, $response);
        // Should convert correctly to integers (spaces trimmed, leading zeros handled)
        $this->assertEquals(60, $response->rate_limits->limit);
        $this->assertEquals(59, $response->rate_limits->remaining); // Leading zero removed
    }

    /**
     * Test the user endpoint with invalid timestamp format.
     *
     * @return void
     */
    public function testUser_invalidTimestamp_throwsException()
    {
        // Invalid timestamp (non-numeric)
        $mocked_headers = [
            'x-api-ratelimit-limit'     => ['60'],
            'x-api-ratelimit-remaining' => ['59'],
            'x-api-ratelimit-reset'     => ['invalid'], // Invalid timestamp
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("Rate limit headers not found in response");
        
        $this->client->utilities->user();
    }

    /**
     * Test the user endpoint with boundary values.
     *
     * @return void
     */
    public function testUser_boundaryValues_success()
    {
        $resetTimestamp = 2147483647; // Max 32-bit timestamp (year 2038)
        // Boundary values: zero and large numbers
        $mocked_headers = [
            'x-api-ratelimit-limit'     => ['0'], // Zero limit
            'x-api-ratelimit-remaining' => ['0'], // Zero remaining
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['0'], // Zero consumed
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $response = $this->client->utilities->user();
        $this->assertInstanceOf(User::class, $response);
        $this->assertEquals(0, $response->rate_limits->limit);
        $this->assertEquals(0, $response->rate_limits->remaining);
        $this->assertEquals(0, $response->rate_limits->consumed);
        $this->assertEquals(
            Carbon::createFromTimestamp($resetTimestamp),
            $response->rate_limits->reset
        );
    }
}
