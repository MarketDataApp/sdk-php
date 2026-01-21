<?php

namespace MarketDataApp\Tests\Unit;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Utilities;
use MarketDataApp\Exceptions\BadStatusCodeError;
use MarketDataApp\Exceptions\RequestError;
use MarketDataApp\Exceptions\UnauthorizedException;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Test case for ClientBase error handling paths.
 *
 * This class tests error handling scenarios that are not covered by RetryTest.
 */
class ClientBaseErrorHandlingTest extends TestCase
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
        $this->client = new Client("");
        
        // Clear API status cache before each test to ensure fresh state
        Utilities::clearApiStatusCache();
    }

    /**
     * Test _setup_rate_limits with network/timeout exception (non-UnauthorizedException).
     *
     * @return void
     */
    public function testSetupRateLimits_withNetworkException_handlesGracefully(): void
    {
        // Create client with empty token first (skips rate limit setup in constructor)
        $client = new Client("");
        
        // Set up mock that will throw a network exception (not UnauthorizedException)
        // We need to set up the mock on the client we're testing
        $mockHandler = new \GuzzleHttp\Handler\MockHandler([
            new RequestException("Network Error", new Request('GET', 'user/')),
        ]);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mockHandler);
        $mockGuzzle = new \GuzzleHttp\Client(['handler' => $handlerStack]);
        $client->setGuzzle($mockGuzzle);
        
        // Use reflection to set token and call _setup_rate_limits
        $reflection = new ReflectionClass($client);
        $tokenProperty = $reflection->getProperty('token');
        $tokenProperty->setValue($client, 'test_token');
        
        $method = $reflection->getMethod('_setup_rate_limits');
        
        // Call the method - it should catch the exception and not throw
        $method->invoke($client);
        
        // Rate limits should be null since setup failed
        $this->assertNull($client->rate_limits);
    }

    /**
     * Test async retry with RequestError that triggers retry logic.
     *
     * @return void
     */
    public function testAsyncRetry_withRequestError_retries(): void
    {
        // Mock RequestError (5xx) that should trigger retry
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);

        $responses = $this->client->execute_in_parallel([['v1/stocks/quotes/AAPL', []]]);

        $this->assertCount(1, $responses);
        $this->assertIsObject($responses[0]);
    }

    /**
     * Test async retry with non-retryable ServerException.
     *
     * @return void
     */
    public function testAsyncRetry_withNonRetryableServerException_throwsImmediately(): void
    {
        // Mock 500 (non-retryable, exactly 500 not > 500)
        $this->setMockResponses([
            new Response(500, [], json_encode(['errmsg' => 'Internal Server Error'])),
        ]);

        $this->expectException(\Throwable::class);

        $this->client->execute_in_parallel([['v1/stocks/quotes/AAPL', []]]);
    }

    /**
     * Test async with 404 response - should NOT retry, return response immediately.
     *
     * @return void
     */
    public function testAsync_with404Response_doesNotRetry_returnsResponse(): void
    {
        // Mock 404 response - should return response immediately, NOT retry
        // Use s='ok' to avoid ApiException during processing
        $this->setMockResponses([
            new Response(404, [
                'x-api-ratelimit-limit' => ['100'],
                'x-api-ratelimit-remaining' => ['99'],
                'x-api-ratelimit-reset' => [(string)(time() + 3600)],
                'x-api-ratelimit-consumed' => ['1'],
            ], json_encode(['s' => 'ok', 'symbol' => ['INVALID']])),
        ]);

        $responses = $this->client->execute_in_parallel([['v1/stocks/quotes/INVALID', []]]);

        $this->assertCount(1, $responses);
        // 404 should return response immediately without retrying
    }

    /**
     * Test sync execute with RequestError that triggers retry.
     *
     * @return void
     */
    public function testSyncExecute_withRequestError_retries(): void
    {
        // Mock RequestError (5xx) that should trigger retry
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);

        $result = $this->client->stocks->quote('AAPL');

        $this->assertNotNull($result);
        $this->assertIsObject($result);
    }

    /**
     * Test validateResponseStatusCode with null response.
     *
     * @return void
     */
    public function testValidateResponseStatusCode_withNullResponse_returnsEarly(): void
    {
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('validateResponseStatusCode');

        // Should not throw when response is null
        $method->invoke($this->client, null, true);
        
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test validateResponseStatusCode with 401 when raiseForStatus=false.
     *
     * @return void
     */
    public function testValidateResponseStatusCode_with401_raiseForStatusFalse_doesNotThrow(): void
    {
        $response = new Response(401, [], json_encode(['errmsg' => 'Unauthorized']));
        
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('validateResponseStatusCode');

        // Should not throw when raiseForStatus is false
        $method->invoke($this->client, $response, false);
        
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test getErrorMessage with null response.
     *
     * @return void
     */
    public function testGetErrorMessage_withNullResponse_returnsDefaultMessage(): void
    {
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('getErrorMessage');

        $message = $method->invoke($this->client, null);
        
        $this->assertEquals("Request failed", $message);
    }

    /**
     * Test getErrorMessage with empty body.
     *
     * @return void
     */
    public function testGetErrorMessage_withEmptyBody_returnsStatusCodeMessage(): void
    {
        $response = new Response(500, [], '');
        
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('getErrorMessage');

        $message = $method->invoke($this->client, $response);
        
        $this->assertStringContainsString("500", $message);
    }

    /**
     * Test getErrorMessage with exception during processing.
     *
     * @return void
     */
    public function testGetErrorMessage_withException_returnsStatusCodeMessage(): void
    {
        // Create a mock response that throws an exception when getBody() is called
        // This tests the catch block in getErrorMessage
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->method('getBody')->willThrowException(new \RuntimeException('Stream error'));
        
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('getErrorMessage');

        $message = $method->invoke($this->client, $response);
        
        // Should return a message with status code when exception occurs
        $this->assertStringContainsString("500", $message);
        $this->assertStringContainsString("Request failed with status code", $message);
    }

    /**
     * Test extractRateLimitsFromResponse with null response.
     *
     * @return void
     */
    public function testExtractRateLimitsFromResponse_withNullResponse_returnsNull(): void
    {
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('extractRateLimitsFromResponse');

        $result = $method->invoke($this->client, null);
        
        $this->assertNull($result);
    }

    /**
     * Test sync execute exhausts retries and throws RequestError.
     *
     * @return void
     */
    public function testSyncExecute_exhaustsRetries_throwsRequestError(): void
    {
        // Mock enough failures to exhaust retries
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
        ]);

        $this->expectException(RequestError::class);

        $this->client->stocks->quote('AAPL');
    }

    /**
     * Test sync execute with non-retryable ServerException.
     *
     * @return void
     */
    public function testSyncExecute_withNonRetryableServerException_throwsImmediately(): void
    {
        // Mock 500 (non-retryable)
        $this->setMockResponses([
            new Response(500, [], json_encode(['errmsg' => 'Internal Server Error'])),
        ]);

        $this->expectException(RequestError::class);

        $this->client->stocks->quote('AAPL');
    }

    /**
     * Test sync execute exhausts retries and throws RequestError.
     *
     * @return void
     */
    public function testSyncExecute_maxAttemptsReached_throwsRequestError(): void
    {
        // Exhaust all retries - should throw RequestError with the error message from response
        // The fallback at line 456 is only reached in edge cases, so we test the normal retry exhaustion
        $this->setMockResponses([
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
            new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
        ]);

        $this->expectException(RequestError::class);
        // The exception will have the error message from the response, not the fallback message
        $this->expectExceptionMessage('Server Error');

        $this->client->stocks->quote('AAPL');
    }
}
