<?php

namespace MarketDataApp\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Exceptions\BadStatusCodeError;
use MarketDataApp\Exceptions\ForbiddenException;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Tests response metadata used to diagnose account IP restrictions.
 */
class IpResponseHeadersTest extends TestCase
{
    use MockResponses;

    private Client $client;

    protected function setUp(): void
    {
        $this->saveMarketDataTokenState();
        $this->clearMarketDataToken();
        $this->client = new Client("");
    }

    protected function tearDown(): void
    {
        $this->restoreMarketDataTokenState();
        parent::tearDown();
    }

    public function testExecuteCapturesDetectedIpFromSuccessfulResponse(): void
    {
        $this->setMockResponses([
            $this->successfulResponse(['X-API-Detected-IP' => '203.0.113.10']),
        ]);

        $this->client->execute('v1/stocks/quotes/AAPL');

        $this->assertSame('203.0.113.10', $this->client->detected_ip);
    }

    public function testParallelExecutionCapturesDetectedIpFromSuccessfulResponse(): void
    {
        $this->setMockResponses([
            $this->successfulResponse(['x-api-detected-ip' => '2001:db8::10']),
        ]);

        $this->client->execute_in_parallel([['v1/stocks/quotes/AAPL', []]]);

        $this->assertSame('2001:db8::10', $this->client->detected_ip);
    }

    public function testRawRequestCapturesDetectedIpFromSuccessfulResponse(): void
    {
        $this->setMockResponses([
            new Response(200, ['X-API-Detected-IP' => '203.0.113.11'], '{}'),
        ]);

        $this->client->makeRawRequest('user/');

        $this->assertSame('203.0.113.11', $this->client->detected_ip);
    }

    public function testExtractDetectedIpReturnsNullWithoutResponse(): void
    {
        $this->assertNull($this->client->extractDetectedIpFromResponse(null));
    }

    public function testResponseWithoutDetectedIpDoesNotEraseLatestValue(): void
    {
        $this->setMockResponses([
            $this->successfulResponse(['X-API-Detected-IP' => '203.0.113.12']),
            $this->successfulResponse(),
        ]);

        $this->client->execute('v1/stocks/quotes/AAPL');
        $this->client->execute('v1/stocks/quotes/AAPL');

        $this->assertSame('203.0.113.12', $this->client->detected_ip);
    }

    public function testForbiddenResponseSurfacesAuthorizedIp(): void
    {
        $this->setMockResponses([
            new Response(
                403,
                ['X-API-Authorized-IP' => '198.51.100.25'],
                json_encode(['s' => 'error', 'errmsg' => 'Access denied'])
            ),
        ]);

        try {
            $this->client->execute('v1/stocks/quotes/AAPL');
            $this->fail('Expected a ForbiddenException.');
        } catch (ForbiddenException $exception) {
            $this->assertInstanceOf(BadStatusCodeError::class, $exception);
            $this->assertSame('198.51.100.25', $exception->authorizedIp);
            $this->assertSame('198.51.100.25', $exception->getAuthorizedIp());
            $this->assertStringContainsString('Authorized IP: 198.51.100.25', $exception->getMessage());
        }
    }

    public function testRawForbiddenResponseSurfacesAuthorizedIp(): void
    {
        $this->setMockResponses([
            new Response(
                403,
                ['X-API-Authorized-IP' => '198.51.100.27'],
                json_encode(['s' => 'error', 'errmsg' => 'Access denied'])
            ),
        ]);

        try {
            $this->client->makeRawRequest('user/');
            $this->fail('Expected a ForbiddenException.');
        } catch (ForbiddenException $exception) {
            $this->assertSame('198.51.100.27', $exception->getAuthorizedIp());
            $this->assertStringContainsString('Authorized IP: 198.51.100.27', $exception->getMessage());
        }
    }

    public function testParallelForbiddenResponseSurfacesAuthorizedIp(): void
    {
        $this->setMockResponses([
            new Response(
                403,
                ['X-API-Authorized-IP' => '2001:db8::25'],
                json_encode(['s' => 'error', 'errmsg' => 'Access denied'])
            ),
        ]);

        try {
            $this->client->execute_in_parallel([['v1/stocks/quotes/AAPL', []]]);
            $this->fail('Expected a ForbiddenException.');
        } catch (ForbiddenException $exception) {
            $this->assertSame('2001:db8::25', $exception->getAuthorizedIp());
            $this->assertStringContainsString('Authorized IP: 2001:db8::25', $exception->getMessage());
        }
    }

    public function testForbiddenResponseWithoutAuthorizedIpPreservesOriginalMessage(): void
    {
        $this->setMockResponses([
            new Response(
                403,
                ['X-API-BLOCKED-IP' => '192.0.2.50'],
                json_encode(['s' => 'error', 'errmsg' => 'Access denied'])
            ),
        ]);

        try {
            $this->client->execute('v1/stocks/quotes/AAPL');
            $this->fail('Expected a ForbiddenException.');
        } catch (ForbiddenException $exception) {
            $this->assertNull($exception->authorizedIp);
            $this->assertNull($exception->getAuthorizedIp());
            $this->assertSame('Access denied', $exception->getMessage());
        }
    }

    public function testValidationPathSurfacesAuthorizedIpWhenHttpErrorsAreDisabled(): void
    {
        $mock = new MockHandler([
            new Response(
                403,
                ['X-API-Authorized-IP' => '198.51.100.26'],
                json_encode(['s' => 'error', 'errmsg' => 'Access denied'])
            ),
        ]);
        $this->client->setGuzzle(new GuzzleClient([
            'handler' => HandlerStack::create($mock),
            'http_errors' => false,
        ]));

        try {
            $this->client->execute('v1/stocks/quotes/AAPL');
            $this->fail('Expected a ForbiddenException.');
        } catch (ForbiddenException $exception) {
            $this->assertSame('198.51.100.26', $exception->getAuthorizedIp());
        }
    }

    /**
     * Build a minimal successful quote response.
     */
    private function successfulResponse(array $headers = []): Response
    {
        return new Response(200, $headers, json_encode([
            's' => 'ok',
            'symbol' => ['AAPL'],
            'last' => [150.0],
        ]));
    }
}
