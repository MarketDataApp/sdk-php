<?php

namespace MarketDataApp\Tests\Integration;

use Carbon\Carbon;
use MarketDataApp\Client;
use MarketDataApp\Exceptions\UnauthorizedException;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for automatic rate limit tracking in the MarketDataApp SDK.
 *
 * These tests make real API calls to verify end-to-end functionality.
 */
class RateLimitsTest extends TestCase
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
        $this->client = new Client($token);
    }

    /**
     * Test that rate limits are initialized during client construction.
     *
     * @return void
     */
    public function testRateLimits_initializedDuringConstruction()
    {
        // Verify that rate limits were initialized during construction
        $this->assertNotNull($this->client->rate_limits, 'Rate limits should be initialized during client construction');
        
        // Verify rate limit values are reasonable
        $this->assertGreaterThan(0, $this->client->rate_limits->limit, 
            'Rate limit should be positive');
        $this->assertGreaterThanOrEqual(0, $this->client->rate_limits->remaining,
            'Requests remaining should be >= 0');
        $this->assertLessThanOrEqual(
            $this->client->rate_limits->limit,
            $this->client->rate_limits->remaining,
            'Requests remaining should be <= limit'
        );
        $this->assertGreaterThanOrEqual(0, $this->client->rate_limits->consumed,
            'Requests consumed should be >= 0');
        
        // Verify reset is a valid future timestamp
        $this->assertInstanceOf(Carbon::class, $this->client->rate_limits->reset,
            'Requests reset should be a Carbon instance');
        
        $now = Carbon::now();
        $oneDayFromNow = $now->copy()->addDay();
        $this->assertLessThanOrEqual(
            $oneDayFromNow->timestamp,
            $this->client->rate_limits->reset->timestamp,
            'Reset timestamp should be within the next 24 hours'
        );
        $this->assertGreaterThanOrEqual(
            $now->timestamp,
            $this->client->rate_limits->reset->timestamp,
            'Reset timestamp should be in the future or present'
        );
    }

    /**
     * Test that rate limits are updated after making a real API request.
     *
     * @return void
     */
    public function testRateLimits_updatedAfterRealRequest()
    {
        // Store initial rate limits
        $initialLimit = $this->client->rate_limits->limit;
        $initialRemaining = $this->client->rate_limits->remaining;
        $initialReset = $this->client->rate_limits->reset;
        
        // Make a real API call (SPY is not a free symbol, will consume a request)
        $quote = $this->client->stocks->quote('SPY');
        $this->assertNotNull($quote);
        $this->assertEquals('SPY', $quote->symbol);
        
        // Verify rate limits were updated
        $this->assertNotNull($this->client->rate_limits, 'Rate limits should still be set after request');
        
        // Verify limit remains constant
        $this->assertEquals($initialLimit, $this->client->rate_limits->limit,
            'Rate limit should remain constant');
        
        // Verify remaining decreased (SPY quote consumed at least 1 credit)
        // Note: If SPY is free for the account, remaining might not decrease
        // But the rate limits should still be updated from the response headers
        $this->assertLessThanOrEqual(
            $initialRemaining,
            $this->client->rate_limits->remaining,
            'Requests remaining should be <= initial (may be same if SPY is free)'
        );
        
        // Verify reset timestamp is valid
        $this->assertInstanceOf(Carbon::class, $this->client->rate_limits->reset);
        $this->assertGreaterThanOrEqual(
            $initialReset->timestamp,
            $this->client->rate_limits->reset->timestamp,
            'Reset timestamp should be same or later than initial'
        );
    }

    /**
     * Test that rate limits are updated after multiple sequential requests.
     *
     * @return void
     */
    public function testRateLimits_updatedAfterMultipleRequests()
    {
        // Store initial rate limits
        $initialRemaining = $this->client->rate_limits->remaining;
        $symbols = ['SPY', 'QQQ', 'EWZ'];
        
        $previousRemaining = $initialRemaining;
        
        foreach ($symbols as $symbol) {
            // Make a real API call
            $quote = $this->client->stocks->quote($symbol);
            $this->assertNotNull($quote);
            $this->assertEquals($symbol, $quote->symbol);
            
            // Verify rate limits were updated
            $this->assertNotNull($this->client->rate_limits,
                "Rate limits should be set after request for {$symbol}");
            
            // Verify that rate limits reflect the most recent response
            // Note: remaining may stay the same if symbols are free
            $currentRemaining = $this->client->rate_limits->remaining;
            $this->assertLessThanOrEqual(
                $previousRemaining,
                $currentRemaining,
                "Requests remaining should be <= previous after {$symbol} request"
            );
            
            $previousRemaining = $currentRemaining;
            
            // Small delay to avoid hitting rate limits too quickly
            usleep(500000); // 0.5 seconds
        }
        
        // Verify final rate limits are updated
        $this->assertNotNull($this->client->rate_limits);
        $this->assertLessThanOrEqual(
            $initialRemaining,
            $this->client->rate_limits->remaining,
            'Final requests remaining should be <= initial'
        );
    }

    /**
     * Test that rate limits property is accessible and matches /user/ endpoint.
     *
     * @return void
     */
    public function testRateLimits_propertyAccessibleAndCurrent()
    {
        // Make a real API call
        $quote = $this->client->stocks->quote('AAPL');
        $this->assertNotNull($quote);
        
        // Access rate limits from client property
        $clientRateLimits = $this->client->rate_limits;
        $this->assertNotNull($clientRateLimits, 'Client rate_limits property should be accessible');
        
        // Verify all properties are accessible
        $this->assertIsInt($clientRateLimits->limit);
        $this->assertIsInt($clientRateLimits->remaining);
        $this->assertIsInt($clientRateLimits->consumed);
        $this->assertInstanceOf(Carbon::class, $clientRateLimits->reset);
        
        // Get rate limits from /user/ endpoint
        $userRateLimits = $this->client->utilities->user()->rate_limits;
        
        // Compare values - they should match (or be very close, as /user/ call itself may consume a request)
        // Note: The /user/ call itself may consume a request, so remaining might differ by 1
        $this->assertEquals(
            $clientRateLimits->limit,
            $userRateLimits->limit,
            'Rate limit should match between client property and /user/ endpoint'
        );
        
        // Reset timestamp should match
        $this->assertEquals(
            $clientRateLimits->reset->timestamp,
            $userRateLimits->reset->timestamp,
            'Reset timestamp should match between client property and /user/ endpoint'
        );
        
        // Remaining might differ by 1 if /user/ consumes a request
        $remainingDiff = abs($clientRateLimits->remaining - $userRateLimits->remaining);
        $this->assertLessThanOrEqual(
            1,
            $remainingDiff,
            'Requests remaining should match or differ by at most 1 (if /user/ consumes a request)'
        );
    }

    /**
     * Test that rate limits are updated after async requests.
     *
     * @return void
     */
    public function testRateLimits_asyncRequests_updateRateLimits()
    {
        // Store initial rate limits
        $initialRemaining = $this->client->rate_limits->remaining;
        
        // Make async requests using execute_in_parallel
        $symbols = ['SPY', 'QQQ'];
        $quotes = $this->client->stocks->quotes($symbols);
        
        $this->assertNotNull($quotes);
        $this->assertCount(2, $quotes->quotes);
        
        // Verify rate limits were updated after async requests
        $this->assertNotNull($this->client->rate_limits,
            'Rate limits should be set after async requests');
        
        // Verify that rate limits reflect the consumed requests
        // Note: Remaining may stay the same if symbols are free
        $this->assertLessThanOrEqual(
            $initialRemaining,
            $this->client->rate_limits->remaining,
            'Requests remaining should be <= initial after async requests'
        );
        
        // Verify rate limit structure is valid
        $this->assertIsInt($this->client->rate_limits->limit);
        $this->assertIsInt($this->client->rate_limits->remaining);
        $this->assertIsInt($this->client->rate_limits->consumed);
        $this->assertInstanceOf(Carbon::class, $this->client->rate_limits->reset);
    }

    /**
     * Test that initialization with invalid token throws UnauthorizedException.
     *
     * With the new token validation behavior, an invalid token should cause
     * UnauthorizedException to be thrown during construction, preventing client creation.
     *
     * @return void
     */
    public function testRateLimits_invalidToken_throwsUnauthorizedException()
    {
        // Expect UnauthorizedException to be thrown during construction
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionCode(401);
        
        try {
            // Create a client with invalid token - should throw during construction
            $client = new Client('invalid_token_12345');
            
            // If we get here, the exception wasn't thrown (unexpected)
            $this->fail('Expected UnauthorizedException to be thrown during client construction');
        } catch (UnauthorizedException $e) {
            // Verify exception details
            $this->assertEquals(401, $e->getCode());
            $this->assertNotNull($e->getResponse());
            $this->assertEquals(401, $e->getResponse()->getStatusCode());
            
            // Re-throw to satisfy expectException
            throw $e;
        }
    }
}
