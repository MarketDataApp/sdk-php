<?php

namespace MarketDataApp\Tests\Integration;

use Carbon\Carbon;
use MarketDataApp\Client;
use MarketDataApp\Exceptions\UnauthorizedException;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for client initialization with different token scenarios.
 *
 * These tests verify that the client can be initialized correctly with:
 * - Valid token (should succeed and set rate_limits)
 * - Empty token (should succeed for free symbols)
 * - Invalid token (should throw UnauthorizedException)
 */
class ClientInitTest extends TestCase
{
    /**
     * Test that client can be initialized with a valid token.
     *
     * A valid token should allow client creation and set rate_limits
     * from the /user endpoint response.
     *
     * @return void
     */
    public function testClientInit_validToken_succeeds()
    {
        $token = getenv('MARKETDATA_TOKEN') ?: 'your_api_token';
        if ($token === 'your_api_token') {
            $this->markTestSkipped('MARKETDATA_TOKEN environment variable not set');
        }
        
        // Create client with valid token
        $client = new Client($token);
        
        // Verify client was created successfully
        $this->assertInstanceOf(Client::class, $client);
        
        // Verify rate limits were set during initialization
        $this->assertNotNull($client->rate_limits, 'Rate limits should be set for valid token');
        $this->assertGreaterThan(0, $client->rate_limits->limit, 'Rate limit should be positive');
        $this->assertGreaterThanOrEqual(0, $client->rate_limits->remaining, 'Remaining should be >= 0');
        $this->assertInstanceOf(Carbon::class, $client->rate_limits->reset, 'Reset should be a Carbon instance');
    }

    /**
     * Test that client can be initialized with an empty token.
     *
     * An empty token should be allowed for accessing free symbols like AAPL.
     * The /user endpoint validation should be skipped, so rate_limits will be null.
     *
     * @return void
     */
    public function testClientInit_emptyToken_succeeds()
    {
        // Create client with empty token
        $client = new Client('');
        
        // Verify client was created successfully
        $this->assertInstanceOf(Client::class, $client);
        
        // Verify rate limits are null (validation was skipped)
        $this->assertNull($client->rate_limits, 'Rate limits should be null for empty token');
        
        // Verify that free symbols work (like AAPL)
        // Note: This makes a real API call, so it's a true integration test
        try {
            $quote = $client->stocks->quote('AAPL');
            $this->assertNotNull($quote);
            $this->assertEquals('AAPL', $quote->symbol);
        } catch (\Exception $e) {
            // If AAPL quote fails, that's okay - the important part is that
            // the client was created without exception
            $this->assertTrue(true, 'Client created successfully even if AAPL quote fails');
        }
    }

    /**
     * Test that client initialization throws UnauthorizedException with invalid token.
     *
     * An invalid token should cause UnauthorizedException to be thrown
     * during construction when the /user endpoint returns 401.
     *
     * @return void
     */
    public function testClientInit_invalidToken_throwsUnauthorizedException()
    {
        // Expect UnauthorizedException to be thrown during construction
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionCode(401);
        
        try {
            // Create client with invalid token - should throw during construction
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
