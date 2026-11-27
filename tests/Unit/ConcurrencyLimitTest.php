<?php

namespace MarketDataApp\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Endpoints\Utilities;
use MarketDataApp\Exceptions\BadStatusCodeError;
use MarketDataApp\Exceptions\RequestError;
use MarketDataApp\Settings;
use MarketDataApp\Tests\Support\AsyncClient;
use MarketDataApp\Tests\Support\GatedHandler;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Every request of one client draws from one pool of Settings::MAX_CONCURRENT_REQUESTS slots
 * (SDK requirements §12).
 *
 * The gated transport holds each request open until something waits on it or the test
 * completes it, and counts the requests open at once, so each count is read at rest.
 *
 * Mock response: NOT from real API output (uses synthetic/test data) in every test of this class.
 */
class ConcurrencyLimitTest extends TestCase
{
    use MockResponses;

    private const LIMIT = Settings::MAX_CONCURRENT_REQUESTS;

    protected function setUp(): void
    {
        $this->saveMarketDataTokenState();
        $this->clearMarketDataToken();

        // A fresh status entry for an unrelated service: retries read UNKNOWN and go on,
        // and no status request enters the gate.
        Utilities::clearApiStatusCache();
        Utilities::getApiStatusData()->update((object)[
            'service' => ['/test/status/'],
            'status' => ['online'],
            'online' => [true],
            'uptimePct30d' => [1.0],
            'uptimePct90d' => [1.0],
            'updated' => [time()],
        ]);
    }

    protected function tearDown(): void
    {
        Utilities::clearApiStatusCache();
        $this->restoreMarketDataTokenState();
        parent::tearDown();
    }

    public function testTwoOverlappingFanOutsNeverPutMoreThanFiftyRequestsInFlight(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $gate = new GatedHandler(fn (RequestInterface $request) => $this->okResponse($request));
        $client = $this->client($gate);

        $background = $this->startQuotes($client, 'BG', 60);
        $this->assertSame(self::LIMIT, $gate->open(), '60 requests started at once, the pool allows 50');

        $symbols = [];
        for ($i = 0; $i < 60; $i++) {
            $symbols[] = sprintf('AAPL250117C%08d', (100 + $i) * 1000);
        }
        $quotes = $client->options->quotes($symbols);

        $this->assertCount(60, $quotes->quotes);
        $this->assertCount(60, Utils::all($background)->wait());
        $this->assertSame(120, $gate->sent());
        $this->assertSame(self::LIMIT, $gate->peak());
    }

    public function testAFanOutStartedOnAFullPoolWaitsInLineBehindTheRequestsAlreadyWaiting(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $gate = new GatedHandler(fn (RequestInterface $request) => $this->okResponse($request));
        $client = $this->client($gate);

        $first = $this->startQuotes($client, 'FIRST', self::LIMIT);
        $second = $this->startQuotes($client, 'SECOND', 20);
        $this->assertSame(self::LIMIT, $gate->sent(), 'the second fan-out waits for a slot');

        $calls = [];
        for ($i = 0; $i < 60; $i++) {
            $calls[] = ["v1/stocks/quotes/THIRD{$i}/", []];
        }
        $results = $client->execute_in_parallel($calls);

        $this->assertCount(60, $results);
        $this->assertCount(70, Utils::all(array_merge($first, $second))->wait());
        $this->assertSame(130, $gate->sent());
        $this->assertSame(self::LIMIT, $gate->peak());

        $sentAfterTheFirst = array_slice($gate->paths(), self::LIMIT, 20);
        foreach ($sentAfterTheFirst as $path) {
            $this->assertStringContainsString('SECOND', $path, 'the requests that waited first start first');
        }
    }

