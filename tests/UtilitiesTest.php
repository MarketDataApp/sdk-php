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
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatus;
use MarketDataApp\Endpoints\Responses\Utilities\Headers;
use PHPUnit\Framework\TestCase;

class UtilitiesTest extends TestCase
{

    private Client $client;

    protected function setUp(): void
    {
        $token = "your_api_token";
        $client = new Client($token);
        $this->client = $client;
    }

    public function testApiStatus_success()
    {
        // Stub
        $this->assertInstanceOf(ApiStatus::class, $this->client->utilities->api_status());
    }

    public function testApiStatus_headers()
    {
        // Stub
        $this->assertInstanceOf(Headers::class, $this->client->utilities->headers());
    }
}
