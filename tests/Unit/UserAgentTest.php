<?php

namespace MarketDataApp\Tests\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\ClientBase;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Test case for User-Agent header functionality in the MarketDataApp SDK.
 *
 * This class tests that the User-Agent header is correctly included in all HTTP requests
 * with the proper RFC 7231 format: product/product-version
 */
class UserAgentTest extends TestCase
{
    use MockResponses;
    /**
     * The client instance used for testing.
     *
     * @var Client
     */
    private Client $client;

    /**
     * History container for capturing HTTP requests.
     *
     * @var array
     */
    private array $history = [];

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
        $this->client = new Client('');
        $this->history = [];
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
     * Set up mock responses with history middleware to capture requests.
     *
     * @param array $responses An array of mock responses to be returned by the client.
     *
     * @return void
     */
    private function setMockResponsesWithHistory(array $responses): void
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        
        // Add history middleware to capture requests
        $history = Middleware::history($this->history);
        $handlerStack->push($history);
        
        $this->client->setGuzzle(new \GuzzleHttp\Client(['handler' => $handlerStack]));
    }

    /**
     * Test that VERSION constant is defined and has correct value.
     *
     * @return void
     */
    public function testVersionConstant_defined(): void
    {
        $this->assertTrue(defined(ClientBase::class . '::VERSION'));
        $this->assertEquals('0.8.0', ClientBase::VERSION);
    }

    /**
     * Test that User-Agent header is included in sync requests (execute method).
     *
     * @return void
     */
    public function testUserAgent_includedInSyncRequest(): void
    {
        $mockedResponse = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'last' => [150.0],
            'ask' => [150.1],
            'askSize' => [200],
            'bid' => [150.0],
            'bidSize' => [300],
            'mid' => [150.05],
            'change' => [0.5],
            'changepct' => [0.33],
            'volume' => [1000000],
            'updated' => [1234567890]
        ];
        
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode($mockedResponse))
        ]);

        $this->client->stocks->quote('AAPL');

        // Verify request was captured
        $this->assertCount(1, $this->history, 'Request should be captured in history');
        
        $request = $this->history[0]['request'];
        $headers = $request->getHeaders();
        
        // Verify User-Agent header is present
        $this->assertArrayHasKey('User-Agent', $headers, 'User-Agent header should be present');
        $this->assertCount(1, $headers['User-Agent'], 'User-Agent header should have one value');
        
        // Verify User-Agent format: marketdata-sdk-php/0.8.0 (RFC 7231 format)
        $userAgent = $headers['User-Agent'][0];
        $this->assertEquals('marketdata-sdk-php/0.8.0', $userAgent, 
            'User-Agent should follow RFC 7231 format: product/product-version');
    }

    /**
     * Test that User-Agent header is included in async requests (executeAsync method).
     *
     * @return void
     */
    public function testUserAgent_includedInAsyncRequest(): void
    {
        $mockedResponse = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'last' => [150.0],
            'ask' => [150.1],
            'askSize' => [200],
            'bid' => [150.0],
            'bidSize' => [300],
            'mid' => [150.05],
            'change' => [0.5],
            'changepct' => [0.33],
            'volume' => [1000000],
            'updated' => [1234567890]
        ];
        
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode($mockedResponse))
        ]);

        // Use execute_in_parallel which uses async requests
        $this->client->execute_in_parallel([
            ['v1/stocks/quote', ['symbol' => 'AAPL']]
        ]);

        // Verify request was captured
        $this->assertCount(1, $this->history, 'Request should be captured in history');
        
        $request = $this->history[0]['request'];
        $headers = $request->getHeaders();
        
        // Verify User-Agent header is present
        $this->assertArrayHasKey('User-Agent', $headers, 'User-Agent header should be present in async request');
        $this->assertEquals('marketdata-sdk-php/0.8.0', $headers['User-Agent'][0],
            'User-Agent should follow RFC 7231 format in async requests');
    }

    /**
     * Test that User-Agent header is included in raw requests (makeRawRequest method).
     *
     * @return void
     */
    public function testUserAgent_includedInRawRequest(): void
    {
        $mockedResponse = [
            's' => 'ok',
            'token' => 'test_token',
            'credits' => 100
        ];
        
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode($mockedResponse))
        ]);

        $this->client->makeRawRequest('user/');

        // Verify request was captured
        $this->assertCount(1, $this->history, 'Request should be captured in history');
        
        $request = $this->history[0]['request'];
        $headers = $request->getHeaders();
        
        // Verify User-Agent header is present
        $this->assertArrayHasKey('User-Agent', $headers, 'User-Agent header should be present in raw request');
        $this->assertEquals('marketdata-sdk-php/0.8.0', $headers['User-Agent'][0],
            'User-Agent should follow RFC 7231 format in raw requests');
    }

    /**
     * Test that makeRawRequest re-throws non-401 ClientExceptions.
     *
     * This test covers line 833 in ClientBase.php where non-401 ClientExceptions
     * are re-thrown after being caught.
     *
     * @return void
     */
    public function testMakeRawRequest_withNon401ClientException_rethrowsException(): void
    {
        // Mock a 403 Forbidden response (non-401 ClientException)
        // MockHandler will automatically throw ClientException for 4xx responses
        $this->setMockResponsesWithHistory([
            new Response(403, [], json_encode(['errmsg' => 'Forbidden']))
        ]);

        // Expect ClientException to be re-thrown (not converted to UnauthorizedException)
        $this->expectException(\GuzzleHttp\Exception\ClientException::class);

        $this->client->makeRawRequest('user/');
    }

    /**
     * Test that User-Agent header format follows RFC 7231 (product/product-version).
     *
     * @return void
     */
    public function testUserAgent_format_followsRFC7231(): void
    {
        $mockedResponse = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'last' => [150.0],
            'ask' => [150.1],
            'askSize' => [200],
            'bid' => [150.0],
            'bidSize' => [300],
            'mid' => [150.05],
            'change' => [0.5],
            'changepct' => [0.33],
            'volume' => [1000000],
            'updated' => [1234567890]
        ];
        
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode($mockedResponse))
        ]);

        $this->client->stocks->quote('AAPL');

        $request = $this->history[0]['request'];
        $userAgent = $request->getHeaderLine('User-Agent');
        
        // RFC 7231 format: product/product-version (with slash separator)
        // Should NOT be: marketdata-sdk-php-0.8.0 (missing slash - incorrect format)
        // Should be: marketdata-sdk-php/0.8.0 (with slash - correct format)
        $this->assertStringContainsString('/', $userAgent, 
            'User-Agent should contain slash separator per RFC 7231');
        $this->assertStringStartsWith('marketdata-sdk-php/', $userAgent,
            'User-Agent should start with product name and slash');
        $this->assertStringEndsWith(ClientBase::VERSION, $userAgent,
            'User-Agent should end with version number');
        
        // Verify format: exactly "marketdata-sdk-php/0.8.0"
        $this->assertEquals('marketdata-sdk-php/' . ClientBase::VERSION, $userAgent,
            'User-Agent format should be: marketdata-sdk-php/{version}');
    }

    /**
     * Test that User-Agent header is included in requests with different formats (JSON, CSV, HTML).
     *
     * @return void
     */
    public function testUserAgent_includedInAllFormats(): void
    {
        // Complete JSON response with all required fields
        $jsonResponse = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'ask' => [150.1],
            'askSize' => [200],
            'bid' => [150.0],
            'bidSize' => [300],
            'mid' => [150.05],
            'last' => [150.0],
            'change' => [0.5],
            'changepct' => [0.33],
            'volume' => [1000000],
            'updated' => [1234567890]
        ];
        $csvResponse = "symbol,last\nAAPL,150.0";
        $htmlResponse = "<table><tr><th>symbol</th><th>last</th></tr><tr><td>AAPL</td><td>150.0</td></tr></table>";
        
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode($jsonResponse)), // JSON
            new Response(200, [], $csvResponse),                // CSV
            new Response(200, [], $htmlResponse)                // HTML
        ]);

        // Test JSON format
        $this->client->stocks->quote('AAPL', parameters: new \MarketDataApp\Endpoints\Requests\Parameters(
            format: \MarketDataApp\Enums\Format::JSON
        ));
        
        // Test CSV format
        $this->client->stocks->quote('AAPL', parameters: new \MarketDataApp\Endpoints\Requests\Parameters(
            format: \MarketDataApp\Enums\Format::CSV
        ));
        
        // Test HTML format
        $this->client->stocks->quote('AAPL', parameters: new \MarketDataApp\Endpoints\Requests\Parameters(
            format: \MarketDataApp\Enums\Format::HTML
        ));

        // Verify all three requests were captured
        $this->assertCount(3, $this->history, 'All three requests should be captured');
        
        // Verify User-Agent is present in all requests
        foreach ($this->history as $index => $transaction) {
            $request = $transaction['request'];
            $userAgent = $request->getHeaderLine('User-Agent');
            $this->assertEquals('marketdata-sdk-php/0.8.0', $userAgent,
                "User-Agent should be present in request #{$index}");
        }
    }

    /**
     * Test that User-Agent header is included in parallel requests.
     *
     * @return void
     */
    public function testUserAgent_includedInParallelRequests(): void
    {
        // Complete responses with all required fields
        $response1 = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'ask' => [150.1],
            'askSize' => [200],
            'bid' => [150.0],
            'bidSize' => [300],
            'mid' => [150.05],
            'last' => [150.0],
            'change' => [0.5],
            'changepct' => [0.33],
            'volume' => [1000000],
            'updated' => [1234567890]
        ];
        $response2 = [
            's' => 'ok',
            'symbol' => ['MSFT'],
            'ask' => [300.1],
            'askSize' => [200],
            'bid' => [300.0],
            'bidSize' => [300],
            'mid' => [300.05],
            'last' => [300.0],
            'change' => [0.5],
            'changepct' => [0.33],
            'volume' => [1000000],
            'updated' => [1234567890]
        ];
        $response3 = [
            's' => 'ok',
            'symbol' => ['GOOGL'],
            'ask' => [2500.1],
            'askSize' => [200],
            'bid' => [2500.0],
            'bidSize' => [300],
            'mid' => [2500.05],
            'last' => [2500.0],
            'change' => [0.5],
            'changepct' => [0.33],
            'volume' => [1000000],
            'updated' => [1234567890]
        ];
        
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
            new Response(200, [], json_encode($response3))
        ]);

        $this->client->execute_in_parallel([
            ['v1/stocks/quote', ['symbol' => 'AAPL']],
            ['v1/stocks/quote', ['symbol' => 'MSFT']],
            ['v1/stocks/quote', ['symbol' => 'GOOGL']]
        ]);

        // Verify all three requests were captured
        $this->assertCount(3, $this->history, 'All parallel requests should be captured');
        
        // Verify User-Agent is present in all parallel requests
        foreach ($this->history as $index => $transaction) {
            $request = $transaction['request'];
            $userAgent = $request->getHeaderLine('User-Agent');
            $this->assertEquals('marketdata-sdk-php/0.8.0', $userAgent,
                "User-Agent should be present in parallel request #{$index}");
        }
    }

    /**
     * Test that User-Agent header is consistent across multiple requests.
     *
     * @return void
     */
    public function testUserAgent_consistentAcrossRequests(): void
    {
        // Complete response with all required fields
        $mockedResponse = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'ask' => [150.1],
            'askSize' => [200],
            'bid' => [150.0],
            'bidSize' => [300],
            'mid' => [150.05],
            'last' => [150.0],
            'change' => [0.5],
            'changepct' => [0.33],
            'volume' => [1000000],
            'updated' => [1234567890]
        ];
        
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode($mockedResponse)),
            new Response(200, [], json_encode($mockedResponse)),
            new Response(200, [], json_encode($mockedResponse))
        ]);

        // Make multiple requests
        $this->client->stocks->quote('AAPL');
        $this->client->stocks->quote('MSFT');
        $this->client->stocks->quote('GOOGL');

        // Verify all requests have the same User-Agent
        $expectedUserAgent = 'marketdata-sdk-php/' . ClientBase::VERSION;
        foreach ($this->history as $index => $transaction) {
            $request = $transaction['request'];
            $userAgent = $request->getHeaderLine('User-Agent');
            $this->assertEquals($expectedUserAgent, $userAgent,
                "User-Agent should be consistent across all requests (request #{$index})");
        }
    }
}
