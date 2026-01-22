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

        // After retries are exhausted, RequestError is thrown (not GuzzleException)
        $this->expectException(\MarketDataApp\Exceptions\RequestError::class);
        $response = $this->client->stocks->quote("INVALID");
    }
}
