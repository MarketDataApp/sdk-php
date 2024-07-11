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
use MarketDataApp\Endpoints\Responses\Options\Expirations;
use MarketDataApp\Endpoints\Responses\Options\Lookup;
use MarketDataApp\Endpoints\Responses\Options\OptionChain;
use MarketDataApp\Endpoints\Responses\Options\Quotes;
use MarketDataApp\Endpoints\Responses\Options\Strikes;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{

    use MockResponses;

    private Client $client;

    protected function setUp(): void
    {
        $token = "your_api_token";
        $client = new Client($token);
        $this->client = $client;
    }

    public function testExpirations_success()
    {
        // Stub
        $this->assertInstanceOf(Expirations::class, $this->client->options->expirations());
    }

    public function testLookup_success()
    {
        // Stub
        $this->assertInstanceOf(Lookup::class, $this->client->options->lookup());
    }

    public function testOptionChain_success()
    {
        // Stub
        $this->assertInstanceOf(OptionChain::class, $this->client->options->option_chain());
    }

    public function testQuotes_success()
    {
        // Stub
        $this->assertInstanceOf(Quotes::class, $this->client->options->quotes());
    }

    public function testStrikes_success()
    {
        // Stub
        $this->assertInstanceOf(Strikes::class, $this->client->options->strikes());
    }
}
