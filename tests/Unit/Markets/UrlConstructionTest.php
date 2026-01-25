<?php

namespace MarketDataApp\Tests\Unit\Markets;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for URL construction in Markets endpoints.
 *
 * These tests verify that the SDK constructs URLs correctly according to the API documentation.
 *
 * API Documentation: https://www.marketdata.app/docs/api/markets
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
    }

    protected function tearDown(): void
    {
        $this->restoreMarketDataTokenState();
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

    /**
     * Get the last request's query string.
     */
    private function getLastRequestQuery(): string
    {
        return $this->history[0]['request']->getUri()->getQuery();
    }

    /**
     * Parse query string into associative array.
     */
    private function parseQuery(string $query): array
    {
        parse_str($query, $result);
        return $result;
    }

    // ========================================================================
    // MARKETS STATUS ENDPOINT
    // API: GET /v1/markets/status/
    // ========================================================================

    /**
     * Test status URL is correct.
     *
     * API expects: /v1/markets/status/
     */
    public function testStatus_basicRequest_correctPathFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'date' => ['2024-01-15'],
                'status' => ['open']
            ]))
        ]);

        $this->client->markets->status();

        $this->assertCount(1, $this->history);
        $this->assertEquals('v1/markets/status/', $this->getLastRequestPath());
    }

    /**
     * Test status URL with country parameter (default US).
     */
    public function testStatus_defaultCountry_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'date' => ['2024-01-15'],
                'status' => ['open']
            ]))
        ]);

        $this->client->markets->status();

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('country', $query);
        $this->assertEquals('US', $query['country']);
    }

    /**
     * Test status URL with custom country parameter.
     */
    public function testStatus_withCountry_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'date' => ['2024-01-15'],
                'status' => ['open']
            ]))
        ]);

        $this->client->markets->status('CA');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('country', $query);
        $this->assertEquals('CA', $query['country']);
    }

    /**
     * Test status URL with date parameter.
     */
    public function testStatus_withDate_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'date' => ['2024-01-15'],
                'status' => ['open']
            ]))
        ]);

        $this->client->markets->status(date: '2024-01-15');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('date', $query);
        $this->assertEquals('2024-01-15', $query['date']);
    }

    /**
     * Test status URL with from and to parameters.
     */
    public function testStatus_withFromAndTo_addsParameters(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'date' => ['2024-01-15', '2024-01-16', '2024-01-17'],
                'status' => ['open', 'open', 'open']
            ]))
        ]);

        $this->client->markets->status(from: '2024-01-15', to: '2024-01-17');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('from', $query);
        $this->assertArrayHasKey('to', $query);
        $this->assertEquals('2024-01-15', $query['from']);
        $this->assertEquals('2024-01-17', $query['to']);
    }

    /**
     * Test status URL with countback parameter.
     */
    public function testStatus_withCountback_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'date' => ['2024-01-15', '2024-01-14', '2024-01-13'],
                'status' => ['open', 'closed', 'closed']
            ]))
        ]);

        $this->client->markets->status(to: '2024-01-15', countback: 3);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('countback', $query);
        $this->assertEquals('3', $query['countback']);
    }
}
