<?php

namespace MarketDataApp\Tests\Traits;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

trait MockResponses
{

    private function setMockResponses(array $responses): void
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);

        $this->client->setGuzzle(new GuzzleClient(['handler' => $handlerStack]));
    }
}
