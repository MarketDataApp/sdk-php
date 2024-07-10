<?php

namespace MarketDataApp\Endpoints;

use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Markets\Status;

class Markets
{

    private Client $client;
    public const BASE_URL = "v1/markets/";

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function status(): Status
    {
        // Stub
        return new Status();
    }
}
