<?php

namespace MarketDataApp\Tests\Unit\Utilities;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Utilities;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for URL construction in Utilities endpoints.
 *
 * These tests verify that the SDK constructs URLs correctly according to the API documentation.
 *
 * API Documentation: https://www.marketdata.app/docs/api/utilities
 */
class UrlConstructionTest extends TestCase
{
    use MockResponses;

    private Client $client;
    private array $history = [];

    protected function setUp(): void
    {
        $this->saveMarketDataTokenState();
        $this->clearMarketDataToken();
        $this->client = new Client('');
        $this->history = [];

        // Clear the API status cache before each test
        Utilities::clearApiStatusCache();
    }

    protected function tearDown(): void
    {
        $this->restoreMarketDataTokenState();
        Utilities::clearApiStatusCache();
        parent::tearDown();
    }

    /**
     * Set up mock responses with history middleware to capture requests.
     */
    private function setMockResponsesWithHistory(array $responses): void
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($this->history));
        $this->client->setGuzzle(new GuzzleClient(['handler' => $handlerStack]));
    }

    /**
     * Get the last request's URI path.
     */
    private function getLastRequestPath(): string
    {
        return $this->history[0]['request']->getUri()->getPath();
    }

    // ========================================================================
    // UTILITIES STATUS ENDPOINT
    // API: GET /status/
    // Note: The Utilities class doesn't have a BASE_URL prefix like other endpoints
    // ========================================================================

    /**
     * Test api_status URL is correct.
     *
     * API expects: status/ (no v1/utilities/ prefix)
     */
    public function testApiStatus_basicRequest_correctPathFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'service' => ['/v1/stocks/quotes/'],
                'status' => ['online'],
                'online' => [true],
                'uptimePct30d' => [99.99],
                'uptimePct90d' => [99.98],
                'updated' => [time()]
            ]))
        ]);

        $this->client->utilities->api_status();

        $this->assertCount(1, $this->history);
        $this->assertEquals('status/', $this->getLastRequestPath());
    }

    // ========================================================================
    // UTILITIES HEADERS ENDPOINT
    // API: GET /headers/
    // Note: The Utilities class doesn't have a BASE_URL prefix like other endpoints
    // ========================================================================

    /**
     * Test headers URL is correct.
     *
     * API expects: headers/ (no v1/utilities/ prefix)
     */
    public function testHeaders_basicRequest_correctPathFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'Host' => 'api.marketdata.app',
                'Authorization' => 'Bearer ****redacted****',
                'User-Agent' => 'marketdata-sdk-php/1.0.0'
            ]))
        ]);

        $this->client->utilities->headers();

        $this->assertCount(1, $this->history);
        $this->assertEquals('headers/', $this->getLastRequestPath());
    }

    // ========================================================================
    // UTILITIES USER ENDPOINT
    // API: GET /user/
    // ========================================================================

    /**
     * Test user URL is correct.
     *
     * API expects: /user/ (note: different base path)
     */
    public function testUser_basicRequest_correctPathFormat(): void
    {
        $resetTimestamp = time() + 3600;
        $this->setMockResponsesWithHistory([
            new Response(200, [
                'x-api-ratelimit-limit' => '100',
                'x-api-ratelimit-remaining' => '99',
                'x-api-ratelimit-reset' => (string)$resetTimestamp,
                'x-api-ratelimit-consumed' => '1',
            ], json_encode([]))
        ]);

        $this->client->utilities->user();

        $this->assertCount(1, $this->history);
        $this->assertEquals('user/', $this->getLastRequestPath());
    }
}
