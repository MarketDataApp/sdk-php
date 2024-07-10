<?php

namespace MarketDataApp\Tests;

use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Markets\Status;
use PHPUnit\Framework\TestCase;

class MarketsTest extends TestCase
{

    private Client $client;

    protected function setUp(): void
    {
        $token = "your_api_token";
        $client = new Client($token);
        $this->client = $client;
    }

    public function testStatus_success()
    {
        // Stub
        $this->assertInstanceOf(Status::class, $this->client->markets->status());
    }
}
