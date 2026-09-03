<?php

namespace MarketDataApp\Tests\Unit\Stocks;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

/**
 * Test case for exception handling in Stocks endpoints.
 */
class ExceptionHandlingTest extends StocksTestCase
{
    /**
     * Test exception handling for GuzzleException.
     *
     * RequestException is retryable, so we need to provide enough mock responses
     * to exhaust retries (3 attempts total).
     *
     * @return void
     */
    public function testExceptionHandling_throwsGuzzleException()
    {
        $this->setMockResponses([
            new RequestException("Error Communicating with Server", new Request('GET', 'test')),
            new RequestException("Error Communicating with Server", new Request('GET', 'test')),
            new RequestException("Error Communicating with Server", new Request('GET', 'test')),
        ]);

        try {
            $this->client->stocks->quote("INVALID");
            $this->fail('Expected retries to end in a RequestError');
        } catch (\MarketDataApp\Exceptions\RequestError $exception) {
            $this->assertSame(
                'Request failed: Error Communicating with Server',
                $exception->getMessage()
            );
            $this->assertNull($exception->getResponse());
            $this->assertInstanceOf(RequestException::class, $exception->getPrevious());
        }
    }
}
