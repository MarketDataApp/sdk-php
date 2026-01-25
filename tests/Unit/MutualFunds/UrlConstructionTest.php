<?php

namespace MarketDataApp\Tests\Unit\MutualFunds;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for URL construction in MutualFunds endpoints.
 *
 * These tests verify that the SDK constructs URLs correctly according to the API documentation.
 *
 * API Documentation: https://www.marketdata.app/docs/api/funds
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
    // MUTUAL FUNDS CANDLES ENDPOINT
    // API: GET /v1/funds/candles/{resolution}/{symbol}/
    // ========================================================================

    /**
     * Test candles URL includes resolution and symbol in path.
     *
     * API expects: /v1/funds/candles/{resolution}/{symbol}/
     */
    public function testCandles_basicRequest_correctPathFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'o' => [100.0],
                'h' => [105.0],
                'l' => [99.0],
                'c' => [104.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->mutual_funds->candles('VFIAX', '2024-01-01');

        $this->assertCount(1, $this->history);
        $this->assertEquals('v1/funds/candles/D/VFIAX/', $this->getLastRequestPath());
    }

    /**
     * Test candles URL with different resolution.
     */
    public function testCandles_withResolution_correctPath(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'o' => [100.0],
                'h' => [105.0],
                'l' => [99.0],
                'c' => [104.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->mutual_funds->candles('VFIAX', '2024-01-01', resolution: 'W');

        $this->assertEquals('v1/funds/candles/W/VFIAX/', $this->getLastRequestPath());
    }

    /**
     * Test candles URL with from parameter.
     */
    public function testCandles_withFrom_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'o' => [100.0],
                'h' => [105.0],
                'l' => [99.0],
                'c' => [104.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->mutual_funds->candles('VFIAX', '2024-01-01');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('from', $query);
        $this->assertEquals('2024-01-01', $query['from']);
    }

    /**
     * Test candles URL with from and to parameters.
     */
    public function testCandles_withFromAndTo_addsParameters(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'o' => [100.0, 101.0],
                'h' => [105.0, 106.0],
                'l' => [99.0, 100.0],
                'c' => [104.0, 105.0],
                'v' => [1000000, 1100000],
                't' => [1234567890, 1234654290]
            ]))
        ]);

        $this->client->mutual_funds->candles('VFIAX', '2024-01-01', '2024-01-31');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('from', $query);
        $this->assertArrayHasKey('to', $query);
        $this->assertEquals('2024-01-01', $query['from']);
        $this->assertEquals('2024-01-31', $query['to']);
    }

    /**
     * Test candles URL with countback parameter.
     */
    public function testCandles_withCountback_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'o' => [100.0],
                'h' => [105.0],
                'l' => [99.0],
                'c' => [104.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->mutual_funds->candles('VFIAX', '2024-01-01', countback: 10);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('countback', $query);
        $this->assertEquals('10', $query['countback']);
    }

    /**
     * Test candles URL with various resolutions.
     */
    public function testCandles_variousResolutions_correctPath(): void
    {
        $resolutions = ['D', 'W', 'M', 'Y'];

        foreach ($resolutions as $resolution) {
            $this->history = [];
            $this->setMockResponsesWithHistory([
                new Response(200, [], json_encode([
                    's' => 'ok',
                    'o' => [100.0],
                    'h' => [105.0],
                    'l' => [99.0],
                    'c' => [104.0],
                    'v' => [1000000],
                    't' => [1234567890]
                ]))
            ]);

            $this->client->mutual_funds->candles('VFIAX', '2024-01-01', resolution: $resolution);

            $this->assertEquals(
                "v1/funds/candles/{$resolution}/VFIAX/",
                $this->getLastRequestPath(),
                "Failed for resolution: {$resolution}"
            );
        }
    }
}
