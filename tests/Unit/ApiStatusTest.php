<?php

namespace MarketDataApp\Tests\Unit;

use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatus;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatusData;
use MarketDataApp\Endpoints\Utilities;
use MarketDataApp\Enums\ApiStatusResult;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Settings;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Test case for ApiStatus and ApiStatusData classes.
 *
 * This class tests constructor edge cases, validation methods, and getter methods.
 */
class ApiStatusTest extends TestCase
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
        
        $this->client = new Client("");
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
     * Test ApiStatus constructor with response missing online field.
     *
     * @return void
     */
    public function testApiStatus_constructor_withMissingOnlineField_defaultsToTrue()
    {
        // Create response without online field
        $response = (object)[
            's' => 'ok',
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];

        $apiStatus = new ApiStatus($response);

        $this->assertCount(1, $apiStatus->services);
        // Online field should default to true when missing
        $this->assertTrue($apiStatus->services[0]->online);
    }

    /**
     * Test ApiStatusData update with missing service field.
     *
     * @return void
     */
    public function testApiStatusData_update_withMissingServiceField_throwsException()
    {
        $data = new ApiStatusData();
        $invalidData = (object)[
            'status' => ['online'],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('service field is missing or not an array');

        $data->update($invalidData);
    }

    /**
     * Test ApiStatusData update with invalid service field (not array).
     *
     * @return void
     */
    public function testApiStatusData_update_withInvalidServiceField_throwsException()
    {
        $data = new ApiStatusData();
        $invalidData = (object)[
            'service' => 'not_an_array',
            'status' => ['online'],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('service field is missing or not an array');

        $data->update($invalidData);
    }

    /**
     * Test ApiStatusData update with missing status field.
     *
     * @return void
     */
    public function testApiStatusData_update_withMissingStatusField_throwsException()
    {
        $data = new ApiStatusData();
        $invalidData = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('status field is missing or not an array');

        $data->update($invalidData);
    }

    /**
     * Test ApiStatusData update with missing uptimePct30d field.
     *
     * @return void
     */
    public function testApiStatusData_update_withMissingUptimePct30dField_throwsException()
    {
        $data = new ApiStatusData();
        $invalidData = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('uptimePct30d field is missing or not an array');

        $data->update($invalidData);
    }

    /**
     * Test ApiStatusData update with missing uptimePct90d field.
     *
     * @return void
     */
    public function testApiStatusData_update_withMissingUptimePct90dField_throwsException()
    {
        $data = new ApiStatusData();
        $invalidData = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'uptimePct30d' => [0.99],
            'updated' => [time()]
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('uptimePct90d field is missing or not an array');

        $data->update($invalidData);
    }

    /**
     * Test ApiStatusData update with missing updated field.
     *
     * @return void
     */
    public function testApiStatusData_update_withMissingUpdatedField_throwsException()
    {
        $data = new ApiStatusData();
        $invalidData = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98]
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('updated field is missing or not an array');

        $data->update($invalidData);
    }

    /**
     * Test refreshBlocking with exception when cache exists.
     *
     * @return void
     */
    public function testRefreshBlocking_withExceptionWhenCacheExists_returnsFalse()
    {
        $data = new ApiStatusData();
        
        // Set up existing cache
        $cachedResponse = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'online' => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $data->update($cachedResponse);

        // Mock a failure during refresh
        $this->setMockResponses([
            new Response(500, [], json_encode(['errmsg' => 'Server Error'])),
        ]);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($data);
        $method = $reflection->getMethod('refreshBlocking');

        $result = $method->invoke($data, $this->client);
        
        // Should return false when cache exists and refresh fails
        $this->assertFalse($result);
    }

    /**
     * Test refreshBlocking with exception when no cache exists.
     *
     * @return void
     */
    public function testRefreshBlocking_withExceptionWhenNoCache_throwsException()
    {
        $data = new ApiStatusData();

        // Mock a failure during refresh
        $this->setMockResponses([
            new Response(500, [], json_encode(['errmsg' => 'Server Error'])),
        ]);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($data);
        $method = $reflection->getMethod('refreshBlocking');

        $this->expectException(\Exception::class);

        $method->invoke($data, $this->client);
    }

    /**
     * Test getApiStatus with skipBlockingRefresh=true.
     *
     * @return void
     */
    public function testGetApiStatus_withSkipBlockingRefresh_returnsUnknown()
    {
        $data = new ApiStatusData();
        
        // No cache - should return UNKNOWN when skipBlockingRefresh is true
        $result = $data->getApiStatus($this->client, '/v1/stocks/quotes/', true);
        
        $this->assertEquals(ApiStatusResult::UNKNOWN, $result);
    }

    /**
     * Test getServiceStatus with empty service array.
     *
     * @return void
     */
    public function testGetServiceStatus_withEmptyServiceArray_returnsUnknown()
    {
        $data = new ApiStatusData();
        
        // Use reflection to call private method
        $reflection = new \ReflectionClass($data);
        $method = $reflection->getMethod('getServiceStatus');

        $result = $method->invoke($data, '/v1/stocks/quotes/');
        
        $this->assertEquals(ApiStatusResult::UNKNOWN, $result);
    }

    /**
     * Test getServiceStatus with service not found.
     *
     * @return void
     */
    public function testGetServiceStatus_withServiceNotFound_returnsUnknown()
    {
        $data = new ApiStatusData();
        $response = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'online' => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $data->update($response);
        
        // Use reflection to call private method
        $reflection = new \ReflectionClass($data);
        $method = $reflection->getMethod('getServiceStatus');

        $result = $method->invoke($data, '/v1/nonexistent/service/');
        
        $this->assertEquals(ApiStatusResult::UNKNOWN, $result);
    }

    /**
     * Test getServiceStatus with status offline.
     *
     * @return void
     */
    public function testGetServiceStatus_withStatusOffline_returnsOffline()
    {
        $data = new ApiStatusData();
        $response = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['offline'],
            'online' => [false],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $data->update($response);
        
        // Use reflection to call private method
        $reflection = new \ReflectionClass($data);
        $method = $reflection->getMethod('getServiceStatus');

        $result = $method->invoke($data, '/v1/stocks/quotes/');
        
        $this->assertEquals(ApiStatusResult::OFFLINE, $result);
    }

    /**
     * Test getServiceStatus with online field false.
     *
     * @return void
     */
    public function testGetServiceStatus_withOnlineFieldFalse_returnsOffline()
    {
        $data = new ApiStatusData();
        $response = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'online' => [false], // Online field is false
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $data->update($response);
        
        // Use reflection to call private method
        $reflection = new \ReflectionClass($data);
        $method = $reflection->getMethod('getServiceStatus');

        $result = $method->invoke($data, '/v1/stocks/quotes/');
        
        // Status says online but online field is false - should return offline
        $this->assertEquals(ApiStatusResult::OFFLINE, $result);
    }

    /**
     * Test getServiceStatus with status online but online field missing for the index.
     *
     * This tests the edge case where status indicates online but the online array
     * doesn't have an entry for that service index.
     *
     * @return void
     */
    public function testGetServiceStatus_withStatusOnlineButOnlineFieldMissing_returnsOffline()
    {
        $data = new ApiStatusData();
        $response = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'online' => [], // Empty online array - missing entry for index 0
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $data->update($response);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($data);
        $method = $reflection->getMethod('getServiceStatus');

        $result = $method->invoke($data, '/v1/stocks/quotes/');

        // Status says online but online field is missing - should return offline
        $this->assertEquals(ApiStatusResult::OFFLINE, $result);
    }

    /**
     * Test getServiceStatus with status online and online field true.
     *
     * @return void
     */
    public function testGetServiceStatus_withStatusOnlineAndOnlineTrue_returnsOnline()
    {
        $data = new ApiStatusData();
        $response = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'online' => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $data->update($response);
        
        // Use reflection to call private method
        $reflection = new \ReflectionClass($data);
        $method = $reflection->getMethod('getServiceStatus');

        $result = $method->invoke($data, '/v1/stocks/quotes/');
        
        $this->assertEquals(ApiStatusResult::ONLINE, $result);
    }

    /**
     * Test getServiceStatus with unknown status.
     *
     * @return void
     */
    public function testGetServiceStatus_withUnknownStatus_returnsUnknown()
    {
        $data = new ApiStatusData();
        $response = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['unknown_status'],
            'online' => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $data->update($response);
        
        // Use reflection to call private method
        $reflection = new \ReflectionClass($data);
        $method = $reflection->getMethod('getServiceStatus');

        $result = $method->invoke($data, '/v1/stocks/quotes/');
        
        // Should default to unknown if we can't determine
        $this->assertEquals(ApiStatusResult::UNKNOWN, $result);
    }

    /**
     * Test getCachedApiStatus when hasData returns false.
     *
     * @return void
     */
    public function testGetCachedApiStatus_whenHasDataReturnsFalse_returnsNull()
    {
        $data = new ApiStatusData();
        
        // No data - hasData() will return false
        $result = $data->getCachedApiStatus();
        
        $this->assertNull($result);
    }

    /**
     * Test getCachedApiStatus when hasData returns true.
     *
     * @return void
     */
    public function testGetCachedApiStatus_whenHasDataReturnsTrue_returnsApiStatus()
    {
        $data = new ApiStatusData();
        $response = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'online' => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $data->update($response);
        
        $result = $data->getCachedApiStatus();
        
        $this->assertNotNull($result);
        $this->assertInstanceOf(ApiStatus::class, $result);
    }

    /**
     * Test refreshAsync prevents duplicate concurrent refreshes (line 172).
     *
     * @return void
     */
    public function testRefreshAsync_duplicateCall_preventsDuplicatePromises()
    {
        $data = new ApiStatusData();
        
        // Set up existing cache
        $cachedResponse = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'online' => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $data->update($cachedResponse);

        // Mock successful response
        $this->setMockResponses([
            new Response(200, [], json_encode([
                's' => 'ok',
                'service' => ['/v1/stocks/quotes/'],
                'status' => ['online'],
                'online' => [true],
                'uptimePct30d' => [0.99],
                'uptimePct90d' => [0.98],
                'updated' => [time()]
            ])),
        ]);

        // Call refreshAsync twice
        $data->refreshAsync($this->client);
        
        // Use reflection to check refreshPromise is set
        $reflection = new \ReflectionClass($data);
        $refreshPromiseProperty = $reflection->getProperty('refreshPromise');
                $firstPromise = $refreshPromiseProperty->getValue($data);
        
        $this->assertNotNull($firstPromise, 'First promise should be created');

        // Call refreshAsync again - should return early without creating new promise
        $data->refreshAsync($this->client);
        
        // Verify the same promise is still there (not replaced)
        $secondPromise = $refreshPromiseProperty->getValue($data);
        $this->assertSame($firstPromise, $secondPromise, 'Second call should not create new promise');

        // Wait for promise to complete
        Utils::settle([$firstPromise])->wait();
    }

    /**
     * Test refreshAsync throws ApiException on error response (line 199).
     *
     * @return void
     */
    public function testRefreshAsync_errorResponse_throwsApiException()
    {
        $data = new ApiStatusData();
        
        // Set up existing cache
        $cachedResponse = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'online' => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $data->update($cachedResponse);

        // Mock error response
        $errorResponse = new Response(200, [], json_encode([
            's' => 'error',
            'errmsg' => 'Test error message'
        ]));
        $this->setMockResponses([$errorResponse]);

        // Trigger async refresh
        $data->refreshAsync($this->client);

        // Use reflection to get the promise
        $reflection = new \ReflectionClass($data);
        $refreshPromiseProperty = $reflection->getProperty('refreshPromise');
                $promise = $refreshPromiseProperty->getValue($data);

        // Wait for promise to complete and handlers to execute
        // The exception is caught in the promise handler (line 204), so we need to wait
        // for the promise to resolve and the handler to execute
        try {
            $promise->wait();
        } catch (\Exception $e) {
            // Exception may bubble up depending on promise implementation
        }

        // Poll until promise is cleared (handlers have executed)
        $maxAttempts = 100;
        $attempt = 0;
        $promiseAfter = $refreshPromiseProperty->getValue($data);
        while ($promiseAfter !== null && $attempt < $maxAttempts) {
            usleep(10000); // 10ms
            $promiseAfter = $refreshPromiseProperty->getValue($data);
            $attempt++;
        }

        // Verify promise is cleared (happens in finally block after line 199 exception is caught at line 204)
        $this->assertNull($promiseAfter, 'Promise should be cleared after completion');

        // Verify cache is preserved (not updated with error response)
        $this->assertTrue($data->hasData(), 'Cache should be preserved');
    }

    /**
     * Test refreshAsync exception handler preserves cache (line 204).
     *
     * @return void
     */
    public function testRefreshAsync_exceptionInHandler_preservesCache()
    {
        $data = new ApiStatusData();
        
        // Set up existing cache with known values
        $cachedResponse = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'online' => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $data->update($cachedResponse);
        
        $originalLastRefreshed = $data->getLastRefreshed();

        // Mock a response that will cause an exception during processing
        // Use a response that will fail validation or cause json_decode to fail
        // We'll use a response that causes validateResponseStatusCode to throw
        $this->setMockResponses([
            new Response(500, [], json_encode(['errmsg' => 'Server Error'])),
        ]);

        // Trigger async refresh
        $data->refreshAsync($this->client);

        // Use reflection to get the promise
        $reflection = new \ReflectionClass($data);
        $refreshPromiseProperty = $reflection->getProperty('refreshPromise');
                $promise = $refreshPromiseProperty->getValue($data);

        // Wait for promise to complete (exception will be caught at line 204)
        try {
            $promise->wait();
        } catch (\Exception $e) {
            // Exception is expected and caught in handler
        }

        // Poll until promise is cleared (handlers have executed)
        $maxAttempts = 100;
        $attempt = 0;
        $promiseAfter = $refreshPromiseProperty->getValue($data);
        while ($promiseAfter !== null && $attempt < $maxAttempts) {
            usleep(10000); // 10ms
            $promiseAfter = $refreshPromiseProperty->getValue($data);
            $attempt++;
        }

        // Verify promise is cleared (happens in finally block)
        $this->assertNull($promiseAfter, 'Promise should be cleared after exception');

        // Verify cache is preserved (not updated)
        $this->assertTrue($data->hasData(), 'Cache should be preserved');
        $this->assertEquals($originalLastRefreshed, $data->getLastRefreshed(), 'Last refreshed should not change');
    }

    /**
     * Test refreshAsync promise rejection handler (line 214).
     *
     * @return void
     */
    public function testRefreshAsync_networkFailure_handlesRejection()
    {
        $data = new ApiStatusData();
        
        // Set up existing cache
        $cachedResponse = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'online' => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $data->update($cachedResponse);
        
        $originalLastRefreshed = $data->getLastRefreshed();

        // Mock network failure (RequestException)
        $this->setMockResponses([
            new RequestException("Network Error", new Request('GET', 'status/')),
        ]);

        // Trigger async refresh
        $data->refreshAsync($this->client);

        // Use reflection to get the promise
        $reflection = new \ReflectionClass($data);
        $refreshPromiseProperty = $reflection->getProperty('refreshPromise');
                $promise = $refreshPromiseProperty->getValue($data);

        // Wait for promise rejection (line 214 handler should execute)
        try {
            $promise->wait();
        } catch (\Exception $e) {
            // Exception is expected (RequestException)
        }

        // Poll until promise is cleared (rejection handler at line 214 should execute)
        $maxAttempts = 100;
        $attempt = 0;
        $promiseAfter = $refreshPromiseProperty->getValue($data);
        while ($promiseAfter !== null && $attempt < $maxAttempts) {
            usleep(10000); // 10ms
            $promiseAfter = $refreshPromiseProperty->getValue($data);
            $attempt++;
        }

        // Verify promise is cleared (line 214)
        $this->assertNull($promiseAfter, 'Promise should be cleared after rejection');

        // Verify cache is preserved
        $this->assertTrue($data->hasData(), 'Cache should be preserved');
        $this->assertEquals($originalLastRefreshed, $data->getLastRefreshed(), 'Last refreshed should not change');
    }

    /**
     * Test getApiStatus triggers async refresh in refresh window (lines 237-239).
     *
     * @return void
     */
    public function testGetApiStatus_refreshWindow_triggersAsyncRefresh()
    {
        $data = new ApiStatusData();
        
        // Set up cache with data
        $cachedResponse = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'online' => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $data->update($cachedResponse);

        // Use reflection to set lastRefreshed to 275 seconds ago (in refresh window: 270-300 seconds)
        $reflection = new \ReflectionClass($data);
        $lastRefreshedProperty = $reflection->getProperty('lastRefreshed');
                $lastRefreshedProperty->setValue($data, Carbon::now()->subSeconds(275));

        // Verify cache is in refresh window
        $this->assertTrue($data->inRefreshWindow(), 'Cache should be in refresh window');

        // Mock response for async refresh
        $this->setMockResponses([
            new Response(200, [], json_encode([
                's' => 'ok',
                'service' => ['/v1/stocks/quotes/'],
                'status' => ['online'],
                'online' => [true],
                'uptimePct30d' => [0.99],
                'uptimePct90d' => [0.98],
                'updated' => [time()]
            ])),
        ]);

        // Call getApiStatus - should trigger async refresh and return cached data immediately
        $result = $data->getApiStatus($this->client, '/v1/stocks/quotes/');

        // Verify cached data is returned immediately
        $this->assertEquals(ApiStatusResult::ONLINE, $result, 'Should return cached status immediately');

        // Verify async refresh was triggered (promise should be created)
        $refreshPromiseProperty = $reflection->getProperty('refreshPromise');
                $promise = $refreshPromiseProperty->getValue($data);
        $this->assertNotNull($promise, 'Async refresh promise should be created');

        // Wait for promise to complete
        try {
            $promise->wait();
        } catch (\Exception $e) {
            // Exception may occur
        }
        
        // Give a small delay to ensure promise handlers execute
        usleep(10000); // 10ms
    }

    /**
     * Test getApiStatus fallback return path (line 256).
     *
     * This tests the scenario where cache is valid after stale check.
     *
     * @return void
     */
    public function testGetApiStatus_validCacheAfterStaleCheck_returnsStatus()
    {
        $data = new ApiStatusData();
        
        // Set up cache with data that is valid but not in refresh window
        // Set to 100 seconds ago (valid, but not in refresh window)
        $cachedResponse = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'],
            'online' => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $data->update($cachedResponse);

        // Use reflection to set lastRefreshed to 100 seconds ago
        // This makes cache valid (age < 300) but not in refresh window (age < 270)
        $reflection = new \ReflectionClass($data);
        $lastRefreshedProperty = $reflection->getProperty('lastRefreshed');
                $lastRefreshedProperty->setValue($data, Carbon::now()->subSeconds(100));

        // Verify cache is valid but not in refresh window
        $this->assertTrue($data->isValid(), 'Cache should be valid');
        $this->assertFalse($data->inRefreshWindow(), 'Cache should not be in refresh window');

        // Call getApiStatus - should hit the fallback return at line 256
        $result = $data->getApiStatus($this->client, '/v1/stocks/quotes/');

        // Verify status is returned
        $this->assertEquals(ApiStatusResult::ONLINE, $result, 'Should return status from cache');

        // Verify no async refresh was triggered (cache is fresh)
        $refreshPromiseProperty = $reflection->getProperty('refreshPromise');
                $promise = $refreshPromiseProperty->getValue($data);
        $this->assertNull($promise, 'No async refresh should be triggered for fresh cache');
    }

    /**
     * Test getServiceStatus with status online but online field false (line 292).
     *
     * This edge case is already tested in testGetServiceStatus_withOnlineFieldFalse_returnsOffline,
     * but we verify it covers line 292 specifically.
     *
     * @return void
     */
    public function testGetServiceStatus_statusOnlineButOnlineFalse_returnsOffline()
    {
        $data = new ApiStatusData();
        $response = (object)[
            'service' => ['/v1/stocks/quotes/'],
            'status' => ['online'], // Status says online
            'online' => [false], // But online field is false
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated' => [time()]
        ];
        $data->update($response);
        
        // Use reflection to call private method
        $reflection = new \ReflectionClass($data);
        $method = $reflection->getMethod('getServiceStatus');

        $result = $method->invoke($data, '/v1/stocks/quotes/');
        
        // Status says online but online field is false - should return offline (line 292)
        $this->assertEquals(ApiStatusResult::OFFLINE, $result);
    }
}
