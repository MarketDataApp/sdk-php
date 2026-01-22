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
     * Test async RequestError catch block - retryable error that triggers retry logic.
     * 
     * This test covers lines 200-213 in ClientBase.php - the RequestError catch block
     * in the async promise then() handler when validateResponseStatusCode throws RequestError
     * and retry succeeds. This path is different from ServerException handling because
     * Guzzle returns a response (not throws) and validateResponseStatusCode throws RequestError.
     * 
     * Uses real 509 API ENDPOINT OVERLOADED response format from the API.
     *
     * @return void
     */
    public function testAsyncRequestErrorCatchBlock_retriesAndSucceeds(): void
    {
        // Create a custom Guzzle client that returns a 5xx response instead of throwing ServerException
        // This allows validateResponseStatusCode to throw RequestError, which is caught by the RequestError catch block
        // Using real 509 API ENDPOINT OVERLOADED response format
        $mockHandler = new MockHandler([
            new Response(509, [], json_encode(['s' => 'error', 'errmsg' => 'This API Endpoint is currently overloaded. Please try again in a few minutes. Write to support@marketdata.app or submit a ticket in the customer dashboard if this error continues for more than 15 minutes.'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockGuzzle = new \GuzzleHttp\Client([
            'handler' => $handlerStack,
            'http_errors' => false, // Don't throw exceptions for 4xx/5xx, return response instead
        ]);
        $this->client->setGuzzle($mockGuzzle);

        // This should succeed after retry
        $responses = $this->client->execute_in_parallel([['v1/stocks/quotes/AAPL', []]]);

        $this->assertCount(1, $responses);
        $this->assertIsObject($responses[0]);
    }

    /**
     * Test async RequestError catch block - retryable error that exhausts retries.
     * 
     * This test covers lines 200-215 in ClientBase.php - the RequestError catch block
     * in the async promise then() handler when validateResponseStatusCode throws RequestError
     * and retries are exhausted.
     * 
     * Uses real 509 API ENDPOINT OVERLOADED response format from the API.
     *
     * @return void
     */
    public function testAsyncRequestErrorCatchBlock_exhaustsRetries(): void
    {
        // Create a custom Guzzle client that returns 5xx responses instead of throwing ServerException
        // This allows validateResponseStatusCode to throw RequestError, which is caught by the RequestError catch block
        // Using real 509 API ENDPOINT OVERLOADED response format
        $errorMessage = 'This API Endpoint is currently overloaded. Please try again in a few minutes. Write to support@marketdata.app or submit a ticket in the customer dashboard if this error continues for more than 15 minutes.';
        $mockHandler = new MockHandler([
            new Response(509, [], json_encode(['s' => 'error', 'errmsg' => $errorMessage])),
            new Response(509, [], json_encode(['s' => 'error', 'errmsg' => $errorMessage])),
            new Response(509, [], json_encode(['s' => 'error', 'errmsg' => $errorMessage])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockGuzzle = new \GuzzleHttp\Client([
            'handler' => $handlerStack,
            'http_errors' => false, // Don't throw exceptions for 4xx/5xx, return response instead
        ]);
        $this->client->setGuzzle($mockGuzzle);

        $this->expectException(RequestError::class);
        $this->expectExceptionMessage($errorMessage);

        // This will call async(), which will get a 5xx response, validateResponseStatusCode will throw RequestError,
        // and the RequestError catch block will handle retries until exhausted
        $this->client->execute_in_parallel([['v1/stocks/quotes/AAPL', []]]);
    }

    /**
     * Test async RequestError catch block - service offline skips retries.
     * 
     * This test covers lines 200-204 in ClientBase.php - the RequestError catch block
     * when service is offline and retries should be skipped.
     * 
     * Uses real 509 API ENDPOINT OVERLOADED response format from the API.
     *
     * @return void
     */
    public function testAsyncRequestErrorCatchBlock_serviceOffline_skipsRetries(): void
    {
        // Mock ApiStatusData to return OFFLINE status
        $mockApiStatusData = $this->createMock(\MarketDataApp\Endpoints\Responses\Utilities\ApiStatusData::class);
        $mockApiStatusData->method('getApiStatus')
            ->willReturn(\MarketDataApp\Enums\ApiStatusResult::OFFLINE);

        // Use reflection to replace the singleton instance
        $utilitiesReflection = new \ReflectionClass(\MarketDataApp\Endpoints\Utilities::class);
        $apiStatusDataProperty = $utilitiesReflection->getProperty('apiStatusData');
                
        // Save original value
        $originalApiStatusData = $apiStatusDataProperty->getValue();
        
        try {
            // Replace with mock
            $apiStatusDataProperty->setValue(null, $mockApiStatusData);
            
            // Create a custom Guzzle client that returns a 5xx response instead of throwing ServerException
            // Using real 509 API ENDPOINT OVERLOADED response format
            $errorMessage = 'This API Endpoint is currently overloaded. Please try again in a few minutes. Write to support@marketdata.app or submit a ticket in the customer dashboard if this error continues for more than 15 minutes.';
            $mockHandler = new MockHandler([
                new Response(509, [], json_encode(['s' => 'error', 'errmsg' => $errorMessage])),
            ]);
            $handlerStack = HandlerStack::create($mockHandler);
            $mockGuzzle = new \GuzzleHttp\Client([
                'handler' => $handlerStack,
                'http_errors' => false, // Don't throw exceptions for 4xx/5xx, return response instead
            ]);
            $this->client->setGuzzle($mockGuzzle);

            $this->expectException(RequestError::class);
            $this->expectExceptionMessage($errorMessage);

            // This will call async(), which will get a 5xx response, validateResponseStatusCode will throw RequestError,
            // and the RequestError catch block will check service status and skip retries (throw immediately)
            $this->client->execute_in_parallel([['v1/stocks/quotes/AAPL', []]]);
        } finally {
            // Restore original singleton
            $apiStatusDataProperty->setValue(null, $originalApiStatusData);
        }
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
     * Test validateResponseStatusCode with retryable status code (5xx) throws RequestError.
     *
     * @return void
     */
    public function testValidateResponseStatusCode_withRetryableStatusCode_throwsRequestError(): void
    {
        $response = new Response(502, [], json_encode(['errmsg' => 'Bad Gateway']));
        
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('validateResponseStatusCode');

        $this->expectException(RequestError::class);
        $this->expectExceptionMessage('Bad Gateway');
        
        $method->invoke($this->client, $response, true);
    }

    /**
     * Test validateResponseStatusCode with 401 when raiseForStatus=true throws UnauthorizedException.
     *
     * @return void
     */
    public function testValidateResponseStatusCode_with401_raiseForStatusTrue_throwsUnauthorizedException(): void
    {
        $response = new Response(401, [], json_encode(['errmsg' => 'Unauthorized']));
        
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('validateResponseStatusCode');

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Unauthorized');
        
        $method->invoke($this->client, $response, true);
    }

    /**
     * Test validateResponseStatusCode with other 4xx status code throws BadStatusCodeError.
     *
     * @return void
     */
    public function testValidateResponseStatusCode_withOther4xx_throwsBadStatusCodeError(): void
    {
        $response = new Response(403, [], json_encode(['errmsg' => 'Forbidden']));
        
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('validateResponseStatusCode');

        $this->expectException(BadStatusCodeError::class);
        $this->expectExceptionMessage('Forbidden');
        
        $method->invoke($this->client, $response, true);
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

    /**
     * Test processResponse with CSV format and directory creation failure.
     *
     * @return void
     */
    public function testProcessResponse_withCsvFormat_directoryCreationFailure_throwsException(): void
    {
        // Create a CSV response
        $response = new Response(200, [], 'Symbol,Price\nAAPL,150.0');
        
        // Create a file where we want to create a directory - this will cause mkdir to fail
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_dir_', true);
        
        // Create a file with the same name as the directory we want to create
        touch($tempDir);
        
        // Clean up the file after test
        $this->addToAssertionCount(1); // Mark that we'll clean up
        register_shutdown_function(function() use ($tempDir) {
            if (file_exists($tempDir) && !is_dir($tempDir)) {
                unlink($tempDir);
            }
        });
        
        // Now try to save to a file in that "directory" - mkdir will fail because $tempDir is a file, not a directory
        $filename = $tempDir . '/subdir/test.csv';
        
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('processResponse');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create directory');
        
        // Use @ operator to suppress the expected warning from mkdir()
        @$method->invoke($this->client, $response, 'csv', ['format' => 'csv', '_filename' => $filename]);
    }

    /**
     * Test processResponse with CSV format and file write failure.
     * 
     * Unix-only: Uses read-only directory permissions which work differently on Windows.
     *
     * @return void
     */
    public function testProcessResponse_withCsvFormat_fileWriteFailure_throwsException(): void
    {
        // Pass on non-Unix platforms - test passes without running
        if (PHP_OS_FAMILY !== 'Linux' && PHP_OS_FAMILY !== 'Darwin') {
            $this->assertTrue(true);
            return;
        }
        
        // Create a CSV response
        $response = new Response(200, [], 'Symbol,Price\nAAPL,150.0');
        
        // Create a directory that exists but is read-only
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_readonly_', true);
        if (mkdir($tempDir, 0555, true)) {
            $filename = $tempDir . '/test.csv';
            
            // Clean up the directory after test
            $this->addToAssertionCount(1); // Mark that we'll clean up
            register_shutdown_function(function() use ($tempDir) {
                if (is_dir($tempDir)) {
                    // Restore permissions for cleanup
                    chmod($tempDir, 0755);
                    // Remove any files first
                    $files = glob($tempDir . '/*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                    rmdir($tempDir);
                }
            });
            
            $reflection = new ReflectionClass($this->client);
            $method = $reflection->getMethod('processResponse');
            
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Failed to write file');
            
            // Use @ operator to suppress the expected warning from file_put_contents()
            try {
                @$method->invoke($this->client, $response, 'csv', ['format' => 'csv', '_filename' => $filename]);
            } catch (\RuntimeException $e) {
                // Verify the error message
                $this->assertStringContainsString('Failed to write file', $e->getMessage());
                // Restore permissions for cleanup
                chmod($tempDir, 0755);
                throw $e;
            }
        } else {
            $this->markTestSkipped('Could not create read-only directory for testing');
        }
    }

    /**
     * Test processResponse with CSV format and file write failure on Windows.
     * 
     * Windows-only: Creates a read-only file and attempts to overwrite it, which should fail.
     *
     * @return void
     */
    public function testProcessResponse_withCsvFormat_fileWriteFailure_throwsExceptionWindows(): void
    {
        // Pass on non-Windows platforms - test passes without running
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertTrue(true);
            return;
        }
        
        // Create a CSV response
        $response = new Response(200, [], 'Symbol,Price\nAAPL,150.0');
        
        // Create a file and make it read-only, then try to overwrite it
        // On Windows, attempting to overwrite a read-only file should fail
        $tempDir = sys_get_temp_dir() . '\\' . uniqid('test_', true);
        if (mkdir($tempDir, 0755, true)) {
            $filename = $tempDir . '\\test.csv';
            
            // Create the file first
            file_put_contents($filename, 'existing content');
            
            // Make it read-only
            chmod($filename, 0444);
            
            // Clean up after test
            $this->addToAssertionCount(1); // Mark that we'll clean up
            register_shutdown_function(function() use ($tempDir, $filename) {
                if (file_exists($filename)) {
                    chmod($filename, 0644);
                    unlink($filename);
                }
                if (is_dir($tempDir)) {
                    rmdir($tempDir);
                }
            });
            
            $reflection = new ReflectionClass($this->client);
            $method = $reflection->getMethod('processResponse');
            
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Failed to write file');
            
            // Use @ operator to suppress the expected warning from file_put_contents()
            try {
                @$method->invoke($this->client, $response, 'csv', ['format' => 'csv', '_filename' => $filename]);
            } catch (\RuntimeException $e) {
                // Verify the error message
                $this->assertStringContainsString('Failed to write file', $e->getMessage());
                // Restore permissions for cleanup
                chmod($filename, 0644);
                throw $e;
            }
        } else {
            $this->markTestSkipped('Could not create test directory');
        }
    }

    /**
     * Test shouldSkipRetryDueToOfflineService with exception during status check.
     * 
     * This test covers the exception catch block (lines 773, 776) in shouldSkipRetryDueToOfflineService.
     * When the status check throws an exception, the method should return false (allowing retry).
     *
     * @return void
     */
    public function testShouldSkipRetryDueToOfflineService_withException_returnsFalse(): void
    {
        // Create a mock ApiStatusData that throws when getApiStatus is called
        $mockApiStatusData = $this->createMock(\MarketDataApp\Endpoints\Responses\Utilities\ApiStatusData::class);
        $mockApiStatusData->method('getApiStatus')
            ->willThrowException(new \RuntimeException('Status check failed'));

        // Use reflection to replace the singleton instance
        $utilitiesReflection = new \ReflectionClass(\MarketDataApp\Endpoints\Utilities::class);
        $apiStatusDataProperty = $utilitiesReflection->getProperty('apiStatusData');
                
        // Save original value
        $originalApiStatusData = $apiStatusDataProperty->getValue();
        
        try {
            // Replace with mock (for static properties, pass null as the object)
            $apiStatusDataProperty->setValue(null, $mockApiStatusData);
            
            // Make a request that triggers retry logic (5xx error)
            // This will call shouldSkipRetryDueToOfflineService, which will try to check status
            // The status check will throw, and the catch block should return false (allowing retry)
            $this->setMockResponses([
                new Response(502, [], json_encode(['errmsg' => 'Server Error'])),
                new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
            ]);

            // This should succeed because the exception in status check is caught and retry continues
            $result = $this->client->stocks->quote('AAPL');
            
            $this->assertNotNull($result);
            $this->assertIsObject($result);
        } finally {
            // Restore original singleton
            $apiStatusDataProperty->setValue(null, $originalApiStatusData);
        }
    }

    /**
     * Test async RequestException exhausts retries and throws RequestError.
     * 
     * This test covers lines 300-305 in ClientBase.php - the RequestException
     * handling path in async promise rejection handler when retries are exhausted.
     *
     * @return void
     */
    public function testAsyncRequestException_exhaustsRetries_throwsRequestError(): void
    {
        // Mock RequestException (network error) that exhausts all retries
        // MAX_RETRY_ATTEMPTS is 3, so we need 3 RequestExceptions
        $this->setMockResponses([
            new RequestException("Network Error", new Request('GET', 'v1/stocks/quotes/AAPL')),
            new RequestException("Network Error", new Request('GET', 'v1/stocks/quotes/AAPL')),
            new RequestException("Network Error", new Request('GET', 'v1/stocks/quotes/AAPL')),
        ]);

        $this->expectException(RequestError::class);
        $this->expectExceptionMessage('Request failed: Network Error');

        $this->client->execute_in_parallel([['v1/stocks/quotes/AAPL', []]]);
    }

    /**
     * Test sync execute with RequestError catch block - retryable error that exhausts retries.
     * 
     * This test covers lines 436-451 in ClientBase.php - the RequestError catch block
     * in the execute() method when validateResponseStatusCode throws RequestError
     * and retries are exhausted.
     *
     * @return void
     */
    public function testSyncExecute_withRequestErrorCatchBlock_exhaustsRetries(): void
    {
        // Create a custom Guzzle client that returns a 5xx response instead of throwing ServerException
        // This allows validateResponseStatusCode to throw RequestError, which is caught by the RequestError catch block
        $mockHandler = new MockHandler([
            new Response(502, [], json_encode(['errmsg' => 'Bad Gateway'])),
            new Response(502, [], json_encode(['errmsg' => 'Bad Gateway'])),
            new Response(502, [], json_encode(['errmsg' => 'Bad Gateway'])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockGuzzle = new \GuzzleHttp\Client([
            'handler' => $handlerStack,
            'http_errors' => false, // Don't throw exceptions for 4xx/5xx, return response instead
        ]);
        $this->client->setGuzzle($mockGuzzle);

        $this->expectException(RequestError::class);
        $this->expectExceptionMessage('Bad Gateway');

        // This will call execute(), which will get a 5xx response, validateResponseStatusCode will throw RequestError,
        // and the RequestError catch block will handle retries until exhausted
        $this->client->stocks->quote('AAPL');
    }

    /**
     * Test sync execute with RequestError catch block - retryable error that succeeds after retry.
     * 
     * This test covers lines 436-447 in ClientBase.php - the RequestError catch block
     * in the execute() method when validateResponseStatusCode throws RequestError
     * and retry succeeds.
     *
     * @return void
     */
    public function testSyncExecute_withRequestErrorCatchBlock_retriesAndSucceeds(): void
    {
        // Create a custom Guzzle client that returns a 5xx response then succeeds
        $mockHandler = new MockHandler([
            new Response(502, [], json_encode(['errmsg' => 'Bad Gateway'])),
            new Response(200, [], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200], 'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33], 'volume' => [1000000], 'updated' => [1234567890]])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockGuzzle = new \GuzzleHttp\Client([
            'handler' => $handlerStack,
            'http_errors' => false, // Don't throw exceptions for 4xx/5xx, return response instead
        ]);
        $this->client->setGuzzle($mockGuzzle);

        // This should succeed after retry
        $result = $this->client->stocks->quote('AAPL');

        $this->assertNotNull($result);
        $this->assertIsObject($result);
    }

    /**
     * Test sync execute with RequestError catch block - service offline skips retries.
     * 
     * This test covers lines 436-441 in ClientBase.php - the RequestError catch block
     * when service is offline and retries should be skipped.
     *
     * @return void
     */
    public function testSyncExecute_withRequestErrorCatchBlock_serviceOffline_skipsRetries(): void
    {
        // Mock ApiStatusData to return OFFLINE status
        $mockApiStatusData = $this->createMock(\MarketDataApp\Endpoints\Responses\Utilities\ApiStatusData::class);
        $mockApiStatusData->method('getApiStatus')
            ->willReturn(\MarketDataApp\Enums\ApiStatusResult::OFFLINE);

        // Use reflection to replace the singleton instance
        $utilitiesReflection = new \ReflectionClass(\MarketDataApp\Endpoints\Utilities::class);
        $apiStatusDataProperty = $utilitiesReflection->getProperty('apiStatusData');
                
        // Save original value
        $originalApiStatusData = $apiStatusDataProperty->getValue();
        
        try {
            // Replace with mock
            $apiStatusDataProperty->setValue(null, $mockApiStatusData);
            
            // Create a custom Guzzle client that returns a 5xx response instead of throwing ServerException
            $mockHandler = new MockHandler([
                new Response(502, [], json_encode(['errmsg' => 'Bad Gateway'])),
            ]);
            $handlerStack = HandlerStack::create($mockHandler);
            $mockGuzzle = new \GuzzleHttp\Client([
                'handler' => $handlerStack,
                'http_errors' => false, // Don't throw exceptions for 4xx/5xx, return response instead
            ]);
            $this->client->setGuzzle($mockGuzzle);

            $this->expectException(RequestError::class);
            $this->expectExceptionMessage('Bad Gateway');

            // This will call execute(), which will get a 5xx response, validateResponseStatusCode will throw RequestError,
            // and the RequestError catch block will check service status and skip retries (throw immediately)
            $this->client->stocks->quote('AAPL');
        } finally {
            // Restore original singleton
            $apiStatusDataProperty->setValue(null, $originalApiStatusData);
        }
    }

    /**
     * Test async promise rejection handler - re-throw other exceptions.
     * 
     * This test covers line 309 in ClientBase.php - the re-throw of other exceptions
     * in the async promise rejection handler when the exception is not a ServerException,
     * ClientException, or RequestException.
     *
     * @return void
     */
    public function testAsyncPromiseRejection_otherException_rethrows(): void
    {
        // Create a custom Guzzle client that throws a non-Guzzle exception
        // This will trigger the "other exceptions" path at line 309
        $mockHandler = new MockHandler([
            new \RuntimeException('Unexpected error'),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockGuzzle = new \GuzzleHttp\Client([
            'handler' => $handlerStack,
        ]);
        $this->client->setGuzzle($mockGuzzle);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected error');

        // This will call async(), which will get a RuntimeException, and it should be re-thrown at line 309
        $this->client->execute_in_parallel([['v1/stocks/quotes/AAPL', []]]);
    }

    /**
     * Test async BadStatusCodeError catch block - non-retryable 4xx error.
     * 
     * This test covers lines 216-218 in ClientBase.php - the BadStatusCodeError catch block
     * in the async promise then() handler when validateResponseStatusCode throws BadStatusCodeError
     * for non-retryable 4xx errors (like 400 Bad Request or 403 Forbidden).
     * 
     * The key to hitting this path is:
     * 1. Use http_errors => false so Guzzle returns the response instead of throwing ClientException
     * 2. The response has a 4xx status code (not 401 which throws UnauthorizedException)
     * 3. validateResponseStatusCode is called and throws BadStatusCodeError
     *
     * @return void
     */
    public function testAsyncBadStatusCodeErrorCatchBlock_nonRetryable4xx_throwsImmediately(): void
    {
        // Create a custom Guzzle client that returns a 4xx response instead of throwing ClientException
        // This allows validateResponseStatusCode to throw BadStatusCodeError, which is caught by the catch block
        // Using 400 Bad Request as an example of a non-retryable 4xx error
        $errorMessage = 'Bad Request - Invalid parameters provided';
        $mockHandler = new MockHandler([
            new Response(400, [], json_encode(['s' => 'error', 'errmsg' => $errorMessage])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockGuzzle = new \GuzzleHttp\Client([
            'handler' => $handlerStack,
            'http_errors' => false, // Don't throw exceptions for 4xx/5xx, return response instead
        ]);
        $this->client->setGuzzle($mockGuzzle);

        $this->expectException(BadStatusCodeError::class);
        $this->expectExceptionMessage($errorMessage);

        // This will call async(), which will get a 400 response, validateResponseStatusCode will throw BadStatusCodeError,
        // and the BadStatusCodeError catch block will re-throw it immediately (no retry for 4xx)
        $this->client->execute_in_parallel([['v1/stocks/quotes/AAPL', []]]);
    }

    /**
     * Test async BadStatusCodeError catch block - 403 Forbidden error.
     * 
     * This test covers lines 216-218 in ClientBase.php - additional coverage for the BadStatusCodeError catch block
     * with a 403 Forbidden status code to ensure the path is covered.
     *
     * @return void
     */
    public function testAsyncBadStatusCodeErrorCatchBlock_403Forbidden_throwsImmediately(): void
    {
        // Create a custom Guzzle client that returns a 403 Forbidden response
        $errorMessage = 'Access denied - insufficient permissions';
        $mockHandler = new MockHandler([
            new Response(403, [], json_encode(['s' => 'error', 'errmsg' => $errorMessage])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockGuzzle = new \GuzzleHttp\Client([
            'handler' => $handlerStack,
            'http_errors' => false, // Don't throw exceptions for 4xx/5xx, return response instead
        ]);
        $this->client->setGuzzle($mockGuzzle);

        $this->expectException(BadStatusCodeError::class);
        $this->expectExceptionMessage($errorMessage);

        // This will call async(), which will get a 403 response, validateResponseStatusCode will throw BadStatusCodeError,
        // and the BadStatusCodeError catch block will re-throw it immediately (no retry for 4xx)
        $this->client->execute_in_parallel([['v1/stocks/quotes/AAPL', []]]);
    }
}