    public function testAFreedSlotStartsTheNextRequestWhileTheOthersAreStillInFlight(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $gate = new GatedHandler(fn (RequestInterface $request) => $this->okResponse($request));
        $client = $this->client($gate);

        $requests = $this->startQuotes($client, 'SYM', self::LIMIT + 1);
        $this->assertSame(self::LIMIT, $gate->sent());

        $gate->completeOldest();
        Utils::queue()->run();

        $this->assertSame(self::LIMIT + 1, $gate->sent(), 'one completion starts the request that waited');
        $this->assertSame(self::LIMIT, $gate->open(), 'the other 49 are still in flight');

        $this->assertCount(self::LIMIT + 1, Utils::all($requests)->wait());
        $this->assertSame(self::LIMIT, $gate->peak());
    }

    #[DataProvider('failureProvider')]
    public function testRequestsThatFailGiveTheirSlotsBack(string $failure, string $exceptionClass): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $gate = new GatedHandler(function (RequestInterface $request) use ($failure) {
            if (str_contains($request->getUri()->getPath(), 'FAIL')) {
                return $this->failure($failure, $request);
            }
            return $this->okResponse($request);
        });
        $client = $this->client($gate);

        $failing = $this->startQuotes($client, 'FAIL', self::LIMIT);
        $waiting = $this->startQuotes($client, 'OK', 10);
        $this->assertSame(self::LIMIT, $gate->open(), '60 requests started at once, the pool allows 50');

        $failed = Utils::settle($failing)->wait();
        $served = Utils::settle($waiting)->wait();

        foreach ($failed as $outcome) {
            $this->assertSame(PromiseInterface::REJECTED, $outcome['state']);
            $this->assertInstanceOf($exceptionClass, $outcome['reason']);
        }
        foreach ($served as $outcome) {
            $this->assertSame(PromiseInterface::FULFILLED, $outcome['state']);
        }

