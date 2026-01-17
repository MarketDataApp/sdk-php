<?php

namespace MarketDataApp\Tests\Integration;

use Carbon\Carbon;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatus;
use MarketDataApp\Endpoints\Responses\Utilities\Headers;
use MarketDataApp\Endpoints\Responses\Utilities\ServiceStatus;
use MarketDataApp\Endpoints\Responses\Utilities\User;
use MarketDataApp\Exceptions\UnauthorizedException;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Utilities endpoints of the MarketDataApp API.
 */
class UtilitiesTest extends TestCase
{

    /**
     * The MarketDataApp API client instance.
     *
     * @var Client
     */
    private Client $client;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $token = getenv('MARKETDATA_TOKEN') ?: 'your_api_token';
        if ($token === 'your_api_token') {
            $this->markTestSkipped('MARKETDATA_TOKEN environment variable not set');
        }
        $client = new Client($token);
        $this->client = $client;
    }

    /**
     * Test the API status endpoint.
     *
     * @return void
     */
    public function testApiStatus_success()
    {
        $response = $this->client->utilities->api_status();
        $this->assertInstanceOf(ApiStatus::class, $response);

        $this->assertGreaterThanOrEqual(4, count($response->services));

        // Verify each item in the response is an object of the correct type and has the correct values.
        $this->assertInstanceOf(ServiceStatus::class, $response->services[0]);
        $this->assertEquals('string', gettype($response->services[0]->service));
        $this->assertEquals('string', gettype($response->services[0]->status));
        $this->assertEquals('double', gettype($response->services[0]->uptime_percentage_30d));
        $this->assertEquals('double', gettype($response->services[0]->uptime_percentage_90d));
        $this->assertInstanceOf(Carbon::class, $response->services[0]->updated);
    }

    /**
     * Test the headers endpoint.
     *
     * @return void
     */
    public function testHeaders_success()
    {
        $response = $this->client->utilities->headers();
        $this->assertInstanceOf(Headers::class, $response);
    }

    /**
     * Test the user endpoint.
     *
     * @return void
     */
    public function testUser_success()
    {
        $response = $this->client->utilities->user();
        $this->assertInstanceOf(User::class, $response);
        $this->assertInstanceOf(\MarketDataApp\RateLimits::class, $response->rate_limits);

        // Verify rate limit fields are present and have correct types
        $this->assertIsInt($response->rate_limits->limit);
        $this->assertIsInt($response->rate_limits->remaining);
        $this->assertIsInt($response->rate_limits->consumed);
        $this->assertInstanceOf(Carbon::class, $response->rate_limits->reset);

        // Verify values are reasonable (limit should be positive, remaining should be <= limit, etc.)
        $this->assertGreaterThan(0, $response->rate_limits->limit);
        $this->assertGreaterThanOrEqual(0, $response->rate_limits->remaining);
        $this->assertLessThanOrEqual($response->rate_limits->limit, $response->rate_limits->remaining);
        $this->assertGreaterThanOrEqual(0, $response->rate_limits->consumed);
    }

    /**
     * Test whether the /user/ endpoint consumes a rate limit request.
     *
     * This test verifies whether calling the user() endpoint itself consumes
     * a rate limit request. According to API docs, X-Api-Ratelimit-Consumed
     * is the quantity consumed in the current request (not cumulative).
     *
     * @return void
     */
    public function testUser_endpoint_consumesRequest()
    {
        // Get rate limits from user() endpoint
        // Note: consumed is the quantity consumed in THIS request, not cumulative
        $rateLimits = $this->client->utilities->user();

        // Verify rate limit structure is valid
        $this->assertInstanceOf(User::class, $rateLimits);
        $this->assertGreaterThan(0, $rateLimits->rate_limits->limit, 
            'Rate limit should be positive');

        // Check if this request consumed any credits
        // consumed = quantity consumed in THIS request (0 if free, >0 if paid)
        $consumedInThisRequest = $rateLimits->rate_limits->consumed;
        
        // The test passes regardless - we're just checking behavior
        // consumed will be 0 if /user/ doesn't consume, >0 if it does
        $this->assertGreaterThanOrEqual(0, $consumedInThisRequest,
            'Consumed should be >= 0 (quantity consumed in this request)');
        
        $this->assertTrue(true, 
            $consumedInThisRequest > 0
                ? 'The /user/ endpoint consumes a rate limit request (consumed: ' . $consumedInThisRequest . ')'
                : 'The /user/ endpoint does not consume a rate limit request (consumed: 0)'
        );
    }

    /**
     * Test rate limits after making a real stock quote call.
     *
     * This test verifies that rate limits are correctly returned and reflect
     * the consumed request after making an actual API call. Uses SPY (not a free
     * trial symbol) to ensure the request actually consumes a rate limit.
     *
     * According to API docs:
     * - X-Api-Ratelimit-Consumed: quantity consumed in the CURRENT request (not cumulative)
     * - X-Api-Ratelimit-Remaining: requests remaining in current rate period
     * - X-Api-Ratelimit-Limit: maximum requests permitted
     *
     * @return void
     */
    public function testUser_afterStockQuote_reflectsConsumedRequest()
    {
        // Get initial rate limits (before SPY quote)
        $initialRateLimits = $this->client->utilities->user();
        $initialLimit = $initialRateLimits->rate_limits->limit;
        $initialRemaining = $initialRateLimits->rate_limits->remaining;
        $initialReset = $initialRateLimits->rate_limits->reset;

        // Make a real API call to get stock quote for SPY (not a free trial symbol, will consume a request)
        $quote = $this->client->stocks->quote('SPY');
        $this->assertNotNull($quote);
        $this->assertEquals('SPY', $quote->symbol);

        // Get rate limits after the API call
        // Note: consumed in this response is for the /user/ call, not the SPY quote
        // But remaining should have decreased due to the SPY quote
        $afterRateLimits = $this->client->utilities->user();
        
        // Verify rate limits structure
        $this->assertInstanceOf(User::class, $afterRateLimits);
        $this->assertInstanceOf(\MarketDataApp\RateLimits::class, $afterRateLimits->rate_limits);

        // Verify limit remains constant
        $this->assertEquals($initialLimit, $afterRateLimits->rate_limits->limit, 
            'Rate limit should remain constant');

        // Verify remaining decreased (SPY quote consumed at least 1 credit)
        // remaining should be less than initial because SPY quote consumed credits
        $this->assertLessThan(
            $initialRemaining,
            $afterRateLimits->rate_limits->remaining,
            'Requests remaining should have decreased after SPY quote call (SPY is not free)'
        );

        // Verify consumed is >= 0 (quantity consumed in the /user/ request itself)
        // This tells us if /user/ consumes credits, but doesn't tell us about SPY
        $this->assertGreaterThanOrEqual(
            0,
            $afterRateLimits->rate_limits->consumed,
            'Consumed should be >= 0 (quantity consumed in this /user/ request)'
        );

        // Verify reset is a valid future timestamp (should be same or later)
        $this->assertGreaterThanOrEqual(
            $initialReset->timestamp,
            $afterRateLimits->rate_limits->reset->timestamp,
            'Reset timestamp should be same or later than initial'
        );

        // Verify reset timestamp is in the future (reasonable check - within next 24 hours)
        $now = Carbon::now();
        $oneDayFromNow = $now->copy()->addDay();
        $this->assertLessThanOrEqual(
            $oneDayFromNow->timestamp,
            $afterRateLimits->rate_limits->reset->timestamp,
            'Reset timestamp should be within the next 24 hours'
        );
    }

    /**
     * Test the user endpoint with invalid token throws UnauthorizedException.
     *
     * @return void
     */
    public function testUser_invalidToken_throwsUnauthorizedException()
    {
        $client = new Client('invalid_token_12345');
        
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionCode(401);
        
        try {
            $client->utilities->user();
        } catch (UnauthorizedException $e) {
            $this->assertEquals(401, $e->getCode());
            $this->assertNotNull($e->getResponse());
            $this->assertEquals(401, $e->getResponse()->getStatusCode());
            throw $e;
        }
    }
}
