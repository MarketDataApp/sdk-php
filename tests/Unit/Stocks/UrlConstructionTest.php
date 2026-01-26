<?php

namespace MarketDataApp\Tests\Unit\Stocks;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\Format;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for URL construction in Stocks endpoints.
 *
 * These tests verify that the SDK constructs URLs correctly according to the API documentation.
 * Each test captures the actual HTTP request and verifies the URL path and query parameters.
 *
 * API Documentation: https://www.marketdata.app/docs/api/stocks
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
    // PRICES ENDPOINT
    // API: GET /v1/stocks/prices/{symbol}/ (single)
    // API: GET /v1/stocks/prices/?symbols={symbols} (multiple)
    // ========================================================================

    /**
     * Test prices URL for single symbol uses path format.
     *
     * API expects: /v1/stocks/prices/{symbol}/
     */
    public function testPrices_singleSymbol_usesPathFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'mid' => [150.0],
                'change' => [1.0],
                'changepct' => [0.01],
                'updated' => [1234567890]
            ]))
        ]);

        $this->client->stocks->prices('AAPL');

        $this->assertCount(1, $this->history);
        $this->assertEquals('v1/stocks/prices/AAPL/', $this->getLastRequestPath());
    }

    /**
     * Test prices URL for multiple symbols uses query format.
     *
     * API expects: /v1/stocks/prices/?symbols={symbol1},{symbol2},...
     */
    public function testPrices_multipleSymbols_usesQueryFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL', 'META', 'MSFT'],
                'mid' => [150.0, 300.0, 400.0],
                'change' => [1.0, 2.0, 3.0],
                'changepct' => [0.01, 0.01, 0.01],
                'updated' => [1234567890, 1234567890, 1234567890]
            ]))
        ]);

        $this->client->stocks->prices(['AAPL', 'META', 'MSFT']);

        $this->assertCount(1, $this->history);
        $this->assertEquals('v1/stocks/prices/', $this->getLastRequestPath());

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('symbols', $query);
        $this->assertEquals('AAPL,META,MSFT', $query['symbols']);
    }

    /**
     * Test prices URL with extended=true does not add query parameter (API default).
     *
     * API default: extended=true, so SDK should not send it when true.
     */
    public function testPrices_extendedTrue_doesNotAddParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'mid' => [150.0],
                'change' => [1.0],
                'changepct' => [0.01],
                'updated' => [1234567890]
            ]))
        ]);

        $this->client->stocks->prices('AAPL', extended: true);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayNotHasKey('extended', $query);
    }

    /**
     * Test prices URL with extended=false adds query parameter.
     *
     * API expects: ?extended=false
     */
    public function testPrices_extendedFalse_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'mid' => [150.0],
                'change' => [1.0],
                'changepct' => [0.01],
                'updated' => [1234567890]
            ]))
        ]);

        $this->client->stocks->prices('AAPL', extended: false);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('extended', $query);
        $this->assertEquals('false', $query['extended']);
    }

    /**
     * Test prices URL with multiple symbols and extended=false.
     */
    public function testPrices_multipleSymbolsExtendedFalse_correctUrl(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL', 'META'],
                'mid' => [150.0, 300.0],
                'change' => [1.0, 2.0],
                'changepct' => [0.01, 0.01],
                'updated' => [1234567890, 1234567890]
            ]))
        ]);

        $this->client->stocks->prices(['AAPL', 'META'], extended: false);

        $this->assertEquals('v1/stocks/prices/', $this->getLastRequestPath());

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertEquals('AAPL,META', $query['symbols']);
        $this->assertEquals('false', $query['extended']);
    }

    // ========================================================================
    // QUOTE ENDPOINT (single symbol)
    // API: GET /v1/stocks/quotes/{symbol}/
    // ========================================================================

    /**
     * Test quote URL uses path format with symbol.
     *
     * API expects: /v1/stocks/quotes/{symbol}/
     */
    public function testQuote_singleSymbol_usesPathFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'ask' => [150.1],
                'askSize' => [100],
                'bid' => [150.0],
                'bidSize' => [200],
                'mid' => [150.05],
                'last' => [150.0],
                'change' => [1.0],
                'changepct' => [0.01],
                'volume' => [1000000],
                'updated' => [1234567890]
            ]))
        ]);

        $this->client->stocks->quote('AAPL');

        $this->assertCount(1, $this->history);
        $this->assertEquals('v1/stocks/quotes/AAPL/', $this->getLastRequestPath());
    }

    /**
     * Test quote URL without 52week parameter does not add it.
     */
    public function testQuote_without52week_noParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'ask' => [150.1],
                'askSize' => [100],
                'bid' => [150.0],
                'bidSize' => [200],
                'mid' => [150.05],
                'last' => [150.0],
                'change' => [1.0],
                'changepct' => [0.01],
                'volume' => [1000000],
                'updated' => [1234567890]
            ]))
        ]);

        $this->client->stocks->quote('AAPL');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayNotHasKey('52week', $query);
    }

    /**
     * Test quote URL with 52week=true adds parameter.
     *
     * API expects: ?52week=true
     */
    public function testQuote_with52week_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'ask' => [150.1],
                'askSize' => [100],
                'bid' => [150.0],
                'bidSize' => [200],
                'mid' => [150.05],
                'last' => [150.0],
                'change' => [1.0],
                'changepct' => [0.01],
                'volume' => [1000000],
                'updated' => [1234567890],
                '52weekHigh' => [180.0],
                '52weekLow' => [120.0]
            ]))
        ]);

        $this->client->stocks->quote('AAPL', fifty_two_week: true);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('52week', $query);
        $this->assertEquals('true', $query['52week']);
    }

    /**
     * Test quote URL with extended=true does not add query parameter (API default).
     *
     * Bug 020: API default is extended=true, so SDK should not send it when true.
     */
    public function testQuote_extendedTrue_doesNotAddParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'ask' => [150.1],
                'askSize' => [100],
                'bid' => [150.0],
                'bidSize' => [200],
                'mid' => [150.05],
                'last' => [150.0],
                'change' => [1.0],
                'changepct' => [0.01],
                'volume' => [1000000],
                'updated' => [1234567890]
            ]))
        ]);

        $this->client->stocks->quote('AAPL', extended: true);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayNotHasKey('extended', $query);
    }

    /**
     * Test quote URL with extended=false adds query parameter.
     *
     * Bug 020: API expects ?extended=false to disable extended hours data.
     */
    public function testQuote_extendedFalse_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'ask' => [150.1],
                'askSize' => [100],
                'bid' => [150.0],
                'bidSize' => [200],
                'mid' => [150.05],
                'last' => [150.0],
                'change' => [1.0],
                'changepct' => [0.01],
                'volume' => [1000000],
                'updated' => [1234567890]
            ]))
        ]);

        $this->client->stocks->quote('AAPL', extended: false);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('extended', $query);
        $this->assertEquals('false', $query['extended']);
    }

    // ========================================================================
    // QUOTES ENDPOINT (multiple symbols)
    // API: GET /v1/stocks/quotes/?symbols={symbol1},{symbol2},...
    // ========================================================================

    /**
     * Test quotes URL uses query format with symbols parameter.
     *
     * API expects: /v1/stocks/quotes/?symbols={symbol1},{symbol2},...
     */
    public function testQuotes_multipleSymbols_usesQueryFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL', 'META', 'MSFT'],
                'ask' => [150.1, 300.1, 400.1],
                'askSize' => [100, 100, 100],
                'bid' => [150.0, 300.0, 400.0],
                'bidSize' => [200, 200, 200],
                'mid' => [150.05, 300.05, 400.05],
                'last' => [150.0, 300.0, 400.0],
                'change' => [1.0, 2.0, 3.0],
                'changepct' => [0.01, 0.01, 0.01],
                'volume' => [1000000, 2000000, 3000000],
                'updated' => [1234567890, 1234567890, 1234567890]
            ]))
        ]);

        $this->client->stocks->quotes(['AAPL', 'META', 'MSFT']);

        $this->assertCount(1, $this->history);
        $this->assertEquals('v1/stocks/quotes/', $this->getLastRequestPath());

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('symbols', $query);
        $this->assertEquals('AAPL,META,MSFT', $query['symbols']);
    }

    /**
     * Test quotes URL with 52week=true adds parameter.
     */
    public function testQuotes_with52week_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL', 'META'],
                'ask' => [150.1, 300.1],
                'askSize' => [100, 100],
                'bid' => [150.0, 300.0],
                'bidSize' => [200, 200],
                'mid' => [150.05, 300.05],
                'last' => [150.0, 300.0],
                'change' => [1.0, 2.0],
                'changepct' => [0.01, 0.01],
                'volume' => [1000000, 2000000],
                'updated' => [1234567890, 1234567890]
            ]))
        ]);

        $this->client->stocks->quotes(['AAPL', 'META'], fifty_two_week: true);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('symbols', $query);
        $this->assertArrayHasKey('52week', $query);
        $this->assertEquals('true', $query['52week']);
    }

    /**
     * Test quotes URL with extended=true does not add query parameter (API default).
     *
     * Bug 020: API default is extended=true, so SDK should not send it when true.
     */
    public function testQuotes_extendedTrue_doesNotAddParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL', 'META'],
                'ask' => [150.1, 300.1],
                'askSize' => [100, 100],
                'bid' => [150.0, 300.0],
                'bidSize' => [200, 200],
                'mid' => [150.05, 300.05],
                'last' => [150.0, 300.0],
                'change' => [1.0, 2.0],
                'changepct' => [0.01, 0.01],
                'volume' => [1000000, 2000000],
                'updated' => [1234567890, 1234567890]
            ]))
        ]);

        $this->client->stocks->quotes(['AAPL', 'META'], extended: true);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayNotHasKey('extended', $query);
    }

    /**
     * Test quotes URL with extended=false adds query parameter.
     *
     * Bug 020: API expects ?extended=false to disable extended hours data.
     */
    public function testQuotes_extendedFalse_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL', 'META'],
                'ask' => [150.1, 300.1],
                'askSize' => [100, 100],
                'bid' => [150.0, 300.0],
                'bidSize' => [200, 200],
                'mid' => [150.05, 300.05],
                'last' => [150.0, 300.0],
                'change' => [1.0, 2.0],
                'changepct' => [0.01, 0.01],
                'volume' => [1000000, 2000000],
                'updated' => [1234567890, 1234567890]
            ]))
        ]);

        $this->client->stocks->quotes(['AAPL', 'META'], extended: false);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('extended', $query);
        $this->assertEquals('false', $query['extended']);
    }

    /**
     * Test quotes URL with both 52week and extended parameters.
     */
    public function testQuotes_with52weekAndExtended_addsBothParameters(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'ask' => [150.1],
                'askSize' => [100],
                'bid' => [150.0],
                'bidSize' => [200],
                'mid' => [150.05],
                'last' => [150.0],
                'change' => [1.0],
                'changepct' => [0.01],
                'volume' => [1000000],
                'updated' => [1234567890],
                '52weekHigh' => [180.0],
                '52weekLow' => [120.0]
            ]))
        ]);

        $this->client->stocks->quotes(['AAPL'], fifty_two_week: true, extended: false);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('52week', $query);
        $this->assertEquals('true', $query['52week']);
        $this->assertArrayHasKey('extended', $query);
        $this->assertEquals('false', $query['extended']);
    }

    // ========================================================================
    // CANDLES ENDPOINT
    // API: GET /v1/stocks/candles/{resolution}/{symbol}/
    // ========================================================================

    /**
     * Test candles URL includes resolution and symbol in path.
     *
     * API expects: /v1/stocks/candles/{resolution}/{symbol}/
     */
    public function testCandles_basicRequest_correctPathFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'o' => [150.0],
                'h' => [155.0],
                'l' => [149.0],
                'c' => [154.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->stocks->candles('AAPL', '2024-01-01', '2024-01-31', 'D');

        $this->assertCount(1, $this->history);
        $this->assertEquals('v1/stocks/candles/D/AAPL/', $this->getLastRequestPath());
    }

    /**
     * Test candles URL with from parameter.
     */
    public function testCandles_withFrom_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'o' => [150.0],
                'h' => [155.0],
                'l' => [149.0],
                'c' => [154.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->stocks->candles('AAPL', '2024-01-01', resolution: 'D');

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
                'o' => [150.0],
                'h' => [155.0],
                'l' => [149.0],
                'c' => [154.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->stocks->candles('AAPL', '2024-01-01', '2024-01-31', 'D');

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
                'o' => [150.0],
                'h' => [155.0],
                'l' => [149.0],
                'c' => [154.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->stocks->candles('AAPL', '2024-01-01', countback: 10, resolution: 'D');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('countback', $query);
        $this->assertEquals('10', $query['countback']);
    }

    /**
     * Test candles URL with extended=true adds parameter.
     *
     * API expects: ?extended=true
     */
    public function testCandles_withExtended_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'o' => [150.0],
                'h' => [155.0],
                'l' => [149.0],
                'c' => [154.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->stocks->candles('AAPL', '2024-01-01', '2024-01-31', '5', extended: true);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('extended', $query);
        $this->assertEquals('true', $query['extended']);
    }

    /**
     * Test candles URL with adjustsplits=true adds parameter.
     *
     * API expects: ?adjustsplits=true
     */
    public function testCandles_withAdjustSplitsTrue_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'o' => [150.0],
                'h' => [155.0],
                'l' => [149.0],
                'c' => [154.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->stocks->candles('AAPL', '2024-01-01', '2024-01-31', 'D', adjust_splits: true);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('adjustsplits', $query);
        $this->assertEquals('true', $query['adjustsplits']);
    }

    /**
     * Test candles URL with adjustsplits=false adds parameter.
     *
     * Bug 010: When adjust_splits is explicitly set to false, the SDK should send
     * adjustsplits=false to override the API default (which is true for daily candles).
     *
     * API expects: ?adjustsplits=false
     */
    public function testCandles_withAdjustSplitsFalse_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'o' => [150.0],
                'h' => [155.0],
                'l' => [149.0],
                'c' => [154.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->stocks->candles('AAPL', '2024-01-01', '2024-01-31', 'D', adjust_splits: false);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('adjustsplits', $query);
        $this->assertEquals('false', $query['adjustsplits']);
    }

    /**
     * Test candles URL without adjust_splits omits parameter (uses API default).
     */
    public function testCandles_withoutAdjustSplits_omitsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'o' => [150.0],
                'h' => [155.0],
                'l' => [149.0],
                'c' => [154.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->stocks->candles('AAPL', '2024-01-01', '2024-01-31', 'D');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayNotHasKey('adjustsplits', $query);
    }

    /**
     * Test candles URL with exchange parameter.
     */
    public function testCandles_withExchange_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'o' => [150.0],
                'h' => [155.0],
                'l' => [149.0],
                'c' => [154.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->stocks->candles('AAPL', '2024-01-01', '2024-01-31', 'D', exchange: 'NASDAQ');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('exchange', $query);
        $this->assertEquals('NASDAQ', $query['exchange']);
    }

    /**
     * Test candles URL with country parameter.
     */
    public function testCandles_withCountry_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'o' => [150.0],
                'h' => [155.0],
                'l' => [149.0],
                'c' => [154.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->stocks->candles('AAPL', '2024-01-01', '2024-01-31', 'D', country: 'US');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('country', $query);
        $this->assertEquals('US', $query['country']);
    }

    /**
     * Test candles URL with various resolution formats.
     */
    public function testCandles_variousResolutions_correctPath(): void
    {
        $resolutions = ['D', '1', '5', '15', 'H', '1H', 'W', 'M', 'Y'];

        foreach ($resolutions as $resolution) {
            $this->history = [];
            $this->setMockResponsesWithHistory([
                new Response(200, [], json_encode([
                    's' => 'ok',
                    'o' => [150.0],
                    'h' => [155.0],
                    'l' => [149.0],
                    'c' => [154.0],
                    'v' => [1000000],
                    't' => [1234567890]
                ]))
            ]);

            $this->client->stocks->candles('AAPL', '2024-01-01', '2024-01-31', $resolution);

            $this->assertEquals(
                "v1/stocks/candles/{$resolution}/AAPL/",
                $this->getLastRequestPath(),
                "Failed for resolution: {$resolution}"
            );
        }
    }

    // ========================================================================
    // BULK CANDLES ENDPOINT
    // API: GET /v1/stocks/bulkcandles/{resolution}/
    // ========================================================================

    /**
     * Test bulkCandles URL includes resolution in path.
     *
     * API expects: /v1/stocks/bulkcandles/{resolution}/
     */
    public function testBulkCandles_basicRequest_correctPathFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL', 'META'],
                'o' => [150.0, 300.0],
                'h' => [155.0, 310.0],
                'l' => [149.0, 295.0],
                'c' => [154.0, 305.0],
                'v' => [1000000, 2000000],
                't' => [1234567890, 1234567890]
            ]))
        ]);

        $this->client->stocks->bulkCandles(['AAPL', 'META'], 'D');

        $this->assertCount(1, $this->history);
        $this->assertEquals('v1/stocks/bulkcandles/D/', $this->getLastRequestPath());
    }

    /**
     * Test bulkCandles URL with symbols parameter.
     */
    public function testBulkCandles_withSymbols_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL', 'META'],
                'o' => [150.0, 300.0],
                'h' => [155.0, 310.0],
                'l' => [149.0, 295.0],
                'c' => [154.0, 305.0],
                'v' => [1000000, 2000000],
                't' => [1234567890, 1234567890]
            ]))
        ]);

        $this->client->stocks->bulkCandles(['AAPL', 'META'], 'D');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('symbols', $query);
        $this->assertEquals('AAPL,META', $query['symbols']);
    }

    /**
     * Test bulkCandles URL with snapshot=true adds parameter.
     */
    public function testBulkCandles_withSnapshot_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'o' => [150.0],
                'h' => [155.0],
                'l' => [149.0],
                'c' => [154.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->stocks->bulkCandles([], 'D', snapshot: true);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('snapshot', $query);
        $this->assertEquals('true', $query['snapshot']);
    }

    /**
     * Test bulkCandles URL with snapshot=true and no symbols omits symbols parameter.
     *
     * Bug 004: When snapshot=true and no symbols provided, symbols should be omitted entirely,
     * not sent as an empty string (symbols=).
     */
    public function testBulkCandles_withSnapshotNoSymbols_omitsSymbolsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'o' => [150.0],
                'h' => [155.0],
                'l' => [149.0],
                'c' => [154.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->stocks->bulkCandles([], 'D', snapshot: true);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayNotHasKey('symbols', $query, 'symbols parameter should be omitted when empty');
    }

    /**
     * Test bulkCandles URL with date parameter.
     */
    public function testBulkCandles_withDate_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL', 'META'],
                'o' => [150.0, 300.0],
                'h' => [155.0, 310.0],
                'l' => [149.0, 295.0],
                'c' => [154.0, 305.0],
                'v' => [1000000, 2000000],
                't' => [1234567890, 1234567890]
            ]))
        ]);

        $this->client->stocks->bulkCandles(['AAPL', 'META'], 'D', date: '2024-01-15');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('date', $query);
        $this->assertEquals('2024-01-15', $query['date']);
    }

    /**
     * Test bulkCandles URL with adjustsplits=true adds parameter.
     */
    public function testBulkCandles_withAdjustSplitsTrue_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL', 'META'],
                'o' => [150.0, 300.0],
                'h' => [155.0, 310.0],
                'l' => [149.0, 295.0],
                'c' => [154.0, 305.0],
                'v' => [1000000, 2000000],
                't' => [1234567890, 1234567890]
            ]))
        ]);

        $this->client->stocks->bulkCandles(['AAPL', 'META'], 'D', adjust_splits: true);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('adjustsplits', $query);
        $this->assertEquals('true', $query['adjustsplits']);
    }

    /**
     * Test bulkCandles URL with adjustsplits=false adds parameter.
     *
     * Bug 010: When adjust_splits is explicitly set to false, the SDK should send
     * adjustsplits=false to override the API default.
     */
    public function testBulkCandles_withAdjustSplitsFalse_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL', 'META'],
                'o' => [150.0, 300.0],
                'h' => [155.0, 310.0],
                'l' => [149.0, 295.0],
                'c' => [154.0, 305.0],
                'v' => [1000000, 2000000],
                't' => [1234567890, 1234567890]
            ]))
        ]);

        $this->client->stocks->bulkCandles(['AAPL', 'META'], 'D', adjust_splits: false);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('adjustsplits', $query);
        $this->assertEquals('false', $query['adjustsplits']);
    }

    /**
     * Test bulkCandles URL without adjust_splits omits parameter (uses API default).
     */
    public function testBulkCandles_withoutAdjustSplits_omitsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL', 'META'],
                'o' => [150.0, 300.0],
                'h' => [155.0, 310.0],
                'l' => [149.0, 295.0],
                'c' => [154.0, 305.0],
                'v' => [1000000, 2000000],
                't' => [1234567890, 1234567890]
            ]))
        ]);

        $this->client->stocks->bulkCandles(['AAPL', 'META'], 'D');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayNotHasKey('adjustsplits', $query);
    }

    // ========================================================================
    // EARNINGS ENDPOINT
    // API: GET /v1/stocks/earnings/{symbol}/
    // ========================================================================

    /**
     * Test earnings URL includes symbol in path.
     *
     * API expects: /v1/stocks/earnings/{symbol}/
     */
    public function testEarnings_basicRequest_correctPathFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'fiscalYear' => [2024],
                'fiscalQuarter' => [1],
                'date' => ['2024-01-25'],
                'reportDate' => ['2024-02-01'],
                'reportTime' => ['after close'],
                'currency' => ['USD'],
                'reportedEPS' => [1.50],
                'estimatedEPS' => [1.45],
                'surpriseEPS' => [0.05],
                'surpriseEPSpct' => [0.03],
                'updated' => [1234567890]
            ]))
        ]);

        $this->client->stocks->earnings('AAPL', from: '2024-01-01');

        $this->assertCount(1, $this->history);
        $this->assertEquals('v1/stocks/earnings/AAPL/', $this->getLastRequestPath());
    }

    /**
     * Test earnings URL with from parameter.
     */
    public function testEarnings_withFrom_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'fiscalYear' => [2024],
                'fiscalQuarter' => [1],
                'date' => ['2024-01-25'],
                'reportDate' => ['2024-02-01'],
                'reportTime' => ['after close'],
                'currency' => ['USD'],
                'reportedEPS' => [1.50],
                'estimatedEPS' => [1.45],
                'surpriseEPS' => [0.05],
                'surpriseEPSpct' => [0.03],
                'updated' => [1234567890]
            ]))
        ]);

        $this->client->stocks->earnings('AAPL', from: '2024-01-01');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('from', $query);
        $this->assertEquals('2024-01-01', $query['from']);
    }

    /**
     * Test earnings URL with from and to parameters.
     */
    public function testEarnings_withFromAndTo_addsParameters(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'fiscalYear' => [2024],
                'fiscalQuarter' => [1],
                'date' => ['2024-01-25'],
                'reportDate' => ['2024-02-01'],
                'reportTime' => ['after close'],
                'currency' => ['USD'],
                'reportedEPS' => [1.50],
                'estimatedEPS' => [1.45],
                'surpriseEPS' => [0.05],
                'surpriseEPSpct' => [0.03],
                'updated' => [1234567890]
            ]))
        ]);

        $this->client->stocks->earnings('AAPL', from: '2024-01-01', to: '2024-12-31');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('from', $query);
        $this->assertArrayHasKey('to', $query);
        $this->assertEquals('2024-01-01', $query['from']);
        $this->assertEquals('2024-12-31', $query['to']);
    }

    /**
     * Test earnings URL with countback parameter.
     */
    public function testEarnings_withCountback_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'fiscalYear' => [2024],
                'fiscalQuarter' => [1],
                'date' => ['2024-01-25'],
                'reportDate' => ['2024-02-01'],
                'reportTime' => ['after close'],
                'currency' => ['USD'],
                'reportedEPS' => [1.50],
                'estimatedEPS' => [1.45],
                'surpriseEPS' => [0.05],
                'surpriseEPSpct' => [0.03],
                'updated' => [1234567890]
            ]))
        ]);

        $this->client->stocks->earnings('AAPL', to: '2024-12-31', countback: 4);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('countback', $query);
        $this->assertEquals('4', $query['countback']);
    }

    // ========================================================================
    // NEWS ENDPOINT
    // API: GET /v1/stocks/news/{symbol}/
    // ========================================================================

    /**
     * Test news URL includes symbol in path.
     *
     * API expects: /v1/stocks/news/{symbol}/
     */
    public function testNews_basicRequest_correctPathFormat(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'headline' => ['Apple announces new product'],
                'content' => ['Full article content here...'],
                'source' => ['Reuters'],
                'publicationDate' => [1234567890]
            ]))
        ]);

        $this->client->stocks->news('AAPL', from: '2024-01-01');

        $this->assertCount(1, $this->history);
        $this->assertEquals('v1/stocks/news/AAPL/', $this->getLastRequestPath());
    }

    /**
     * Test news URL with from parameter.
     */
    public function testNews_withFrom_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'headline' => ['Apple announces new product'],
                'content' => ['Full article content here...'],
                'source' => ['Reuters'],
                'publicationDate' => [1234567890]
            ]))
        ]);

        $this->client->stocks->news('AAPL', from: '2024-01-01');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('from', $query);
        $this->assertEquals('2024-01-01', $query['from']);
    }

    /**
     * Test news URL with from and to parameters.
     */
    public function testNews_withFromAndTo_addsParameters(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'headline' => ['Apple announces new product'],
                'content' => ['Full article content here...'],
                'source' => ['Reuters'],
                'publicationDate' => [1234567890]
            ]))
        ]);

        $this->client->stocks->news('AAPL', from: '2024-01-01', to: '2024-01-31');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('from', $query);
        $this->assertArrayHasKey('to', $query);
        $this->assertEquals('2024-01-01', $query['from']);
        $this->assertEquals('2024-01-31', $query['to']);
    }

    /**
     * Test news URL with countback parameter.
     */
    public function testNews_withCountback_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'headline' => ['Apple announces new product'],
                'content' => ['Full article content here...'],
                'source' => ['Reuters'],
                'publicationDate' => [1234567890]
            ]))
        ]);

        $this->client->stocks->news('AAPL', to: '2024-01-31', countback: 10);

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('countback', $query);
        $this->assertEquals('10', $query['countback']);
    }

    /**
     * Test news URL with date parameter.
     */
    public function testNews_withDate_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'headline' => ['Apple announces new product'],
                'content' => ['Full article content here...'],
                'source' => ['Reuters'],
                'publicationDate' => [1234567890]
            ]))
        ]);

        $this->client->stocks->news('AAPL', from: '2024-01-01', date: '2024-01-15');

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('date', $query);
        $this->assertEquals('2024-01-15', $query['date']);
    }

    // ========================================================================
    // UNIVERSAL PARAMETERS
    // These apply to all endpoints via the Parameters object
    // ========================================================================

    /**
     * Test format=json adds parameter.
     */
    public function testUniversalParams_formatJson_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'mid' => [150.0],
                'change' => [1.0],
                'changepct' => [0.01],
                'updated' => [1234567890]
            ]))
        ]);

        $this->client->stocks->prices('AAPL', parameters: new Parameters(format: Format::JSON));

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('format', $query);
        $this->assertEquals('json', $query['format']);
    }

    /**
     * Test format=csv adds parameter.
     */
    public function testUniversalParams_formatCsv_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], "symbol,mid,change,changepct,updated\nAAPL,150.0,1.0,0.01,1234567890")
        ]);

        $this->client->stocks->prices('AAPL', parameters: new Parameters(format: Format::CSV));

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('format', $query);
        $this->assertEquals('csv', $query['format']);
    }

    /**
     * Test human_readable=true adds parameter.
     */
    public function testUniversalParams_humanReadable_addsParameter(): void
    {
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                'Symbol' => ['AAPL'],
                'Mid' => [150.0],
                'Change $' => [1.0],
                'Change %' => [0.01],
                'Date' => [1234567890]
            ]))
        ]);

        $this->client->stocks->prices('AAPL', parameters: new Parameters(use_human_readable: true));

        $query = $this->parseQuery($this->getLastRequestQuery());
        $this->assertArrayHasKey('human', $query);
        $this->assertEquals('true', $query['human']);
    }

    // ========================================================================
    // SYMBOL TRIMMING
    // Bug 017: Single-symbol endpoints should trim whitespace from symbols
    // ========================================================================

    /**
     * Test quote() trims whitespace from symbol.
     *
     * Bug 017: Symbols with leading/trailing whitespace should be trimmed
     * before being used in the URL path to avoid encoded spaces (%20).
     */
    public function testQuote_symbolWithWhitespace_isTrimmed(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'ask' => [150.1],
                'askSize' => [100],
                'bid' => [150.0],
                'bidSize' => [200],
                'mid' => [150.05],
                'last' => [150.0],
                'change' => [1.0],
                'changepct' => [0.01],
                'volume' => [1000000],
                'updated' => [1234567890]
            ]))
        ]);

        $this->client->stocks->quote('AAPL ');

        $path = $this->getLastRequestPath();
        $this->assertEquals('v1/stocks/quotes/AAPL/', $path);
        $this->assertStringNotContainsString('%20', $path, 'Path should not contain encoded space');
    }

    /**
     * Test candles() trims whitespace from symbol.
     */
    public function testCandles_symbolWithWhitespace_isTrimmed(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'o' => [150.0],
                'h' => [155.0],
                'l' => [149.0],
                'c' => [154.0],
                'v' => [1000000],
                't' => [1234567890]
            ]))
        ]);

        $this->client->stocks->candles(' AAPL ', '2024-01-01', '2024-01-31', 'D');

        $path = $this->getLastRequestPath();
        $this->assertEquals('v1/stocks/candles/D/AAPL/', $path);
        $this->assertStringNotContainsString('%20', $path, 'Path should not contain encoded space');
    }

    /**
     * Test prices() with single symbol trims whitespace.
     */
    public function testPrices_singleSymbolWithWhitespace_isTrimmed(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'mid' => [150.0],
                'change' => [1.0],
                'changepct' => [0.01],
                'updated' => [1234567890]
            ]))
        ]);

        $this->client->stocks->prices('  AAPL  ');

        $path = $this->getLastRequestPath();
        $this->assertEquals('v1/stocks/prices/AAPL/', $path);
        $this->assertStringNotContainsString('%20', $path, 'Path should not contain encoded space');
    }

    /**
     * Test earnings() trims whitespace from symbol.
     */
    public function testEarnings_symbolWithWhitespace_isTrimmed(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'fiscalYear' => [2024],
                'fiscalQuarter' => [1],
                'date' => ['2024-01-25'],
                'reportDate' => ['2024-02-01'],
                'reportTime' => ['after close'],
                'currency' => ['USD'],
                'reportedEPS' => [1.50],
                'estimatedEPS' => [1.45],
                'surpriseEPS' => [0.05],
                'surpriseEPSpct' => [0.03],
                'updated' => [1234567890]
            ]))
        ]);

        $this->client->stocks->earnings('AAPL ', from: '2024-01-01');

        $path = $this->getLastRequestPath();
        $this->assertEquals('v1/stocks/earnings/AAPL/', $path);
        $this->assertStringNotContainsString('%20', $path, 'Path should not contain encoded space');
    }

    /**
     * Test news() trims whitespace from symbol.
     */
    public function testNews_symbolWithWhitespace_isTrimmed(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'symbol' => ['AAPL'],
                'headline' => ['Apple announces new product'],
                'content' => ['Full article content here...'],
                'source' => ['Reuters'],
                'publicationDate' => [1234567890]
            ]))
        ]);

        $this->client->stocks->news(' AAPL', from: '2024-01-01');

        $path = $this->getLastRequestPath();
        $this->assertEquals('v1/stocks/news/AAPL/', $path);
        $this->assertStringNotContainsString('%20', $path, 'Path should not contain encoded space');
    }
}
