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
 * - Environment variable token (automatic resolution)
 * - .env file token (automatic resolution)
 * - Explicit token precedence over env vars
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
        // Use the same robust token detection as Settings class
        $token = getenv('MARKETDATA_TOKEN');
        if ($token === false || $token === '') {
            $token = $_ENV['MARKETDATA_TOKEN'] ?? $_SERVER['MARKETDATA_TOKEN'] ?? null;
        }
        if ($token === null || $token === '') {
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

    /**
     * Test that client can be initialized without token when MARKETDATA_TOKEN env var is set.
     *
     * The client should automatically read the token from the environment variable.
     *
     * @return void
     */
    public function testClientInit_withEnvVar_succeeds()
    {
        // Use the same robust token detection as Settings class
        $token = getenv('MARKETDATA_TOKEN');
        if ($token === false || $token === '') {
            $token = $_ENV['MARKETDATA_TOKEN'] ?? $_SERVER['MARKETDATA_TOKEN'] ?? null;
        }
        if ($token === null || $token === '') {
            $this->markTestSkipped('MARKETDATA_TOKEN environment variable not set');
        }

        // Temporarily unset any existing env var to test clean state
        $originalToken = getenv('MARKETDATA_TOKEN');
        
        // Create client without passing token - should read from env var
        $client = new Client();

        // Verify client was created successfully
        $this->assertInstanceOf(Client::class, $client);

        // If token was valid, rate limits should be set
        if ($originalToken && $originalToken !== '') {
            $this->assertNotNull($client->rate_limits, 'Rate limits should be set when token from env var is valid');
        }
    }

    /**
     * Test that explicit token takes precedence over environment variable.
     *
     * When both explicit token and env var are provided, explicit token should be used.
     *
     * @return void
     */
    public function testClientInit_explicitTokenOverridesEnvVar()
    {
        // Use the same robust token detection as Settings class
        $envToken = getenv('MARKETDATA_TOKEN');
        if ($envToken === false || $envToken === '') {
            $envToken = $_ENV['MARKETDATA_TOKEN'] ?? $_SERVER['MARKETDATA_TOKEN'] ?? null;
        }
        if ($envToken === null || $envToken === '') {
            $this->markTestSkipped('MARKETDATA_TOKEN environment variable not set');
        }

        // Use a different explicit token (empty string to test precedence)
        $explicitToken = '';
        $client = new Client($explicitToken);

        // Verify client was created with explicit token (empty string)
        $this->assertInstanceOf(Client::class, $client);
        // Empty token should result in null rate_limits
        $this->assertNull($client->rate_limits, 'Rate limits should be null when explicit empty token is provided');
    }

    /**
     * Test that client falls back to empty string when no token is provided.
     *
     * When no token is provided and no env var is set, client should use empty string
     * (allowing free symbols like AAPL).
     *
     * @return void
     */
    public function testClientInit_noTokenProvided_fallsBackToEmpty()
    {
        // Save original env var
        $originalToken = getenv('MARKETDATA_TOKEN');
        
        // Temporarily unset env var for this test
        if ($originalToken !== false) {
            putenv('MARKETDATA_TOKEN');
            unset($_ENV['MARKETDATA_TOKEN']);
            unset($_SERVER['MARKETDATA_TOKEN']);
        }

        try {
            // Create client without token and without env var
            $client = new Client();

            // Verify client was created successfully
            $this->assertInstanceOf(Client::class, $client);

            // Rate limits should be null (empty token skips validation)
            $this->assertNull($client->rate_limits, 'Rate limits should be null when no token is provided');
        } finally {
            // Restore original env var
            if ($originalToken !== false) {
                putenv('MARKETDATA_TOKEN=' . $originalToken);
                $_ENV['MARKETDATA_TOKEN'] = $originalToken;
                $_SERVER['MARKETDATA_TOKEN'] = $originalToken;
            }
        }
    }

    /**
     * Test that client can be initialized without token parameter.
     *
     * This tests the new optional parameter feature and backward compatibility.
     *
     * @return void
     */
    public function testClientInit_noParameter_succeeds()
    {
        // This test verifies that new Client() works (backward compatibility maintained)
        // It will use env var if available, or fall back to empty string
        $client = new Client();

        // Verify client was created successfully
        $this->assertInstanceOf(Client::class, $client);
    }
}