        $sentBefore = $gate->sent();
        $fresh = $this->startQuotes($client, 'FRESH', self::LIMIT);
        $this->assertSame($sentBefore + self::LIMIT, $gate->sent(), 'every slot came back');
        $this->assertCount(self::LIMIT, Utils::all($fresh)->wait());
        $this->assertSame(self::LIMIT, $gate->peak());
    }

    public static function failureProvider(): array
    {
        return [
            'client error 400' => ['400', BadStatusCodeError::class],
            'server error 503 after its retries' => ['503', RequestError::class],
            'network error after its retries' => ['network', RequestError::class],
            'unexpected exception from the transport' => ['exception', \RuntimeException::class],
        ];
    }

    public function testARequestThatCannotStartWhenItsSlotFreesGivesTheSlotOn(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $gate = new GatedHandler(fn (RequestInterface $request) => $this->okResponse($request));
        $client = $this->client($gate);

        $requests = $this->startQuotes($client, 'SYM', self::LIMIT);
        // 'xml' has no Accept header, so building the request throws once the request has a slot.
        $broken = $client->startAsync('v1/stocks/quotes/BROKEN/', ['format' => 'xml']);
        $after = $this->startQuotes($client, 'AFTER', 1);

        $outcome = Utils::settle([$broken])->wait()[0];
        $this->assertSame(PromiseInterface::REJECTED, $outcome['state']);
        $this->assertInstanceOf(\UnhandledMatchError::class, $outcome['reason']);

        $this->assertCount(self::LIMIT + 1, Utils::all(array_merge($requests, $after))->wait());
        $this->assertNotContains('/v1/stocks/quotes/BROKEN/', $gate->paths());
        $this->assertSame(self::LIMIT, $gate->peak());
    }

    public function testARetryWaitingOutItsBackoffHoldsNoSlot(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $attempts = [];
        $gate = new GatedHandler(function (RequestInterface $request) use (&$attempts) {
            $path = $request->getUri()->getPath();
            $attempts[$path] = ($attempts[$path] ?? 0) + 1;
            if (str_contains($path, 'RETRY') && $attempts[$path] === 1) {
                return new Response(503, [], json_encode(['s' => 'error', 'errmsg' => 'Service Unavailable']));
            }
            return $this->okResponse($request);
        });
        $client = $this->client($gate);
        $client->holdBackoff = true;

        $retrying = $this->startQuotes($client, 'RETRY', self::LIMIT);
        for ($i = 0; $i < self::LIMIT; $i++) {
            $gate->completeOldest();
        }
        Utils::queue()->run();
        $this->assertCount(self::LIMIT, $client->timers, 'all 50 wait out a backoff');
        $this->assertSame(0, $gate->open());

        $others = $this->startQuotes($client, 'OTHER', self::LIMIT);
        $this->assertSame(self::LIMIT, $gate->open(), 'the requests in backoff hold no slot');

        $client->fireTimers();
        $this->assertCount(2 * self::LIMIT, Utils::all(array_merge($retrying, $others))->wait());
        $this->assertSame(3 * self::LIMIT, $gate->sent());
        $this->assertSame(self::LIMIT, $gate->peak());
    }

    public function testABlockingRequestHoldsNoSlotDuringItsBackoff(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $attempts = 0;
        $gate = new GatedHandler(function (RequestInterface $request) use (&$attempts) {
            if ($request->getUri()->getPath() === '/v1/stocks/quotes/AAPL/' && ++$attempts === 1) {
                return new Response(503, [], json_encode(['s' => 'error', 'errmsg' => 'Service Unavailable']));
            }
            return $this->okResponse($request);
        });
        $client = $this->client($gate);

        $fanOutResults = null;
        $client->duringBlockingBackoff = function () use ($client, &$fanOutResults): void {
            $calls = [];
            for ($i = 0; $i < self::LIMIT; $i++) {
                $calls[] = ["v1/stocks/quotes/DURING{$i}/", []];
            }
            $fanOutResults = $client->execute_in_parallel($calls);
        };

        $quote = $client->stocks->quote('AAPL');

        $this->assertSame('AAPL', $quote->symbol);
        $this->assertCount(self::LIMIT, $fanOutResults);
        $this->assertSame(self::LIMIT, $gate->peak(), 'the fan-out during the backoff had all 50 slots');
        $this->assertSame(self::LIMIT + 2, $gate->sent());
    }

    public function testABlockingRequestOnAFullPoolWaitsForASlotWithoutDeadlocking(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $gate = new GatedHandler(fn (RequestInterface $request) => $this->okResponse($request));
        $client = $this->client($gate);

        $background = $this->startQuotes($client, 'BG', self::LIMIT);

        $quote = $client->stocks->quote('AAPL');

        $this->assertSame('AAPL', $quote->symbol);
        $this->assertSame(self::LIMIT, $gate->peak(), 'the blocking request waited for a slot');
        $this->assertSame(self::LIMIT + 1, $gate->sent());
        $this->assertSame(self::LIMIT - 1, $gate->open(), 'it freed a slot by completing one request');
        $this->assertCount(self::LIMIT, Utils::all($background)->wait());
    }

    public function testAClientWithEverySlotTakenDoesNotHoldUpAnotherClient(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $gate = new GatedHandler(fn (RequestInterface $request) => $this->okResponse($request));
        $busy = $this->client($gate);
        $other = new AsyncClient('');
        $other->setGuzzle($this->guzzle($gate));

        $background = $this->startQuotes($busy, 'BG', self::LIMIT);

        $quote = $other->stocks->quote('AAPL');

        $this->assertSame('AAPL', $quote->symbol);
        $this->assertSame(self::LIMIT + 1, $gate->peak(), 'the other client sent at once, beside the 50');
        $this->assertSame(self::LIMIT, $gate->open(), 'none of the busy client\'s requests had to finish first');
        $this->assertCount(self::LIMIT, Utils::all($background)->wait());
    }

    public function testTheApiStatusRefreshWaitsForASlot(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $gate = new GatedHandler(fn (RequestInterface $request) => $this->okResponse($request));
        $client = $this->client($gate);

        $background = $this->startQuotes($client, 'BG', self::LIMIT);
        Utilities::getApiStatusData()->refreshAsync($client);
        $this->assertSame(self::LIMIT, $gate->sent(), 'the status request waits for a slot');

        $gate->completeOldest();
        Utils::queue()->run();

        $this->assertSame(self::LIMIT + 1, $gate->sent());
        $this->assertSame('/status/', $gate->paths()[self::LIMIT]);

        $this->assertCount(self::LIMIT, Utils::all($background)->wait());
        $this->completeEverything($gate);
        $this->assertSame(self::LIMIT, $gate->peak());
    }

    private function client(GatedHandler $gate): AsyncClient
    {
        $client = new AsyncClient('');
        $client->setGuzzle($this->guzzle($gate));

        return $client;
    }

    private function guzzle(GatedHandler $gate): GuzzleClient
    {
        return new GuzzleClient([
            'base_uri' => AsyncClient::API_URL,
            'handler' => HandlerStack::create($gate),
        ]);
    }

    /**
     * @return list<PromiseInterface>
     */
    private function startQuotes(AsyncClient $client, string $prefix, int $count): array
    {
        $promises = [];
        for ($i = 0; $i < $count; $i++) {
            $promises[] = $client->startAsync("v1/stocks/quotes/{$prefix}{$i}/");
        }

        return $promises;
    }

    private function completeEverything(GatedHandler $gate): void
    {
        while ($gate->open() > 0) {
            $gate->completeOldest();
            Utils::queue()->run();
        }
    }

    private function okResponse(RequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $symbol = basename($path);

        if ($path === '/status/') {
            return new Response(200, [], json_encode([
                's' => 'ok',
                'service' => ['/v1/stocks/quotes/'],
                'status' => ['online'],
                'online' => [true],
                'uptimePct30d' => [1.0],
                'uptimePct90d' => [1.0],
                'updated' => [time()],
            ]));
        }

        if (str_starts_with($path, '/v1/options/quotes/')) {
            return new Response(200, [], json_encode([
                's' => 'ok',
                'optionSymbol' => [$symbol],
                'underlying' => ['AAPL'],
                'expiration' => [1737147600],
                'side' => ['call'],
                'strike' => [150],
                'firstTraded' => [1617197400],
                'dte' => [30],
                'ask' => [116.9],
                'askSize' => [90],
                'bid' => [114.1],
                'bidSize' => [90],
                'mid' => [115.5],
                'last' => [115],
                'openInterest' => [21957],
                'volume' => [0],
                'inTheMoney' => [true],
                'underlyingPrice' => [175.13],
                'iv' => [1.629],
                'delta' => [1],
                'gamma' => [0],
                'theta' => [-0.009],
                'vega' => [0],
                'intrinsicValue' => [115.13],
                'extrinsicValue' => [0.37],
                'updated' => [1684702875],
            ]));
        }

        return new Response(200, [], json_encode([
            's' => 'ok',
            'symbol' => [$symbol],
            'last' => [150.0],
            'ask' => [150.1],
            'askSize' => [200],
            'bid' => [150.0],
            'bidSize' => [300],
            'mid' => [150.05],
            'change' => [0.5],
            'changepct' => [0.33],
            'volume' => [1000000],
            'updated' => [1234567890],
        ]));
    }

    private function failure(string $failure, RequestInterface $request): ResponseInterface|\Throwable
    {
        return match ($failure) {
            '400' => new Response(400, [], json_encode(['s' => 'error', 'errmsg' => 'Bad Request'])),
            '503' => new Response(503, [], json_encode(['s' => 'error', 'errmsg' => 'Service Unavailable'])),
            'network' => new ConnectException('Connection refused', $request),
            'exception' => new \RuntimeException('The transport broke'),
        };
    }
}
