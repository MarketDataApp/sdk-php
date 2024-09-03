<?php

namespace MarketDataApp\Tests\Traits;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

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
    private function setMockResponses(array $responses): void
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);

        $this->client->setGuzzle(new GuzzleClient(['handler' => $handlerStack]));
    }
}
