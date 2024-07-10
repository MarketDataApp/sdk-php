<?php

namespace MarketDataApp\Endpoints;

use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Options\Expirations;
use MarketDataApp\Endpoints\Responses\Options\Lookup;
use MarketDataApp\Endpoints\Responses\Options\OptionChain;
use MarketDataApp\Endpoints\Responses\Options\Quotes;
use MarketDataApp\Endpoints\Responses\Options\Strikes;

class Options
{

    private Client $client;
    public const BASE_URL = "v1/options/";

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function expirations(): Expirations
    {
        // Stub
        return new Expirations();
    }

    public function lookup(): Lookup
    {
        // Stub
        return new Lookup();
    }

    public function strikes(): Strikes
    {
        // Stub
        return new Strikes();
    }

    public function option_chain(): OptionChain
    {
        // Stub
        return new OptionChain();
    }

    public function quotes(): Quotes
    {
        // Stub
        return new Quotes();
    }
}
