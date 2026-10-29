<?php

namespace MarketDataApp\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\ClientBase;
use MarketDataApp\Endpoints\Utilities;
use MarketDataApp\Exceptions\RequestError;
use MarketDataApp\Retry\RetryConfig;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Tests for the fixed request and connection timeouts of the default HTTP client.
 *
 * Note: All mock responses in this test class are NOT from real API output (synthetic/test data).
 */
class RequestTimeoutTest extends TestCase
{
    use MockResponses;

    /**
     * The client instance used for testing.
     *
     * @var Client
     */
    private Client $client;

    /**
     * Create a client with an empty token and a fresh, unrelated status entry.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->saveMarketDataTokenState();
        $this->clearMarketDataToken();

        $this->client = new Client("");

        Utilities::clearApiStatusCache();
        Utilities::getApiStatusData()->update((object)[
            'service' => ['/test/status/'],
            'status' => ['online'],
            'online' => [true],
            'uptimePct30d' => [1.0],
            'uptimePct90d' => [1.0],
            'updated' => [time()]
        ]);
    }

    /**
     * Restore the original MARKETDATA_TOKEN state.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->restoreMarketDataTokenState();
        parent::tearDown();
    }

    /**
     * The client built by the constructor carries a 99-second request timeout and a 2-second connection timeout.
     *
     * @return void
     */
    public function testDefaultClient_carriesTheFixedTimeouts(): void
    {
        $guzzle = $this->defaultGuzzle();

        $this->assertSame(99, $guzzle->getConfig('timeout'));
        $this->assertSame(2, $guzzle->getConfig('connect_timeout'));
    }

    /**
     * The default client's timeouts reach the sync, async and raw request paths.
     *
     * @return void
     */
    public function testDefaultTimeouts_reachEveryRequestPath(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], $this->quoteBody()),
            new Response(200, [], $this->quoteBody()),
            new Response(200, [], json_encode([])),
        ]));
        $stack->push(Middleware::history($history));
        $this->client->setGuzzle(new GuzzleClient(['handler' => $stack] + $this->defaultGuzzle()->getConfig()));

        $this->client->stocks->quote('AAPL');
        $this->client->execute_in_parallel([['v1/stocks/quotes/AAPL/', []]]);
        $this->client->makeRawRequest('user/');

        $this->assertCount(3, $history);
        foreach ($history as $transaction) {
            $this->assertSame(99, $transaction['options']['timeout']);
            $this->assertSame(2, $transaction['options']['connect_timeout']);
        }
    }

    /**
     * A client passed to setGuzzle() keeps its own timeouts.
     *
     * @return void
     */
    public function testSetGuzzle_keepsTheClientsOwnTimeouts(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], $this->quoteBody()),
        ]));
        $stack->push(Middleware::history($history));
        $this->client->setGuzzle(new GuzzleClient([
            'handler' => $stack,
            'timeout' => 30,
            'connect_timeout' => 5,
        ]));

        $this->client->stocks->quote('AAPL');

        $this->assertSame(30, $history[0]['options']['timeout']);
        $this->assertSame(5, $history[0]['options']['connect_timeout']);
    }

    /**
     * A timed-out sync request is retried like any network failure, then thrown as RequestError.
     *
     * @return void
     */
    public function testSyncTimeout_isRetriedThenThrownAsRequestError(): void
    {
        $history = [];
        $this->setMockResponsesWithHistory($this->timeouts(), $history);

        try {
            $this->client->stocks->quote('AAPL');
            $this->fail('Expected RequestError');
        } catch (RequestError $e) {
            $this->assertStringContainsString('Operation timed out after 99000 milliseconds', $e->getMessage());
            $this->assertInstanceOf(ConnectException::class, $e->getPrevious());
        }

        $this->assertCount(RetryConfig::MAX_RETRY_ATTEMPTS, $history);
    }

    /**
     * A timed-out async request is retried like any network failure, then thrown as RequestError.
     *
     * @return void
     */
    public function testAsyncTimeout_isRetriedThenThrownAsRequestError(): void
    {
        $history = [];
        $this->setMockResponsesWithHistory($this->timeouts(), $history);

        try {
            $this->client->execute_in_parallel([['v1/stocks/quotes/AAPL/', []]]);
            $this->fail('Expected RequestError');
        } catch (RequestError $e) {
            $this->assertStringContainsString('Operation timed out after 99000 milliseconds', $e->getMessage());
        }

        $this->assertCount(RetryConfig::MAX_RETRY_ATTEMPTS, $history);
    }

    /**
     * A timeout after the response headers arrived is thrown as RequestError on Guzzle 8.
     *
     * @return void
     */
    public function testTimeoutAfterHeaders_isThrownAsRequestError(): void
    {
        if (!class_exists(ResponseTimeoutException::class)) {
            $this->markTestSkipped('ResponseTimeoutException exists only on Guzzle 8.');
        }

        $request = new Request('GET', 'v1/stocks/quotes/AAPL/');
        $message = 'cURL error 28: Operation timed out after 99000 milliseconds with 512 bytes received';
        $history = [];
        $this->setMockResponsesWithHistory(array_fill(
            0,
            RetryConfig::MAX_RETRY_ATTEMPTS,
            new ResponseTimeoutException($message, $request, new Response(200))
        ), $history);

        $this->expectException(RequestError::class);
        $this->expectExceptionMessage('Operation timed out after 99000 milliseconds');

        $this->client->stocks->quote('AAPL');
    }

    /**
     * Read the Guzzle client the constructor built.
     *
     * @return GuzzleClient The client's default Guzzle instance.
     */
    private function defaultGuzzle(): GuzzleClient
    {
        return (new ReflectionProperty(ClientBase::class, 'guzzle'))->getValue(new Client(""));
    }

    /**
     * Build one connection timeout per allowed attempt.
     *
     * @return ConnectException[] The exceptions the mock handler throws in order.
     */
    private function timeouts(): array
    {
        $request = new Request('GET', 'v1/stocks/quotes/AAPL/');
        $message = 'cURL error 28: Operation timed out after 99000 milliseconds with 0 bytes received';

        return array_fill(0, RetryConfig::MAX_RETRY_ATTEMPTS, new ConnectException($message, $request));
    }

    /**
     * Build a single-symbol quote body.
     *
     * @return string The JSON body.
     */
    private function quoteBody(): string
    {
        return json_encode([
            's' => 'ok', 'symbol' => ['AAPL'], 'last' => [150.0], 'ask' => [150.1], 'askSize' => [200],
            'bid' => [150.0], 'bidSize' => [300], 'mid' => [150.05], 'change' => [0.5], 'changepct' => [0.33],
            'volume' => [1000000], 'updated' => [1234567890],
        ]);
    }
}
