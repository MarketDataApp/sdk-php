<?php

namespace MarketDataApp\Endpoints;

use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\MutualFunds\Candles;

class MutualFunds
{

    private Client $client;
    public const BASE_URL = "v1/funds/";

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function candles(): Candles
    {
        // Stub
        return new Candles();
    }
}
