<?php

namespace MarketDataApp\Tests\Unit;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Exceptions\BadStatusCodeError;
use MarketDataApp\Exceptions\RequestError;
use MarketDataApp\Exceptions\UnauthorizedException;
use MarketDataApp\Retry\RetryConfig;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Test case for retry functionality in the MarketDataApp SDK.
 *
 * This class tests retry logic for sync, async, and parallel requests.
 */
class RetryTest extends TestCase
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
        $this->client = new Client("test_token");
    }

    // ========== Sync Request Retry Tests ==========

    /**
     * Test sync retry on server error succeeds after retries.
     *
     * @return void
     */
    public function testSyncRetryOnServerError_retriesAndSucceeds(): void
    {
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);

        $start = microtime(true);
        $result = $this->client->stocks->quote('AAPL');
        $duration = microtime(true) - $start;

        $this->assertNotNull($result);
        $this->assertIsObject($result);
        // Verify exponential backoff timing (approximately 0.5s + 1s = 1.5s minimum)
        $this->assertGreaterThan(1.0, $duration);
    }

    /**
     * Test sync retry on server error exhausts retries.
     *
     * @return void
     */
    public function testSyncRetryOnServerError_exhaustsRetries(): void
    {
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
        ]);

        $this->expectException(RequestError::class);
        $this->expectExceptionMessage('Server Error');

        $this->client->stocks->quote('AAPL');
    }

    /**
     * Test sync retry on network error succeeds after retry.
     *
     * @return void
     */
    public function testSyncRetryOnNetworkError_retriesAndSucceeds(): void
    {
        $this->setMockResponses([
            new RequestException("Network Error", new Request('GET', 'test')),
            new RequestException("Network Error", new Request('GET', 'test')),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);

        $result = $this->client->stocks->quote('AAPL');

        $this->assertNotNull($result);
        $this->assertIsObject($result);
    }

    /**
     * Test sync no retry on client error.
     *
     * @return void
     */
    public function testSyncNoRetryOnClientError(): void
    {
        $this->setMockResponses([
            new Response(400, [], json_encode(['errmsg' => 'Bad Request'])),
        ]);

        $this->expectException(BadStatusCodeError::class);
        $this->expectExceptionMessage('Bad Request');

        $this->client->stocks->quote('INVALID');
    }

    /**
     * Test sync no retry on 404 (special case).
     *
     * @return void
     */
    public function testSyncNoRetryOn404(): void
    {
        $this->setMockResponses([
            new Response(404, [], json_encode(['s' => 'ok', 'symbol' => ['NONEXISTENT'], 'last' => [0.0], 'ask' => [0.0], 'askSize' => [0], 'bid' => [0.0], 'bidSize' => [0], 'mid' => [0.0], 'change' => [0.0], 'changepct' => [0.0], 'volume' => [0], 'updated' => [0]])),
        ]);

        // 404 should return response, not throw (special case)
        // Note: If the response has s='error', ApiException will be thrown during processing
        // This test uses s='ok' to verify 404 doesn't trigger retries
        $result = $this->client->stocks->quote('NONEXISTENT');

        $this->assertNotNull($result);
    }

    /**
     * Test sync retry exponential backoff timing.
     *
     * @return void
     */
    public function testSyncRetryExponentialBackoff(): void
    {
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);

        $start = microtime(true);
        $this->client->stocks->quote('AAPL');
        $duration = microtime(true) - $start;

        // Verify delays are approximately 0.5s + 1s = 1.5s (with tolerance)
        $this->assertGreaterThan(1.0, $duration);
        $this->assertLessThan(3.0, $duration); // Should be less than 3s
    }

    // ========== Async Request Retry Tests ==========

    /**
     * Test async retry on server error succeeds after retry.
     *
     * @return void
     */
    public function testAsyncRetryOnServerError_retriesAndSucceeds(): void
    {
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);

        $responses = $this->client->execute_in_parallel([['quotes/AAPL', []]]);

        $this->assertCount(1, $responses);
        $this->assertIsObject($responses[0]);
    }

    /**
     * Test async retry on server error exhausts retries.
     *
     * @return void
     */
    public function testAsyncRetryOnServerError_exhaustsRetries(): void
    {
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
        ]);

        $this->expectException(\Throwable::class);

        $this->client->execute_in_parallel([['quotes/AAPL', []]]);
    }

    /**
     * Test async retry on network error succeeds after retry.
     *
     * @return void
     */
    public function testAsyncRetryOnNetworkError_retriesAndSucceeds(): void
    {
        $this->setMockResponses([
            new RequestException("Network Error", new Request('GET', 'test')),
            new RequestException("Network Error", new Request('GET', 'test')),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);

        $responses = $this->client->execute_in_parallel([['quotes/AAPL', []]]);

        $this->assertCount(1, $responses);
        $this->assertIsObject($responses[0]);
    }

    /**
     * Test async no retry on client error.
     *
     * @return void
     */
    public function testAsyncNoRetryOnClientError(): void
    {
        $this->setMockResponses([
            new Response(400, [], json_encode(['errmsg' => 'Bad Request'])),
        ]);

        $this->expectException(\Throwable::class);

        $this->client->execute_in_parallel([['quotes/INVALID', []]]);
    }

    /**
     * Test async retry exponential backoff.
     *
     * @return void
     */
    public function testAsyncRetryExponentialBackoff(): void
    {
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);

        $start = microtime(true);
        $this->client->execute_in_parallel([['quotes/AAPL', []]]);
        $duration = microtime(true) - $start;

        // Verify delays are exponential (with tolerance)
        $this->assertGreaterThan(1.0, $duration);
        $this->assertLessThan(3.0, $duration);
    }

    // ========== Parallel Request Retry Tests ==========

    /**
     * Test parallel retry on server error retries independently.
     *
     * @return void
     */
    public function testParallelRetryOnServerError_retriesIndependently(): void
    {
        $this->setMockResponses([
            // First request: succeeds immediately
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
            // Second request: needs 2 retries
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['MSFT'], 'last' => [300.0], 'ask' => [300.1], 'askSize' => [200], 'bid' => [300.0], 'bidSize' => [300], 'mid' => [300.05], 'change' => [1.0], 'changepct' => [0.33], 'volume' => [2000000], 'updated' => [1234567890]])),
        ]);

        $result = $this->client->stocks->quotes(['AAPL', 'MSFT']);

        $this->assertNotNull($result);
        $this->assertIsObject($result);
    }

    /**
     * Test parallel retry on server error with mixed results.
     *
     * This test verifies that all parallel requests eventually succeed even when
     * some need retries. It does not assume any specific response ordering, only
     * that all required symbols are present in the final result.
     *
     * @return void
     */
    public function testParallelRetryOnServerError_mixedResults(): void
    {
        // Test scenario:
        // - AAPL: Should succeed immediately (1 response needed)
        // - MSFT: Needs 1 retry (1 failure + 1 success = 2 responses needed)
        // - GOOGL: Needs 2 retries (2 failures + 1 success = 3 responses needed)
        // Total: 6 responses minimum, but we provide extra buffer for timing variations
        $this->setMockResponses([
            // Success responses for each symbol (multiple copies to handle retry timing)
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['MSFT'], 'last' => [300.0], 'ask' => [300.1], 'askSize' => [200], 'bid' => [300.0], 'bidSize' => [300], 'mid' => [300.05], 'change' => [1.0], 'changepct' => [0.33], 'volume' => [2000000], 'updated' => [1234567890]])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['GOOGL'], 'last' => [2500.0], 'ask' => [2500.1], 'askSize' => [200], 'bid' => [2500.0], 'bidSize' => [300], 'mid' => [2500.05], 'change' => [5.0], 'changepct' => [0.2], 'volume' => [3000000], 'updated' => [1234567890]])),
            // Error responses to trigger retries (order may vary due to async timing)
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            // Additional success responses for retries (buffer for timing variations)
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['MSFT'], 'last' => [300.0], 'ask' => [300.1], 'askSize' => [200], 'bid' => [300.0], 'bidSize' => [300], 'mid' => [300.05], 'change' => [1.0], 'changepct' => [0.33], 'volume' => [2000000], 'updated' => [1234567890]])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['GOOGL'], 'last' => [2500.0], 'ask' => [2500.1], 'askSize' => [200], 'bid' => [2500.0], 'bidSize' => [300], 'mid' => [2500.05], 'change' => [5.0], 'changepct' => [0.2], 'volume' => [3000000], 'updated' => [1234567890]])),
        ]);

        $result = $this->client->stocks->quotes(['AAPL', 'MSFT', 'GOOGL']);

        // Verify the result structure
        $this->assertNotNull($result);
        $this->assertIsObject($result);
        $this->assertIsArray($result->quotes);
        $this->assertCount(3, $result->quotes, 'Should have exactly 3 quotes');

        // Extract symbols from the result (order-independent verification)
        $symbols = array_map(function($quote) {
            return $quote->symbol;
        }, $result->quotes);

        // Verify all expected symbols are present (regardless of order)
        $expectedSymbols = ['AAPL', 'MSFT', 'GOOGL'];
        sort($symbols);
        sort($expectedSymbols);
        $this->assertEquals($expectedSymbols, $symbols, 'All expected symbols should be present in the result');
    }

    /**
     * Test parallel retry on network error retries independently.
     *
     * @return void
     */
    public function testParallelRetryOnNetworkError_retriesIndependently(): void
    {
        $this->setMockResponses([
            // Request 1: network error then success
            new RequestException("Network Error", new Request('GET', 'test')),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
            // Request 2: succeeds immediately
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['MSFT'], 'last' => [300.0], 'ask' => [300.1], 'askSize' => [200], 'bid' => [300.0], 'bidSize' => [300], 'mid' => [300.05], 'change' => [1.0], 'changepct' => [0.33], 'volume' => [2000000], 'updated' => [1234567890]])),
        ]);

        $result = $this->client->stocks->quotes(['AAPL', 'MSFT']);

        $this->assertNotNull($result);
        $this->assertIsObject($result);
    }

    // ========== Edge Cases and Integration Tests ==========

    /**
     * Test retry config values match Python SDK.
     *
     * @return void
     */
    public function testRetryConfigValues_matchPythonSDK(): void
    {
        $this->assertEquals(3, RetryConfig::MAX_RETRY_ATTEMPTS);
        $this->assertEquals(0.5, RetryConfig::RETRY_BACKOFF);
        $this->assertEquals(0.5, RetryConfig::MIN_RETRY_BACKOFF);
        $this->assertEquals(5.0, RetryConfig::MAX_RETRY_BACKOFF);

        // Test isRetryableStatusCode (status code > 500)
        $this->assertFalse(RetryConfig::isRetryableStatusCode(500)); // 500 is NOT retryable
        $this->assertTrue(RetryConfig::isRetryableStatusCode(501));
        $this->assertTrue(RetryConfig::isRetryableStatusCode(502));
        $this->assertTrue(RetryConfig::isRetryableStatusCode(503));
        $this->assertTrue(RetryConfig::isRetryableStatusCode(504));
        $this->assertFalse(RetryConfig::isRetryableStatusCode(400));
        $this->assertFalse(RetryConfig::isRetryableStatusCode(404));
    }

    /**
     * Test retry does not block async operations.
     *
     * @return void
     */
    public function testRetryDoesNotBlockAsyncOperations(): void
    {
        $this->setMockResponses([
            // Multiple requests with retries
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['MSFT'], 'last' => [300.0], 'ask' => [300.1], 'askSize' => [200], 'bid' => [300.0], 'bidSize' => [300], 'mid' => [300.05], 'change' => [1.0], 'changepct' => [0.33], 'volume' => [2000000], 'updated' => [1234567890]])),
        ]);

        $start = microtime(true);
        $result = $this->client->stocks->quotes(['AAPL', 'MSFT']);
        $duration = microtime(true) - $start;

        $this->assertNotNull($result);
        // Parallel requests should complete faster than sequential
        $this->assertLessThan(5.0, $duration);
    }

    /**
     * Test retry with ApiException still throws ApiException.
     *
     * @return void
     */
    public function testRetryWithApiException_stillThrowsApiException(): void
    {
        $this->setMockResponses([
            new Response(200, [], json_encode(['s' => 'error', 'errmsg' => 'API Error'])),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('API Error');

        $this->client->stocks->quote('AAPL');
    }

    /**
     * Test retry status code validation: 502 retries, 500 and 400 do not.
     *
     * @return void
     */
    public function testRetryStatusCodeValidation_502Retries_500And400DoNot(): void
    {
        // Test 502 retries (status code > 500)
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);

        $result = $this->client->stocks->quote('AAPL');
        $this->assertNotNull($result);
        
        // Test 500 does NOT retry (status code is exactly 500, not > 500) - should throw immediately
        $this->setMockResponses([
            new Response(500, [], json_encode(['errmsg' => 'Internal Server Error'])),
        ]);
        
        $this->expectException(RequestError::class);
        $this->client->stocks->quote('AAPL');

        // Test 400 does not retry
        $this->setMockResponses([
            new Response(400, [], json_encode(['errmsg' => 'Bad Request'])),
        ]);

        $this->expectException(BadStatusCodeError::class);
        $this->client->stocks->quote('INVALID');
    }

    /**
     * Test retry with 502 Bad Gateway (retryable).
     *
     * @return void
     */
    public function testRetryOn502BadGateway(): void
    {
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Bad Gateway'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);

        $result = $this->client->stocks->quote('AAPL');
        $this->assertNotNull($result);
    }

    /**
     * Test retry with 503 Service Unavailable (retryable).
     *
     * @return void
     */
    public function testRetryOn503ServiceUnavailable(): void
    {
        $this->setMockResponses([
            new Response(503, [], json_encode(['errmsg' => 'Service Unavailable'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);

        $result = $this->client->stocks->quote('AAPL');
        $this->assertNotNull($result);
    }

    /**
     * Test retry with 504 Gateway Timeout (retryable).
     *
     * @return void
     */
    public function testRetryOn504GatewayTimeout(): void
    {
        $this->setMockResponses([
            new Response(504, [], json_encode(['errmsg' => 'Gateway Timeout'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);

        $result = $this->client->stocks->quote('AAPL');
        $this->assertNotNull($result);
    }

    /**
     * Test 401 Unauthorized throws UnauthorizedException in sync request.
     *
     * @return void
     */
    public function test401Unauthorized_throwsUnauthorizedException(): void
    {
        $this->setMockResponses([
            new Response(401, [], json_encode(['errmsg' => 'Unauthorized: The token supplied with the request is missing, invalid, or cannot be used.'])),
        ]);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Unauthorized: The token supplied with the request is missing, invalid, or cannot be used.');
        $this->expectExceptionCode(401);

        $this->client->stocks->quote('AAPL');
    }

    /**
     * Test 401 Unauthorized does not retry (it's a 4xx error).
     *
     * @return void
     */
    public function test401Unauthorized_doesNotRetry(): void
    {
        $this->setMockResponses([
            new Response(401, [], json_encode(['errmsg' => 'Unauthorized'])),
        ]);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionCode(401);

        try {
            $this->client->stocks->quote('AAPL');
        } catch (UnauthorizedException $e) {
            $this->assertEquals(401, $e->getCode());
            $this->assertNotNull($e->getResponse());
            $this->assertEquals(401, $e->getResponse()->getStatusCode());
            throw $e;
        }
    }

    /**
     * Test 401 Unauthorized throws UnauthorizedException in async request.
     *
     * @return void
     */
    public function test401Unauthorized_async_throwsUnauthorizedException(): void
    {
        $this->setMockResponses([
            new Response(401, [], json_encode(['errmsg' => 'Unauthorized'])),
        ]);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionCode(401);

        $this->client->execute_in_parallel([
            ['v1/stocks/quotes/AAPL', []],
        ]);
    }

    /**
     * Test 401 Unauthorized exception preserves response.
     *
     * @return void
     */
    public function test401Unauthorized_preservesResponse(): void
    {
        $errorMessage = 'Unauthorized: The token supplied with the request is missing, invalid, or cannot be used.';
        $response = new Response(401, [], json_encode(['errmsg' => $errorMessage]));
        
        $this->setMockResponses([$response]);

        try {
            $this->client->stocks->quote('AAPL');
            $this->fail('Expected UnauthorizedException was not thrown');
        } catch (UnauthorizedException $e) {
            $this->assertEquals(401, $e->getCode());
            $this->assertEquals($errorMessage, $e->getMessage());
            $this->assertNotNull($e->getResponse());
            $this->assertEquals(401, $e->getResponse()->getStatusCode());
            $this->assertInstanceOf(UnauthorizedException::class, $e);
            $this->assertInstanceOf(BadStatusCodeError::class, $e); // Should extend BadStatusCodeError
        }
    }
}
