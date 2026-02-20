<?php

namespace MarketDataApp\Tests\Unit;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatusData;
use MarketDataApp\Endpoints\Utilities;
use MarketDataApp\Enums\ApiStatusResult;
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
 *
 * Note: All mock responses in this test class are NOT from real API output (synthetic/test data for retry testing).
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
        // Save original token state before clearing
        $this->saveMarketDataTokenState();
        
        // Clear MARKETDATA_TOKEN environment variable to ensure empty token is used.
        // This prevents real API calls during Client construction by ensuring
        // _setup_rate_limits() skips the /user/ endpoint validation call.
        $this->clearMarketDataToken();
        
        // Use empty token for unit tests to skip validation (tests use mocks anyway)
        $this->client = new Client("");
        
        // Clear API status cache before each test to ensure fresh state
        Utilities::clearApiStatusCache();
    }

    /**
     * Restore original environment variable state after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->restoreMarketDataTokenState();
        parent::tearDown();
    }

    // ========== Sync Request Retry Tests ==========

    /**
     * Test sync retry on server error succeeds after retries.
     *
     * @return void
     */
    public function testSyncRetryOnServerError_retriesAndSucceeds(): void
    {
        // Mock responses: NOT from real API output (synthetic/test data for retry testing)
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
        // Mock responses: NOT from real API output (synthetic/test data for retry testing)
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
     * Test multi-symbol quotes retry on server error.
     *
     * This test verifies that the single multi-symbol request retries on server error
     * and eventually succeeds with all symbols present in the final result.
     *
     * @return void
     */
    public function testMultiSymbolQuotes_retryOnServerError_succeeds(): void
    {
        // Test scenario: First request fails with 502, retry succeeds
        $this->setMockResponses([
            // First request fails
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            // Retry succeeds with multi-symbol response
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL', 'MSFT', 'GOOGL'],
                'last' => [150.0, 300.0, 2500.0],
                'ask' => [150.1, 300.1, 2500.1],
                'askSize' => [200, 200, 200],
                'bid' => [150.0, 300.0, 2500.0],
                'bidSize' => [300, 300, 300],
                'mid' => [150.05, 300.05, 2500.05],
                'change' => [0.5, 1.0, 5.0],
                'changepct' => [0.33, 0.33, 0.2],
                'volume' => [1000000, 2000000, 3000000],
                'updated' => [1234567890, 1234567890, 1234567890]
            ])),
        ]);

        $result = $this->client->stocks->quotes(['AAPL', 'MSFT', 'GOOGL']);

        // Verify the result structure
        $this->assertNotNull($result);
        $this->assertIsObject($result);
        $this->assertIsArray($result->quotes);
        $this->assertCount(3, $result->quotes, 'Should have exactly 3 quotes');

        // Extract symbols from the result
        $symbols = array_map(function($quote) {
            return $quote->symbol;
        }, $result->quotes);

        // Verify all expected symbols are present
        $expectedSymbols = ['AAPL', 'MSFT', 'GOOGL'];
        $this->assertEquals($expectedSymbols, $symbols, 'All expected symbols should be present in the result');
    }

    /**
     * Test multi-symbol quotes retry on network error.
     *
     * @return void
     */
    public function testMultiSymbolQuotes_retryOnNetworkError_succeeds(): void
    {
        $this->setMockResponses([
            // First request fails with network error
            new RequestException("Network Error", new Request('GET', 'test')),
            // Retry succeeds with multi-symbol response
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL', 'MSFT'],
                'last' => [150.0, 300.0],
                'ask' => [150.1, 300.1],
                'askSize' => [200, 200],
                'bid' => [150.0, 300.0],
                'bidSize' => [300, 300],
                'mid' => [150.05, 300.05],
                'change' => [0.5, 1.0],
                'changepct' => [0.33, 0.33],
                'volume' => [1000000, 2000000],
                'updated' => [1234567890, 1234567890]
            ])),
        ]);

        $result = $this->client->stocks->quotes(['AAPL', 'MSFT']);

        $this->assertNotNull($result);
        $this->assertIsObject($result);
        $this->assertCount(2, $result->quotes);
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

    // ========== Intelligent Retry with API Status Checking Tests ==========

    /**
     * Test retry when service is ONLINE - should retry normally.
     *
     * @return void
     */
    public function testRetryWithServiceOnline_retriesNormally(): void
    {
        // Set up API status cache with service online
        $statusResponse = (object)[
            's' => 'ok',
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'online' => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $apiStatusData = Utilities::getApiStatusData();
        $apiStatusData->update($statusResponse);

        // Mock server error then success
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);

        $result = $this->client->stocks->quote('AAPL');
        $this->assertNotNull($result);
    }

    /**
     * Test retry when service is OFFLINE - should skip retries and throw immediately.
     *
     * @return void
     */
    public function testRetryWithServiceOffline_skipsRetries(): void
    {
        // Set up API status cache with service offline
        $statusResponse = (object)[
            's' => 'ok',
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['offline'],
            'online' => [false],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $apiStatusData = Utilities::getApiStatusData();
        $apiStatusData->update($statusResponse);

        // Mock server error - should NOT retry, throw immediately
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
        ]);

        $this->expectException(RequestError::class);
        $this->expectExceptionMessage('Server Error');

        // Should throw immediately without retrying
        $this->client->stocks->quote('AAPL');
    }

    /**
     * Test retry when service is UNKNOWN - should retry normally.
     *
     * @return void
     */
    public function testRetryWithServiceUnknown_retriesNormally(): void
    {
        // Set up API status cache but with a different service (so our service is UNKNOWN)
        $statusResponse = (object)[
            's' => 'ok',
            'service' => ['/v1/options/chain/'], // Different service
            'status' => ['online'],
            'online' => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $apiStatusData = Utilities::getApiStatusData();
        $apiStatusData->update($statusResponse);

        // Mock server error then success
        // Service /v1/stocks/quotes/ will be UNKNOWN (not in cache), so should retry
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);

        $result = $this->client->stocks->quote('AAPL');
        $this->assertNotNull($result);
    }

    /**
     * Test service path mapping for various endpoints.
     *
     * @return void
     */
    public function testServicePathMapping_variousEndpoints(): void
    {
        $reflection = new \ReflectionClass($this->client);
        $parentClass = $reflection->getParentClass();
        $method = $parentClass->getMethod('getServicePath');

        // Test various method paths
        $this->assertEquals('/v1/stocks/quotes/', $method->invoke($this->client, 'v1/stocks/quotes/AAPL'));
        $this->assertEquals('/v1/stocks/candles/', $method->invoke($this->client, 'v1/stocks/candles/D/AAPL/'));
        $this->assertEquals('/v1/options/chain/', $method->invoke($this->client, 'v1/options/chain/AAPL'));
        $this->assertEquals('/v1/stocks/earnings/', $method->invoke($this->client, 'v1/stocks/earnings/AAPL'));
        $this->assertEquals('/v1/stocks/news/', $method->invoke($this->client, 'v1/stocks/news/AAPL'));
        $this->assertEquals('/v1/options/quotes/', $method->invoke($this->client, 'v1/options/quotes/AAPL230728C00200000'));
        
        // Test status endpoint returns null
        $this->assertNull($method->invoke($this->client, 'status/'));
        
        // Test unknown service returns null
        $this->assertNull($method->invoke($this->client, 'v1/unknown/service/'));
    }

    /**
     * Test status endpoint doesn't check its own status (no infinite loop).
     *
     * @return void
     */
    public function testStatusEndpoint_doesNotCheckOwnStatus(): void
    {
        // Set up API status cache with status endpoint offline
        $statusResponse = (object)[
            's' => 'ok',
            'service' => ['/status/'],
            'status' => ['offline'],
            'online' => [false],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $apiStatusData = Utilities::getApiStatusData();
        $apiStatusData->update($statusResponse);

        // Mock status endpoint error - should retry normally (not skip due to offline status)
        // because status endpoint ("status/") doesn't check its own status
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(200, [], json_encode([
                's' => 'ok',
                'service' => ['/status/'],
                'status' => ['online'],
                'online' => [true],
                'uptimePct30d' => [0.99],
                'uptimePct90d' => [0.98],
                'updated' => [time()]
            ])),
        ]);

        // Status endpoint should retry normally (doesn't check its own status)
        $result = $this->client->utilities->api_status();
        $this->assertNotNull($result);
    }

    /**
     * Test async retry with service offline - should skip retries.
     *
     * @return void
     */
    public function testAsyncRetryWithServiceOffline_skipsRetries(): void
    {
        // Set up API status cache with service offline
        $statusResponse = (object)[
            's' => 'ok',
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['offline'],
            'online' => [false],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $apiStatusData = Utilities::getApiStatusData();
        $apiStatusData->update($statusResponse);

        // Mock server error - should NOT retry
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
        ]);

        $this->expectException(\Throwable::class);

        // Should throw immediately without retrying
        $this->client->execute_in_parallel([
            ['v1/stocks/quotes/AAPL', []],
        ]);
    }

    // ========== Concurrent Request Limit Tests ==========

    /**
     * Test that execute_in_parallel enforces MAX_CONCURRENT_REQUESTS limit.
     *
     * When more than 50 requests are passed, they should be processed in batches.
     *
     * @return void
     */
    public function testExecuteInParallel_enforcesConcurrentLimit(): void
    {
        // Create 75 mock responses (more than MAX_CONCURRENT_REQUESTS of 50)
        $responses = [];
        for ($i = 0; $i < 75; $i++) {
            $responses[] = new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ["SYM{$i}"],
                'last' => [100.0 + $i],
                'ask' => [100.1],
                'askSize' => [200],
                'bid' => [100.0],
                'bidSize' => [300],
                'mid' => [100.05],
                'change' => [0.5],
                'changepct' => [0.33],
                'volume' => [1000000],
                'updated' => [1234567890]
            ]));
        }
        $this->setMockResponses($responses);

        // Create 75 calls
        $calls = [];
        for ($i = 0; $i < 75; $i++) {
            $calls[] = ["quotes/SYM{$i}", []];
        }

        $results = $this->client->execute_in_parallel($calls);

        // All 75 results should be returned
        $this->assertCount(75, $results);

        // Results should be in the same order as the calls
        for ($i = 0; $i < 75; $i++) {
            $this->assertEquals("SYM{$i}", $results[$i]->symbol[0]);
        }
    }

    /**
     * Test that requests within limit are processed in a single batch.
     *
     * @return void
     */
    public function testExecuteInParallel_singleBatchUnderLimit(): void
    {
        // Create 10 mock responses (well under MAX_CONCURRENT_REQUESTS of 50)
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $responses[] = new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ["SYM{$i}"],
                'last' => [100.0 + $i],
                'ask' => [100.1],
                'askSize' => [200],
                'bid' => [100.0],
                'bidSize' => [300],
                'mid' => [100.05],
                'change' => [0.5],
                'changepct' => [0.33],
                'volume' => [1000000],
                'updated' => [1234567890]
            ]));
        }
        $this->setMockResponses($responses);

        // Create 10 calls
        $calls = [];
        for ($i = 0; $i < 10; $i++) {
            $calls[] = ["quotes/SYM{$i}", []];
        }

        $results = $this->client->execute_in_parallel($calls);

        $this->assertCount(10, $results);
        for ($i = 0; $i < 10; $i++) {
            $this->assertEquals("SYM{$i}", $results[$i]->symbol[0]);
        }
    }

    /**
     * Test execute_in_parallel with exactly MAX_CONCURRENT_REQUESTS.
     *
     * @return void
     */
    public function testExecuteInParallel_exactlyAtLimit(): void
    {
        $limit = \MarketDataApp\Settings::MAX_CONCURRENT_REQUESTS;

        // Create exactly MAX_CONCURRENT_REQUESTS mock responses
        $responses = [];
        for ($i = 0; $i < $limit; $i++) {
            $responses[] = new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ["SYM{$i}"],
                'last' => [100.0 + $i],
                'ask' => [100.1],
                'askSize' => [200],
                'bid' => [100.0],
                'bidSize' => [300],
                'mid' => [100.05],
                'change' => [0.5],
                'changepct' => [0.33],
                'volume' => [1000000],
                'updated' => [1234567890]
            ]));
        }
        $this->setMockResponses($responses);

        // Create exactly MAX_CONCURRENT_REQUESTS calls
        $calls = [];
        for ($i = 0; $i < $limit; $i++) {
            $calls[] = ["quotes/SYM{$i}", []];
        }

        $results = $this->client->execute_in_parallel($calls);

        $this->assertCount($limit, $results);
    }

    /**
     * Test execute_in_parallel with one more than MAX_CONCURRENT_REQUESTS.
     *
     * @return void
     */
    public function testExecuteInParallel_oneOverLimit(): void
    {
        $limit = \MarketDataApp\Settings::MAX_CONCURRENT_REQUESTS;
        $count = $limit + 1;

        // Create one more than MAX_CONCURRENT_REQUESTS mock responses
        $responses = [];
        for ($i = 0; $i < $count; $i++) {
            $responses[] = new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ["SYM{$i}"],
                'last' => [100.0 + $i],
                'ask' => [100.1],
                'askSize' => [200],
                'bid' => [100.0],
                'bidSize' => [300],
                'mid' => [100.05],
                'change' => [0.5],
                'changepct' => [0.33],
                'volume' => [1000000],
                'updated' => [1234567890]
            ]));
        }
        $this->setMockResponses($responses);

        // Create one more than MAX_CONCURRENT_REQUESTS calls
        $calls = [];
        for ($i = 0; $i < $count; $i++) {
            $calls[] = ["quotes/SYM{$i}", []];
        }

        $results = $this->client->execute_in_parallel($calls);

        // All results should be returned (processed in 2 batches: 50 + 1)
        $this->assertCount($count, $results);

        // Results should maintain order
        for ($i = 0; $i < $count; $i++) {
            $this->assertEquals("SYM{$i}", $results[$i]->symbol[0]);
        }
    }

    /**
     * Test execute_in_parallel batching with large number of requests.
     *
     * @return void
     */
    public function testExecuteInParallel_multipleBatches(): void
    {
        $limit = \MarketDataApp\Settings::MAX_CONCURRENT_REQUESTS;
        $count = $limit * 2 + 25; // 125 requests = 3 batches (50 + 50 + 25)

        // Create mock responses
        $responses = [];
        for ($i = 0; $i < $count; $i++) {
            $responses[] = new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ["SYM{$i}"],
                'last' => [100.0 + $i],
                'ask' => [100.1],
                'askSize' => [200],
                'bid' => [100.0],
                'bidSize' => [300],
                'mid' => [100.05],
                'change' => [0.5],
                'changepct' => [0.33],
                'volume' => [1000000],
                'updated' => [1234567890]
            ]));
        }
        $this->setMockResponses($responses);

        // Create calls
        $calls = [];
        for ($i = 0; $i < $count; $i++) {
            $calls[] = ["quotes/SYM{$i}", []];
        }

        $results = $this->client->execute_in_parallel($calls);

        // All results should be returned
        $this->assertCount($count, $results);

        // Results should maintain order across all batches
        for ($i = 0; $i < $count; $i++) {
            $this->assertEquals("SYM{$i}", $results[$i]->symbol[0]);
        }
    }
}
