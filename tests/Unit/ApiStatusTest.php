<?php

namespace MarketDataApp\Tests\Unit;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatus;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatusData;
use MarketDataApp\Endpoints\Utilities;
use MarketDataApp\Enums\ApiStatusResult;
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
        $this->client = new Client("");
        Utilities::clearApiStatusCache();
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
}
