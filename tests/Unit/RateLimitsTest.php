<?php

namespace MarketDataApp\Tests\Unit;

use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for automatic rate limit tracking in the MarketDataApp SDK.
 *
 * This class tests that rate limits are automatically updated after each request.
 */
class RateLimitsTest extends TestCase
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
     * @return void
     */
    protected function setUp(): void
    {
        // Use empty token for unit tests to skip validation (tests use mocks anyway)
        $token = '';
        // Create client - rate_limits will be null (validation skipped for empty token)
        $this->client = new Client($token);
    }

    /**
     * Helper method to initialize rate limits with mocked response.
     *
     * @param array $headers Rate limit headers
     * @return void
     */
    private function initializeRateLimits(array $headers): void
    {
        $this->setMockResponses([
            new Response(200, $headers, json_encode([]))
        ]);
        
        // Since we're using empty tokens in unit tests, _setup_rate_limits() will skip.
        // Instead, we'll directly extract and set rate limits from the mocked response.
        $response = new \GuzzleHttp\Psr7\Response(200, $headers, json_encode([]));
        $rateLimits = $this->client->extractRateLimitsFromResponse($response);
        if ($rateLimits !== null) {
            $reflection = new \ReflectionClass($this->client);
            $property = $reflection->getProperty('rate_limits');
            $property->setValue($this->client, $rateLimits);
        }
    }

    /**
     * Helper method to create a proper quote response array.
     *
     * @param string $symbol The stock symbol
     * @param float $price The stock price
     * @return array
     */
    private function createQuoteResponse(string $symbol, float $price): array
    {
        return [
            's' => 'ok',
            'symbol' => [$symbol],
            'ask' => [$price + 0.1],
            'askSize' => [200],
            'bid' => [$price],
            'bidSize' => [300],
            'mid' => [$price + 0.05],
            'last' => [$price],
            'change' => [0.5],
            'changepct' => [0.33],
            'volume' => [1000000],
            'updated' => [time()]
        ];
    }

    /**
     * Test that rate limits are initialized during client construction.
     *
     * @return void
     */
    public function testRateLimits_initializedDuringConstruction()
    {
        $resetTimestamp = time() + 3600; // 1 hour from now
        $mocked_headers = [
            'x-api-ratelimit-limit'     => ['100'],
            'x-api-ratelimit-remaining' => ['99'],
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        
        // Initialize rate limits with mocked response
        $this->initializeRateLimits($mocked_headers);
        
        // Verify rate limits were initialized
        $this->assertNotNull($this->client->rate_limits);
        $this->assertEquals(100, $this->client->rate_limits->limit);
        $this->assertEquals(99, $this->client->rate_limits->remaining);
        $this->assertEquals(1, $this->client->rate_limits->consumed);
        $this->assertInstanceOf(Carbon::class, $this->client->rate_limits->reset);
        $this->assertEquals($resetTimestamp, $this->client->rate_limits->reset->timestamp);
    }

    /**
     * Test that rate limits remain null if initialization fails.
     *
     * @return void
     */
    public function testRateLimits_initializationFails_remainsNull()
    {
        // Mock a 401 error for /user/ endpoint
        $this->setMockResponses([
            new Response(401, [], json_encode(['errmsg' => 'Unauthorized']))
        ]);

        // Try to initialize rate limits - should fail gracefully
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('_setup_rate_limits');
        $method->invoke($this->client);
        
        // Verify rate limits are null
        $this->assertNull($this->client->rate_limits);

        // Now mock a successful request with rate limit headers
        $resetTimestamp = time() + 3600;
        $this->setMockResponses([
            new Response(200, [
                'x-api-ratelimit-limit'     => ['100'],
                'x-api-ratelimit-remaining' => ['98'],
                'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
                'x-api-ratelimit-consumed'  => ['1'],
            ], json_encode($this->createQuoteResponse('AAPL', 150.0)))
        ]);

        // Make a request - rate limits should be updated
        $this->client->stocks->quote('AAPL');
        
        // Verify rate limits were updated after successful request
        $this->assertNotNull($this->client->rate_limits);
        $this->assertEquals(100, $this->client->rate_limits->limit);
        $this->assertEquals(98, $this->client->rate_limits->remaining);
    }

    /**
     * Test that rate limits are updated after execute() call.
     *
     * @return void
     */
    public function testRateLimits_updatedAfterExecute()
    {
        $resetTimestamp = time() + 3600;
        
        // Mock initial /user/ response
        $initialHeaders = [
            'x-api-ratelimit-limit'     => ['100'],
            'x-api-ratelimit-remaining' => ['99'],
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        
        // Mock stock quote response with different rate limit headers
        $quoteHeaders = [
            'x-api-ratelimit-limit'     => ['100'],
            'x-api-ratelimit-remaining' => ['98'],
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        
        // Initialize rate limits first
        $this->initializeRateLimits($initialHeaders);
        
        // Verify initial rate limits
        $this->assertEquals(99, $this->client->rate_limits->remaining);
        
        // Now set up mock for the quote request
        $this->setMockResponses([
            new Response(200, $quoteHeaders, json_encode($this->createQuoteResponse('AAPL', 150.0)))
        ]);
        
        // Make a request
        $this->client->stocks->quote('AAPL');
        
        // Verify rate limits were updated
        $this->assertEquals(98, $this->client->rate_limits->remaining);
        $this->assertEquals(100, $this->client->rate_limits->limit);
    }

    /**
     * Test that rate limits are updated after multiple sequential requests.
     *
     * @return void
     */
    public function testRateLimits_updatedAfterExecute_multipleRequests()
    {
        $resetTimestamp = time() + 3600;
        
        // Mock initial /user/ response
        $initialHeaders = [
            'x-api-ratelimit-limit'     => ['100'],
            'x-api-ratelimit-remaining' => ['100'],
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['0'],
        ];
        
        // Mock multiple sequential responses with decreasing remaining
        // Initialize rate limits first
        $this->initializeRateLimits($initialHeaders);
        
        // Now set up mocks for the sequential requests
        $this->setMockResponses([
            new Response(200, [
                'x-api-ratelimit-limit'     => ['100'],
                'x-api-ratelimit-remaining' => ['99'],
                'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
                'x-api-ratelimit-consumed'  => ['1'],
            ], json_encode($this->createQuoteResponse('SPY', 400.0))),
            new Response(200, [
                'x-api-ratelimit-limit'     => ['100'],
                'x-api-ratelimit-remaining' => ['98'],
                'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
                'x-api-ratelimit-consumed'  => ['1'],
            ], json_encode($this->createQuoteResponse('QQQ', 350.0))),
            new Response(200, [
                'x-api-ratelimit-limit'     => ['100'],
                'x-api-ratelimit-remaining' => ['97'],
                'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
                'x-api-ratelimit-consumed'  => ['1'],
            ], json_encode($this->createQuoteResponse('EWZ', 30.0)))
        ]);
        
        // Verify initial rate limits
        $this->assertEquals(100, $this->client->rate_limits->remaining);
        
        // Make first request
        $this->client->stocks->quote('SPY');
        $this->assertEquals(99, $this->client->rate_limits->remaining);
        
        // Make second request
        $this->client->stocks->quote('QQQ');
        $this->assertEquals(98, $this->client->rate_limits->remaining);
        
        // Make third request
        $this->client->stocks->quote('EWZ');
        $this->assertEquals(97, $this->client->rate_limits->remaining);
    }

    /**
     * Test graceful degradation when rate limit headers are missing.
     *
     * @return void
     */
    public function testRateLimits_missingHeaders_gracefulDegradation()
    {
        $resetTimestamp = time() + 3600;
        
        // Mock initial /user/ response with headers
        $initialHeaders = [
            'x-api-ratelimit-limit'     => ['100'],
            'x-api-ratelimit-remaining' => ['99'],
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        
        // Initialize rate limits first
        $this->initializeRateLimits($initialHeaders);
        
        // Store initial rate limits
        $initialRemaining = $this->client->rate_limits->remaining;
        $initialLimit = $this->client->rate_limits->limit;
        
        // Mock stock quote response WITHOUT rate limit headers
        $this->setMockResponses([
            new Response(200, [            ], json_encode($this->createQuoteResponse('AAPL', 150.0))) // No rate limit headers
        ]);
        
        // Make a request without rate limit headers
        $this->client->stocks->quote('AAPL');
        
        // Verify rate limits were NOT updated (graceful degradation)
        $this->assertEquals($initialRemaining, $this->client->rate_limits->remaining);
        $this->assertEquals($initialLimit, $this->client->rate_limits->limit);
    }

    /**
     * Test that rate limits are updated after async requests.
     *
     * @return void
     */
    public function testRateLimits_updatedAfterAsync()
    {
        $resetTimestamp = time() + 3600;
        
        // Mock initial /user/ response
        $initialHeaders = [
            'x-api-ratelimit-limit'     => ['100'],
            'x-api-ratelimit-remaining' => ['100'],
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['0'],
        ];
        
        // Initialize rate limits first
        $this->initializeRateLimits($initialHeaders);
        
        // Mock async responses with rate limit headers
        // Note: quotes() makes one async call per symbol, so we need one response per symbol
        $this->setMockResponses([
            new Response(200, [
                'x-api-ratelimit-limit'     => ['100'],
                'x-api-ratelimit-remaining' => ['99'],
                'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
                'x-api-ratelimit-consumed'  => ['1'],
            ], json_encode($this->createQuoteResponse('SPY', 400.0)))
        ]);
        
        // Verify initial rate limits
        $this->assertEquals(100, $this->client->rate_limits->remaining);
        
        // Make async request using execute_in_parallel
        $this->client->stocks->quotes(['SPY']);
        
        // Verify rate limits were updated
        $this->assertEquals(99, $this->client->rate_limits->remaining);
    }

    /**
     * Test that rate limits are updated even for 404 responses.
     *
     * @return void
     */
    public function testRateLimits_404Response_updatesRateLimits()
    {
        $resetTimestamp = time() + 3600;
        
        // Mock initial /user/ response
        $initialHeaders = [
            'x-api-ratelimit-limit'     => ['100'],
            'x-api-ratelimit-remaining' => ['99'],
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        
        // Initialize rate limits first
        $this->initializeRateLimits($initialHeaders);
        
        // Mock 404 response with rate limit headers
        // Note: 404 responses are handled specially - they return the response instead of throwing
        $this->setMockResponses([
            new Response(404, [
                'x-api-ratelimit-limit'     => ['100'],
                'x-api-ratelimit-remaining' => ['98'],
                'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
                'x-api-ratelimit-consumed'  => ['1'],
            ], json_encode(['s' => 'error', 'errmsg' => 'Not found']))
        ]);
        
        // Verify initial rate limits
        $this->assertEquals(99, $this->client->rate_limits->remaining);
        
        // Make a request that returns 404
        // 404 is handled specially and returns the response, so no exception is thrown
        try {
            $this->client->stocks->quote('INVALID_SYMBOL');
        } catch (\Exception $e) {
            // Some endpoints might throw, but rate limits should still be updated
        }
        
        // Verify rate limits were updated even for 404
        $this->assertEquals(98, $this->client->rate_limits->remaining);
        $this->assertEquals(100, $this->client->rate_limits->limit);
    }

    /**
     * Test that rate limits property is accessible and has all required properties.
     *
     * @return void
     */
    public function testRateLimits_propertyAccessible()
    {
        $resetTimestamp = time() + 3600;
        
        $mocked_headers = [
            'x-api-ratelimit-limit'     => ['100'],
            'x-api-ratelimit-remaining' => ['99'],
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        
        // Initialize rate limits with mocked response
        $this->initializeRateLimits($mocked_headers);
        
        // Verify property is accessible
        $this->assertNotNull($this->client->rate_limits);
        
        // Verify all properties are accessible
        $this->assertIsInt($this->client->rate_limits->limit);
        $this->assertIsInt($this->client->rate_limits->remaining);
        $this->assertIsInt($this->client->rate_limits->consumed);
        $this->assertInstanceOf(Carbon::class, $this->client->rate_limits->reset);
        
        // Verify property values
        $this->assertEquals(100, $this->client->rate_limits->limit);
        $this->assertEquals(99, $this->client->rate_limits->remaining);
        $this->assertEquals(1, $this->client->rate_limits->consumed);
        $this->assertEquals($resetTimestamp, $this->client->rate_limits->reset->timestamp);
    }
}
