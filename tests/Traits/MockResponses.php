<?php

namespace MarketDataApp\Tests\Traits;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Trait for setting up mock responses in HTTP client tests.
 */
trait MockResponses
{

    /**
     * Set mock responses for the HTTP client.
     *
     * This method creates a new GuzzleHttp client with a mock handler
     * and sets it on the current client instance.
     *
     * @param array $responses An array of mock responses to be returned by the client.
     *
     * @return void
     */
    protected function setMockResponses(array $responses): void
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);

        $this->client->setGuzzle(new GuzzleClient(['handler' => $handlerStack]));
    }

    /**
     * Get a mocked response for the /user/ endpoint.
     *
     * This helper method provides a standard mock response for the /user/ endpoint
     * with valid rate limit headers.
     *
     * @return Response A mocked response for the /user/ endpoint.
     */
    protected function getMockedUserEndpointResponse(): Response
    {
        $resetTimestamp = time() + 3600;
        return new Response(200, [
            'x-api-ratelimit-limit'     => ['100'],
            'x-api-ratelimit-remaining' => ['99'],
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['1'],
        ], json_encode([]));
    }

    /**
     * Clear MARKETDATA_TOKEN environment variable to ensure empty token is used.
     *
     * This method clears the token from all possible locations where it might be set:
     * - putenv()
     * - $_ENV superglobal
     * - $_SERVER superglobal
     *
     * This prevents real API calls during Client construction in unit tests by ensuring
     * that an empty token is used, which causes _setup_rate_limits() to skip the /user/
     * endpoint validation call.
     *
     * @return void
     */
    protected function clearMarketDataToken(): void
    {
        // Clear putenv
        putenv('MARKETDATA_TOKEN');
        
        // Clear $_ENV
        unset($_ENV['MARKETDATA_TOKEN']);
        
        // Clear $_SERVER
        unset($_SERVER['MARKETDATA_TOKEN']);
    }
}
