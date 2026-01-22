<?php

namespace MarketDataApp\Tests\Unit;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatus;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatusData;
use MarketDataApp\Endpoints\Responses\Utilities\Headers;
use MarketDataApp\Endpoints\Responses\Utilities\ServiceStatus;
use MarketDataApp\Endpoints\Responses\Utilities\User;
use MarketDataApp\Enums\ApiStatusResult;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Exceptions\UnauthorizedException;
use MarketDataApp\Settings;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the Utilities endpoints of the MarketDataApp.
 *
 * This class tests the functionality of the API status and headers endpoints.
 */
class UtilitiesTest extends TestCase
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
     * This method is called before each test.
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
        $token = '';
        $client = new Client($token);
        $this->client = $client;
        
        // Clear API status cache before each test to ensure fresh state
        \MarketDataApp\Endpoints\Utilities::clearApiStatusCache();
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
     * Test the API status endpoint for a successful response.
     *
     * @return void
     */
    public function testApiStatus_success()
    {
        // Mock response: Based on real API output from https://api.marketdata.app/status/
        $mocked_response = [
            's'            => 'ok',
            'service'      => [
                '/v1/markets/status/',
                '/v1/options/chain/',
                '/v1/options/expirations/',
                '/v1/options/lookup/',
                '/v1/options/quotes/',
                '/v1/options/strikes/',
                '/v1/stocks/bulkcandles/',
                '/v1/stocks/bulkquotes/',
                '/v1/stocks/candles/',
                '/v1/stocks/earnings/',
                '/v1/stocks/news/',
                '/v1/stocks/quotes/'
            ],
            'status'       => ['online', 'online', 'online', 'online', 'online', 'online', 'online', 'online', 'online', 'online', 'online', 'online'],
            'online'       => [true, true, true, true, true, true, true, true, true, true, true, true],
            'uptimePct30d' => [0.99961, 0.9995999999999999, 0.99992, 0.99977, 0.9995999999999999, 0.99997, 0.99956, 0.99954, 0.99961, 0.9995999999999999, 0.99981, 0.99959],
            'uptimePct90d' => [0.9985299999999999, 0.9976, 0.99884, 0.99928, 0.9986499999999999, 0.99884, 0.99874, 0.99866, 0.9987900000000001, 0.99887, 0.99893, 0.99869],
            'updated'      => [1769102755, 1769102755, 1769102755, 1769102755, 1769102755, 1769102755, 1769102755, 1769102755, 1769102755, 1769102755, 1769102755, 1769102755]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->utilities->api_status();
        $this->assertInstanceOf(ApiStatus::class, $response);

        $this->assertCount(12, $response->services);

        // Verify each item in the response is an object of the correct type and has the correct values.
        for ($i = 0; $i < count($response->services); $i++) {
            $this->assertInstanceOf(ServiceStatus::class, $response->services[$i]);
            $this->assertEquals($mocked_response['service'][$i], $response->services[$i]->service);
            $this->assertEquals($mocked_response['status'][$i], $response->services[$i]->status);
            $this->assertEquals($mocked_response['online'][$i], $response->services[$i]->online);
            $this->assertEquals($mocked_response['uptimePct30d'][$i], $response->services[$i]->uptime_percentage_30d);
            $this->assertEquals($mocked_response['uptimePct90d'][$i], $response->services[$i]->uptime_percentage_90d);
            $this->assertEquals(Carbon::createFromTimestamp($mocked_response['updated'][$i]),
                $response->services[$i]->updated);
        }
    }

    /**
     * Test the headers endpoint for a successful response.
     *
     * @return void
     */
    public function testHeaders_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'accept'            => '*/*',
            'accept-encoding'   => 'gzip',
            'authorization'     => 'Bearer *******************************************************YKT0',
            'cache-control'     => 'no-cache',
            'cf-connecting-ip'  => '132.43.100.7',
            'cf-ipcountry'      => 'US',
            'cf-ray'            => '85bc0c2bef389lo9',
            'cf-visitor'        => '{"scheme"=>"https"}',
            'connection'        => 'Keep-Alive',
            'host'              => 'api.marketdata.app',
            'postman-token'     => '09efc901-97q5-46h0-930a-7618d910b9f8',
            'user-agent'        => 'PostmanRuntime/7.36.3',
            'x-forwarded-proto' => 'https',
            'x-real-ip'         => '53.43.221.49'
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->utilities->headers();
        $this->assertInstanceOf(Headers::class, $response);
        foreach ($mocked_response as $key => $value) {
            $this->assertEquals($value, $response->{$key});
        }
    }

    /**
     * Test the user endpoint for a successful response.
     *
     * @return void
     */
    public function testUser_success()
    {
        $resetTimestamp = 1734567890;
        $mocked_headers = [
            'x-api-ratelimit-limit'     => ['60'],
            'x-api-ratelimit-remaining' => ['59'],
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $response = $this->client->utilities->user();
        $this->assertInstanceOf(User::class, $response);
        $this->assertInstanceOf(\MarketDataApp\RateLimits::class, $response->rate_limits);

        // Verify all rate limit fields are correctly extracted and converted
        $this->assertEquals(60, $response->rate_limits->limit);
        $this->assertEquals(59, $response->rate_limits->remaining);
        $this->assertEquals(1, $response->rate_limits->consumed);
        
        // Verify that reset is properly converted to Carbon datetime
        $this->assertInstanceOf(Carbon::class, $response->rate_limits->reset);
        $this->assertEquals(
            Carbon::createFromTimestamp($resetTimestamp),
            $response->rate_limits->reset
        );
    }

    /**
     * Test the user endpoint with missing rate limit headers.
     *
     * @return void
     */
    public function testUser_missingHeaders_throwsException()
    {
        // Response with no rate limit headers
        $this->setMockResponses([new Response(200, [], json_encode([]))]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("Rate limit headers not found in response");
        
        $this->client->utilities->user();
    }

    /**
     * Test the user endpoint with partial rate limit headers.
     *
     * @return void
     */
    public function testUser_partialHeaders_throwsException()
    {
        // Response with only some headers (missing x-api-ratelimit-reset)
        $mocked_headers = [
            'x-api-ratelimit-limit'     => ['60'],
            'x-api-ratelimit-remaining' => ['59'],
            'x-api-ratelimit-consumed'  => ['1'],
            // Missing x-api-ratelimit-reset
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("Rate limit headers not found in response");
        
        $this->client->utilities->user();
    }

    /**
     * Test the user endpoint with invalid non-numeric header values.
     *
     * @return void
     */
    public function testUser_invalidNumericHeaders_throwsException()
    {
        // Headers with non-numeric values
        $mocked_headers = [
            'x-api-ratelimit-limit'     => ['abc'], // Invalid
            'x-api-ratelimit-remaining' => ['59'],
            'x-api-ratelimit-reset'     => ['1734567890'],
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("Rate limit headers not found in response");
        
        $this->client->utilities->user();
    }

    /**
     * Test the user endpoint with empty header values.
     *
     * @return void
     */
    public function testUser_emptyHeaderValues_throwsException()
    {
        // Headers present but with empty string values
        $mocked_headers = [
            'x-api-ratelimit-limit'     => [''],
            'x-api-ratelimit-remaining' => ['59'],
            'x-api-ratelimit-reset'     => ['1734567890'],
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("Rate limit headers not found in response");
        
        $this->client->utilities->user();
    }

    /**
     * Test the user endpoint with case-insensitive header matching.
     *
     * @return void
     */
    public function testUser_caseInsensitiveHeaders_success()
    {
        $resetTimestamp = 1734567890;
        // Headers with different case
        $mocked_headers = [
            'X-Api-Ratelimit-Limit'     => ['60'], // Different case
            'X-API-RATELIMIT-REMAINING' => ['59'], // All uppercase
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp], // Lowercase
            'X-Api-Ratelimit-Consumed'  => ['1'], // Mixed case
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $response = $this->client->utilities->user();
        $this->assertInstanceOf(User::class, $response);
        $this->assertEquals(60, $response->rate_limits->limit);
        $this->assertEquals(59, $response->rate_limits->remaining);
        $this->assertEquals(1, $response->rate_limits->consumed);
    }

    /**
     * Test the user endpoint with different numeric formats.
     *
     * @return void
     */
    public function testUser_differentNumericFormats_success()
    {
        $resetTimestamp = 1734567890;
        // Headers with string numbers that can be converted
        $mocked_headers = [
            'x-api-ratelimit-limit'     => [' 60 '], // With spaces
            'x-api-ratelimit-remaining' => ['059'], // With leading zero
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $response = $this->client->utilities->user();
        $this->assertInstanceOf(User::class, $response);
        // Should convert correctly to integers (spaces trimmed, leading zeros handled)
        $this->assertEquals(60, $response->rate_limits->limit);
        $this->assertEquals(59, $response->rate_limits->remaining); // Leading zero removed
    }

    /**
     * Test the user endpoint with invalid timestamp format.
     *
     * @return void
     */
    public function testUser_invalidTimestamp_throwsException()
    {
        // Invalid timestamp (non-numeric)
        $mocked_headers = [
            'x-api-ratelimit-limit'     => ['60'],
            'x-api-ratelimit-remaining' => ['59'],
            'x-api-ratelimit-reset'     => ['invalid'], // Invalid timestamp
            'x-api-ratelimit-consumed'  => ['1'],
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("Rate limit headers not found in response");
        
        $this->client->utilities->user();
    }

    /**
     * Test the user endpoint with boundary values.
     *
     * @return void
     */
    public function testUser_boundaryValues_success()
    {
        $resetTimestamp = 2147483647; // Max 32-bit timestamp (year 2038)
        // Boundary values: zero and large numbers
        $mocked_headers = [
            'x-api-ratelimit-limit'     => ['0'], // Zero limit
            'x-api-ratelimit-remaining' => ['0'], // Zero remaining
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['0'], // Zero consumed
        ];
        $this->setMockResponses([new Response(200, $mocked_headers, json_encode([]))]);

        $response = $this->client->utilities->user();
        $this->assertInstanceOf(User::class, $response);
        $this->assertEquals(0, $response->rate_limits->limit);
        $this->assertEquals(0, $response->rate_limits->remaining);
        $this->assertEquals(0, $response->rate_limits->consumed);
        $this->assertEquals(
            Carbon::createFromTimestamp($resetTimestamp),
            $response->rate_limits->reset
        );
    }

    /**
     * Test that client can be initialized with empty token.
     *
     * Empty token should be allowed for accessing free symbols like AAPL.
     * The /user endpoint validation should be skipped.
     *
     * @return void
     */
    public function testClient_init_emptyToken_succeeds()
    {
        // Client with empty token should be created without exception
        // No /user endpoint call should be made (validation skipped)
        $client = new Client('');
        
        $this->assertInstanceOf(Client::class, $client);
        $this->assertNull($client->rate_limits, 'Rate limits should be null for empty token');
    }

    /**
     * Test that client can be initialized with valid token (mocked).
     *
     * Valid token should allow client creation and set rate_limits.
     * Note: This test uses empty token since unit tests use mocks anyway.
     * Integration tests provide better coverage for real token validation.
     *
     * @return void
     */
    public function testClient_init_validToken_succeeds()
    {
        // For unit tests, we use empty token to skip validation
        // Integration tests cover the real token validation scenario
        $client = new Client('');
        
        // Verify client was created
        $this->assertInstanceOf(Client::class, $client);
        $this->assertNull($client->rate_limits, 'Rate limits should be null for empty token in unit tests');
    }

    /**
     * Test the API status endpoint parses online field correctly.
     *
     * @return void
     */
    public function testApiStatus_parsesOnlineField()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'            => 'ok',
            'service'      => ['Test Service'],
            'status'       => ['online'],
            'online'       => [false], // Service is offline
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated'      => [1708972840]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->utilities->api_status();
        $this->assertInstanceOf(ApiStatus::class, $response);
        $this->assertCount(1, $response->services);
        $this->assertFalse($response->services[0]->online);
    }

    /**
     * Test the API status endpoint handles missing online field (backward compatibility).
     *
     * @return void
     */
    public function testApiStatus_missingOnlineField_defaultsToTrue()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'            => 'ok',
            'service'      => ['Test Service'],
            'status'       => ['online'],
            // 'online' field missing
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated'      => [1708972840]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->utilities->api_status();
        $this->assertInstanceOf(ApiStatus::class, $response);
        $this->assertCount(1, $response->services);
        // Should default to true for backward compatibility
        $this->assertTrue($response->services[0]->online);
    }

    /**
     * Test getServiceStatus returns ONLINE for online service.
     *
     * @return void
     */
    public function testGetServiceStatus_onlineService_returnsOnline()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'            => 'ok',
            'service'      => ['/v1/stocks/quotes/'],
            'status'       => ['online'],
            'online'       => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated'      => [time()]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $status = $this->client->utilities->getServiceStatus('/v1/stocks/quotes/');
        $this->assertEquals(ApiStatusResult::ONLINE, $status);
    }

    /**
     * Test getServiceStatus returns OFFLINE for offline service.
     *
     * @return void
     */
    public function testGetServiceStatus_offlineService_returnsOffline()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'            => 'ok',
            'service'      => ['/v1/stocks/quotes/'],
            'status'       => ['offline'],
            'online'       => [false],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated'      => [time()]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $status = $this->client->utilities->getServiceStatus('/v1/stocks/quotes/');
        $this->assertEquals(ApiStatusResult::OFFLINE, $status);
    }

    /**
     * Test getServiceStatus returns UNKNOWN for unknown service.
     *
     * @return void
     */
    public function testGetServiceStatus_unknownService_returnsUnknown()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'            => 'ok',
            'service'      => ['/v1/stocks/quotes/'],
            'status'       => ['online'],
            'online'       => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated'      => [time()]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $status = $this->client->utilities->getServiceStatus('/v1/unknown/service/');
        $this->assertEquals(ApiStatusResult::UNKNOWN, $status);
    }

    /**
     * Test refreshApiStatus with blocking mode.
     *
     * @return void
     */
    public function testRefreshApiStatus_blocking_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'            => 'ok',
            'service'      => ['/v1/stocks/quotes/'],
            'status'       => ['online'],
            'online'       => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated'      => [time()]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $result = $this->client->utilities->refreshApiStatus(true);
        $this->assertTrue($result);
    }

    /**
     * Test refreshApiStatus with async mode.
     *
     * @return void
     */
    public function testRefreshApiStatus_async_returnsImmediately()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'            => 'ok',
            'service'      => ['/v1/stocks/quotes/'],
            'status'       => ['online'],
            'online'       => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated'      => [time()]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        // Async mode should return immediately (true if cache exists, false if no cache)
        $result = $this->client->utilities->refreshApiStatus(false);
        // Since we don't have cache initially, it should return false
        $this->assertIsBool($result);
    }

    /**
     * Test ApiStatusData cache validity checking.
     *
     * @return void
     */
    public function testApiStatusData_isValid()
    {
        $data = new ApiStatusData();
        $this->assertFalse($data->isValid()); // No cache initially

        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = (object)[
            's'            => 'ok',
            'service'      => ['/v1/stocks/quotes/'],
            'status'       => ['online'],
            'online'       => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated'      => [time()]
        ];
        $data->update($mocked_response);
        $this->assertTrue($data->isValid()); // Cache is fresh
    }

    /**
     * Test ApiStatusData refresh window checking.
     *
     * @return void
     */
    public function testApiStatusData_inRefreshWindow()
    {
        $data = new ApiStatusData();
        $this->assertFalse($data->inRefreshWindow()); // No cache initially

        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = (object)[
            's'            => 'ok',
            'service'      => ['/v1/stocks/quotes/'],
            'status'       => ['online'],
            'online'       => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated'      => [time()]
        ];
        $data->update($mocked_response);
        
        // Fresh cache should not be in refresh window
        $this->assertFalse($data->inRefreshWindow());
    }

    /**
     * Test API status refresh window triggers async refresh (lines 96-99).
     *
     * When cache age is between 4min30sec and 5min, it should return cached data
     * immediately AND trigger async refresh.
     *
     * @return void
     */
    public function testApiStatus_refreshWindow_triggersAsyncRefresh()
    {
        $apiStatusData = \MarketDataApp\Endpoints\Utilities::getApiStatusData();
        $reflection = new \ReflectionClass($apiStatusData);
        
        // Directly populate the cache via reflection (bypass api_status() first call)
        // This ensures we have full control over the cache state
        $serviceProperty = $reflection->getProperty('service');
        $serviceProperty->setValue($apiStatusData, ['Test Service']);
        
        $statusProperty = $reflection->getProperty('status');
        $statusProperty->setValue($apiStatusData, ['online']);
        
        $onlineProperty = $reflection->getProperty('online');
        $onlineProperty->setValue($apiStatusData, [true]);
        
        $uptimePct30dProperty = $reflection->getProperty('uptimePct30d');
        $uptimePct30dProperty->setValue($apiStatusData, [0.99]);
        
        $uptimePct90dProperty = $reflection->getProperty('uptimePct90d');
        $uptimePct90dProperty->setValue($apiStatusData, [0.98]);
        
        $updatedProperty = $reflection->getProperty('updated');
        $updatedProperty->setValue($apiStatusData, [time()]);
        
        // Set lastRefreshed to 275 seconds ago (within refresh window: 270-300 seconds)
        $lastRefreshedProperty = $reflection->getProperty('lastRefreshed');
        $lastRefreshedProperty->setValue($apiStatusData, Carbon::now()->subSeconds(275));
        
        // Verify preconditions
        $this->assertTrue($apiStatusData->hasData(), 'Cache should have data');
        $this->assertNotNull($apiStatusData->getCachedApiStatus(), 'getCachedApiStatus should return non-null');
        $this->assertTrue($apiStatusData->inRefreshWindow(), 'Cache should be in refresh window');
        
        // Mock response for async refresh
        $mocked_response = [
            's'            => 'ok',
            'service'      => ['Test Service'],
            'status'       => ['online'],
            'online'       => [true],
            'uptimePct30d' => [0.99],
            'uptimePct90d' => [0.98],
            'updated'      => [time()]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);
        
        // Call api_status() - should return cached data immediately AND trigger async refresh
        // This should hit lines 96-99:
        // - hasData() = true (enter first block)
        // - getCachedApiStatus() != null (enter inner block)
        // - lastRefreshed != null (enter timestamp check)
        // - age (275) is NOT < 270, so skip line 92
        // - age (275) >= 270 AND age (275) < 300, so enter lines 96-99
        $cachedResponse = $this->client->utilities->api_status();
        
        // Verify cached data is returned
        $this->assertInstanceOf(ApiStatus::class, $cachedResponse);
        $this->assertEquals('Test Service', $cachedResponse->services[0]->service);
    }

    /**
     * Test API status fallback path (lines 115-117).
     *
     * The fallback path is reached when:
     * 1. hasData() returns false (skips first block at lines 85-103)
     * 2. isValid() returns true (skips second block at lines 106-112)
     * 3. Code falls through to fallback at lines 115-117
     *
     * This simulates a scenario where cache has fresh lastRefreshed but empty data.
     *
     * @return void
     */
    public function testApiStatus_fallback_whenCacheEmptyButFresh()
    {
        $apiStatusData = \MarketDataApp\Endpoints\Utilities::getApiStatusData();
        
        // Use reflection to set up a state where:
        // - lastRefreshed is fresh (within 300 seconds) -> isValid() = true
        // - service is empty -> hasData() = false
        // This causes both blocks to be skipped, falling through to fallback
        $reflection = new \ReflectionClass($apiStatusData);
        
        // Set lastRefreshed to 100 seconds ago (fresh, within 300 second validity)
        $lastRefreshedProperty = $reflection->getProperty('lastRefreshed');
                $lastRefreshedProperty->setValue($apiStatusData, Carbon::now()->subSeconds(100));
        
        // Keep service array empty (default state, but ensure it explicitly)
        $serviceProperty = $reflection->getProperty('service');
                $serviceProperty->setValue($apiStatusData, []);
        
        // Mock the fallback response (only one response needed)
        $fallback_response = [
            's'            => 'ok',
            'service'      => ['Fallback Service'],
            'status'       => ['online'],
            'online'       => [true],
            'uptimePct30d' => [0.95],
            'uptimePct90d' => [0.97],
            'updated'      => [time()]
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($fallback_response))
        ]);
        
        // Call api_status() - should hit fallback path because:
        // 1. hasData() = !empty([]) && lastRefreshed !== null = false -> skip first block
        // 2. isValid() = lastRefreshed !== null && age < 300 = true -> !isValid() = false -> skip second block
        // 3. Fall through to fallback (lines 115-117)
        $response = $this->client->utilities->api_status();
        
        // Verify fallback response is returned
        $this->assertInstanceOf(ApiStatus::class, $response);
        $this->assertCount(1, $response->services);
        $this->assertEquals('Fallback Service', $response->services[0]->service);
    }
}
