<?php

namespace MarketDataApp\Tests\Unit\Options;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Enums\Expiration;
use MarketDataApp\Enums\Range;
use MarketDataApp\Enums\Side;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for URL construction in Options endpoints.
 *
 * These tests verify that the SDK constructs URLs correctly according to the API documentation.
 * Each test captures the actual HTTP request and verifies the URL path and query parameters.
 *
 * API Documentation: https://www.marketdata.app/docs/api/options
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
    // EXPIRATIONS ENDPOINT
    // API: GET /v1/options/expirations/{underlyingSymbol}/
    // ========================================================================

    /**
     * Test expirations URL includes symbol in path.
     *
     * API expects: /v1/options/expirations/{underlyingSymbol}/
     */
    public function testExpirations_basicRequest_correctPathFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'expirations' => ['2024-01-19', '2024-02-16', '2024-03-15'],
                'updated' => 1234567890
            ]))
        ]);

        $this->client->options->expirations('AAPL');

        $this->assertCount(1, $this->history);
        $this->assertEquals('v1/options/expirations/AAPL/', $this->getLastRequestPath());
    }

    /**
     * Test expirations URL with strike parameter.
     */
    public function testExpirations_withStrike_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'expirations' => ['2024-01-19', '2024-02-16'],
                'updated' => 1234567890
            ]))
        ]);

        $this->client->options->expirations('AAPL', strike: 200);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('strike', $query);
        $this->assertEquals('200', $query['strike']);
    }

    /**
     * Test expirations URL with decimal strike parameter.
     *
     * This verifies the fix for Bug 007: strike should accept decimal values
     * (e.g., 12.5) for non-standard options strikes, not just integers.
     */
    public function testExpirations_withDecimalStrike_preservesDecimal(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'expirations' => ['2024-01-19'],
                'updated' => 1234567890
            ]))
        ]);

        $this->client->options->expirations('AAPL', strike: 12.5);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('strike', $query);
        $this->assertEquals('12.5', $query['strike']);
    }

    /**
     * Test expirations URL with date parameter.
     */
    public function testExpirations_withDate_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'expirations' => ['2024-01-19', '2024-02-16'],
                'updated' => 1234567890
            ]))
        ]);

        $this->client->options->expirations('AAPL', date: '2024-01-15');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('date', $query);
        $this->assertEquals('2024-01-15', $query['date']);
    }

    /**
     * Test expirations URL with both strike and date parameters.
     */
    public function testExpirations_withStrikeAndDate_addsParameters(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'expirations' => ['2024-01-19'],
                'updated' => 1234567890
            ]))
        ]);

        $this->client->options->expirations('AAPL', strike: 200, date: '2024-01-15');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('strike', $query);
        $this->assertArrayHasKey('date', $query);
        $this->assertEquals('200', $query['strike']);
        $this->assertEquals('2024-01-15', $query['date']);
    }

    // ========================================================================
    // LOOKUP ENDPOINT
    // API: GET /v1/options/lookup/{userInput}/
    // ========================================================================

    /**
     * Test lookup URL includes input in path.
     *
     * API expects: /v1/options/lookup/{userInput}/
     */
    public function testLookup_basicRequest_correctPathFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => 'AAPL230728C00200000'
            ]))
        ]);

        $this->client->options->lookup('AAPL 7/28/23 $200 Call');

        $this->assertCount(1, $this->history);
        // URL encoding expected for spaces and special chars
        $path = $this->getLastRequestPath();
        $this->assertStringStartsWith('v1/options/lookup/', $path);
        $this->assertStringContainsString('AAPL', $path);
    }

    /**
     * Test lookup URL properly encodes slashes in user input.
     *
     * This verifies the fix for Bug 001: slashes in dates (e.g., 7/28/23)
     * must be encoded as %2F to remain a single path segment.
     */
    public function testLookup_withSlashesInInput_properlyEncodes(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => 'AAPL230728C00200000'
            ]))
        ]);

        $input = 'AAPL 7/28/23 $200 Call';
        $this->client->options->lookup($input);

        $path = $this->getLastRequestPath();
        $expectedPath = 'v1/options/lookup/' . rawurlencode($input) . '/';

        // Slashes must be encoded as %2F, not left as path separators
        $this->assertEquals($expectedPath, $path);
        $this->assertStringContainsString('%2F', $path);
        $this->assertStringNotContainsString('7/28/23', $path);
    }

    // ========================================================================
    // STRIKES ENDPOINT
    // API: GET /v1/options/strikes/{underlyingSymbol}/
    // ========================================================================

    /**
     * Test strikes URL includes symbol in path.
     *
     * API expects: /v1/options/strikes/{underlyingSymbol}/
     */
    public function testStrikes_basicRequest_correctPathFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'updated' => 1234567890,
                '2024-01-19' => [150.0, 155.0, 160.0, 165.0, 170.0]
            ]))
        ]);

        $this->client->options->strikes('AAPL');

        $this->assertCount(1, $this->history);
        $this->assertEquals('v1/options/strikes/AAPL/', $this->getLastRequestPath());
    }

    /**
     * Test strikes URL with expiration parameter.
     */
    public function testStrikes_withExpiration_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'updated' => 1234567890,
                '2024-01-19' => [150.0, 155.0, 160.0]
            ]))
        ]);

        $this->client->options->strikes('AAPL', expiration: '2024-01-19');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('expiration', $query);
        $this->assertEquals('2024-01-19', $query['expiration']);
    }

    /**
     * Test strikes URL with date parameter.
     */
    public function testStrikes_withDate_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'updated' => 1234567890,
                '2024-01-19' => [150.0, 155.0, 160.0]
            ]))
        ]);

        $this->client->options->strikes('AAPL', date: '2024-01-10');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('date', $query);
        $this->assertEquals('2024-01-10', $query['date']);
    }

    // ========================================================================
    // OPTION CHAIN ENDPOINT
    // API: GET /v1/options/chain/{underlyingSymbol}/
    // ========================================================================

    /**
     * Test option chain URL includes symbol in path.
     *
     * API expects: /v1/options/chain/{underlyingSymbol}/
     */
    public function testOptionChain_basicRequest_correctPathFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL');

        $this->assertCount(1, $this->history);
        $this->assertEquals('v1/options/chain/AAPL/', $this->getLastRequestPath());
    }

    /**
     * Test option chain URL omits expiration parameter when not specified.
     *
     * This verifies the fix for Bug 002: when expiration is not specified,
     * the parameter should be omitted so the API applies its default
     * (next monthly expiration) instead of returning the full chain.
     */
    public function testOptionChain_withoutExpiration_omitsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayNotHasKey('expiration', $query);
    }

    /**
     * Test option chain URL with date parameter.
     */
    public function testOptionChain_withDate_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', date: '2024-01-15');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('date', $query);
        $this->assertEquals('2024-01-15', $query['date']);
    }

    /**
     * Test option chain URL with expiration parameter (specific date).
     */
    public function testOptionChain_withExpirationDate_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', expiration: '2024-01-19');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('expiration', $query);
        $this->assertEquals('2024-01-19', $query['expiration']);
    }

    /**
     * Test option chain URL with expiration enum (all).
     */
    public function testOptionChain_withExpirationAll_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', expiration: Expiration::ALL);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('expiration', $query);
        $this->assertEquals('all', $query['expiration']);
    }

    /**
     * Test option chain URL with side parameter (call).
     */
    public function testOptionChain_withSideCall_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', side: Side::CALL);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('side', $query);
        $this->assertEquals('call', $query['side']);
    }

    /**
     * Test option chain URL with side parameter (put).
     */
    public function testOptionChain_withSidePut_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119P00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['put'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [false],
                'intrinsicValue' => [0.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [-0.35],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [-0.02]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', side: Side::PUT);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('side', $query);
        $this->assertEquals('put', $query['side']);
    }

    /**
     * Test option chain URL with range parameter (itm).
     */
    public function testOptionChain_withRangeItm_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', range: Range::IN_THE_MONEY);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('range', $query);
        $this->assertEquals('itm', $query['range']);
    }

    /**
     * Test option chain URL with strike parameter.
     */
    public function testOptionChain_withStrike_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00200000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [200.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [false],
                'intrinsicValue' => [0.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.30],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', strike: '200');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('strike', $query);
        $this->assertEquals('200', $query['strike']);
    }

    /**
     * Test option chain URL with strikeLimit parameter.
     */
    public function testOptionChain_withStrikeLimit_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', strike_limit: 10);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('strikeLimit', $query);
        $this->assertEquals('10', $query['strikeLimit']);
    }

    /**
     * Test option chain URL with dte parameter.
     */
    public function testOptionChain_withDte_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', dte: 30);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('dte', $query);
        $this->assertEquals('30', $query['dte']);
    }

    /**
     * Test option chain URL with weekly=false sends parameter.
     */
    public function testOptionChain_withWeeklyFalse_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', weekly: false);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('weekly', $query);
        $this->assertEquals('false', $query['weekly']);
    }

    /**
     * Test option chain URL with monthly=false sends parameter.
     */
    public function testOptionChain_withMonthlyFalse_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', monthly: false);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('monthly', $query);
        $this->assertEquals('false', $query['monthly']);
    }

    /**
     * Test option chain URL omits nonstandard parameter when not specified.
     *
     * This verifies the fix for Bug 006: when non_standard is not specified,
     * the parameter should be omitted so the API applies its default (false)
     * instead of sending nonstandard=true.
     */
    public function testOptionChain_withoutNonStandard_omitsParameter(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayNotHasKey('nonstandard', $query);
    }

    /**
     * Test option chain URL with nonstandard=false sends parameter.
     *
     * When explicitly set to false, the parameter should be sent to override
     * any API default behavior.
     */
    public function testOptionChain_withNonStandardFalse_addsParameter(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', non_standard: false);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('nonstandard', $query);
        $this->assertEquals('false', $query['nonstandard']);
    }

    /**
     * Test option chain URL with nonstandard=true sends parameter.
     */
    public function testOptionChain_withNonStandardTrue_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', non_standard: true);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('nonstandard', $query);
        $this->assertEquals('true', $query['nonstandard']);
    }

    /**
     * Test option chain URL with minBid parameter.
     */
    public function testOptionChain_withMinBid_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', min_bid: 1.0);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('minBid', $query);
        $this->assertEquals('1', $query['minBid']);
    }

    /**
     * Test option chain URL with delta as float value.
     */
    public function testOptionChain_withDeltaFloat_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', delta: 0.50);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('delta', $query);
        $this->assertEquals('0.5', $query['delta']);
    }

    /**
     * Test option chain URL with delta as string expression (comparison).
     *
     * This verifies the fix for Bug 003: delta should accept string expressions
     * like ">.50" for range comparisons, not just float values.
     */
    public function testOptionChain_withDeltaStringComparison_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', delta: '>.50');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('delta', $query);
        $this->assertEquals('>.50', $query['delta']);
    }

    /**
     * Test option chain URL with delta as string expression (range).
     *
     * This verifies the fix for Bug 003: delta should accept string expressions
     * like ".30-.60" for range filtering.
     */
    public function testOptionChain_withDeltaStringRange_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.45],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', delta: '.30-.60');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('delta', $query);
        $this->assertEquals('.30-.60', $query['delta']);
    }

    /**
     * Test option chain URL with delta as string expression (comma-separated list).
     *
     * This verifies the fix for Bug 003: delta should accept string expressions
     * like ".60,.30" for multiple specific deltas.
     */
    public function testOptionChain_withDeltaStringList_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000', 'AAPL240119C00160000'],
                'underlying' => ['AAPL', 'AAPL'],
                'expiration' => [1705622400, 1705622400],
                'side' => ['call', 'call'],
                'strike' => [150.0, 160.0],
                'firstTraded' => [1234567890, 1234567890],
                'dte' => [30, 30],
                'updated' => [1234567890, 1234567890],
                'bid' => [5.0, 3.0],
                'bidSize' => [10, 10],
                'mid' => [5.5, 3.5],
                'ask' => [6.0, 4.0],
                'askSize' => [10, 10],
                'last' => [5.5, 3.5],
                'openInterest' => [1000, 800],
                'volume' => [500, 300],
                'inTheMoney' => [true, false],
                'intrinsicValue' => [10.0, 0.0],
                'extrinsicValue' => [5.0, 3.5],
                'underlyingPrice' => [160.0, 160.0],
                'iv' => [0.25, 0.28],
                'delta' => [0.60, 0.30],
                'gamma' => [0.02, 0.03],
                'theta' => [-0.05, -0.04],
                'vega' => [0.15, 0.12],
                'rho' => [0.03, 0.02]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', delta: '.60,.30');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('delta', $query);
        $this->assertEquals('.60,.30', $query['delta']);
    }

    /**
     * Test option chain URL with minVolume parameter.
     */
    public function testOptionChain_withMinVolume_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('AAPL', min_volume: 100);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('minVolume', $query);
        $this->assertEquals('100', $query['minVolume']);
    }

    // ========================================================================
    // QUOTES ENDPOINT
    // API: GET /v1/options/quotes/{optionSymbol}/
    // ========================================================================

    /**
     * Test quotes URL for single option symbol uses path format.
     *
     * API expects: /v1/options/quotes/{optionSymbol}/
     */
    public function testQuotes_singleSymbol_usesPathFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->quotes('AAPL240119C00150000');

        $this->assertCount(1, $this->history);
        $this->assertEquals('v1/options/quotes/AAPL240119C00150000/', $this->getLastRequestPath());
    }

    /**
     * Test quotes URL with date parameter.
     */
    public function testQuotes_withDate_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->quotes('AAPL240119C00150000', date: '2024-01-15');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('date', $query);
        $this->assertEquals('2024-01-15', $query['date']);
    }

    /**
     * Test quotes URL with from and to parameters.
     */
    public function testQuotes_withFromAndTo_addsParameters(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->quotes('AAPL240119C00150000', from: '2024-01-01', to: '2024-01-15');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('from', $query);
        $this->assertArrayHasKey('to', $query);
        $this->assertEquals('2024-01-01', $query['from']);
        $this->assertEquals('2024-01-15', $query['to']);
    }

    /**
     * Test quotes URL for multiple option symbols makes concurrent requests.
     */
    public function testQuotes_multipleSymbols_makesConcurrentRequests(): void
    {
        $mockResponse = json_encode([
            's' => 'ok',
            'optionSymbol' => ['AAPL240119C00150000'],
            'underlying' => ['AAPL'],
            'expiration' => [1705622400],
            'side' => ['call'],
            'strike' => [150.0],
            'firstTraded' => [1234567890],
            'dte' => [30],
            'updated' => [1234567890],
            'bid' => [5.0],
            'bidSize' => [10],
            'mid' => [5.5],
            'ask' => [6.0],
            'askSize' => [10],
            'last' => [5.5],
            'openInterest' => [1000],
            'volume' => [500],
            'inTheMoney' => [true],
            'intrinsicValue' => [10.0],
            'extrinsicValue' => [5.0],
            'underlyingPrice' => [160.0],
            'iv' => [0.25],
            'delta' => [0.65],
            'gamma' => [0.02],
            'theta' => [-0.05],
            'vega' => [0.15],
            'rho' => [0.03]
        ]);

        $this->setMockResponsesWithHistory([
            new Response(200, [], $mockResponse),
            new Response(200, [], $mockResponse),
        ]);

        $this->client->options->quotes(['AAPL240119C00150000', 'AAPL240119P00150000']);

        // Should make 2 separate requests (concurrent)
        $this->assertCount(2, $this->history);

        // Verify both paths are for quotes endpoint
        $paths = array_map(fn($h) => $h['request']->getUri()->getPath(), $this->history);
        $this->assertContains('v1/options/quotes/AAPL240119C00150000/', $paths);
        $this->assertContains('v1/options/quotes/AAPL240119P00150000/', $paths);
    }

    // ========================================================================
    // SYMBOL TRIMMING
    // Bug 017: Single-symbol endpoints should trim whitespace from symbols
    // ========================================================================

    /**
     * Test expirations() trims whitespace from symbol.
     *
     * Bug 017: Symbols with leading/trailing whitespace should be trimmed
     * before being used in the URL path to avoid encoded spaces (%20).
     */
    public function testExpirations_symbolWithWhitespace_isTrimmed(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'expirations' => ['2024-01-19', '2024-02-16'],
                'updated' => 1234567890
            ]))
        ]);

        $this->client->options->expirations('AAPL ');

        $path = $this->getLastRequestPath();
        $this->assertEquals('v1/options/expirations/AAPL/', $path);
        $this->assertStringNotContainsString('%20', $path, 'Path should not contain encoded space');
    }

    /**
     * Test strikes() trims whitespace from symbol.
     */
    public function testStrikes_symbolWithWhitespace_isTrimmed(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'updated' => 1234567890,
                '2024-01-19' => [150.0, 155.0, 160.0]
            ]))
        ]);

        $this->client->options->strikes(' AAPL ');

        $path = $this->getLastRequestPath();
        $this->assertEquals('v1/options/strikes/AAPL/', $path);
        $this->assertStringNotContainsString('%20', $path, 'Path should not contain encoded space');
    }

    /**
     * Test option_chain() trims whitespace from symbol.
     */
    public function testOptionChain_symbolWithWhitespace_isTrimmed(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->option_chain('  AAPL  ');

        $path = $this->getLastRequestPath();
        $this->assertEquals('v1/options/chain/AAPL/', $path);
        $this->assertStringNotContainsString('%20', $path, 'Path should not contain encoded space');
    }

    /**
     * Test quotes() with single symbol trims whitespace.
     */
    public function testQuotes_singleSymbolWithWhitespace_isTrimmed(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => ['AAPL240119C00150000'],
                'underlying' => ['AAPL'],
                'expiration' => [1705622400],
                'side' => ['call'],
                'strike' => [150.0],
                'firstTraded' => [1234567890],
                'dte' => [30],
                'updated' => [1234567890],
                'bid' => [5.0],
                'bidSize' => [10],
                'mid' => [5.5],
                'ask' => [6.0],
                'askSize' => [10],
                'last' => [5.5],
                'openInterest' => [1000],
                'volume' => [500],
                'inTheMoney' => [true],
                'intrinsicValue' => [10.0],
                'extrinsicValue' => [5.0],
                'underlyingPrice' => [160.0],
                'iv' => [0.25],
                'delta' => [0.65],
                'gamma' => [0.02],
                'theta' => [-0.05],
                'vega' => [0.15],
                'rho' => [0.03]
            ]))
        ]);

        $this->client->options->quotes('AAPL240119C00150000 ');

        $path = $this->getLastRequestPath();
        $this->assertEquals('v1/options/quotes/AAPL240119C00150000/', $path);
        $this->assertStringNotContainsString('%20', $path, 'Path should not contain encoded space');
    }
}
