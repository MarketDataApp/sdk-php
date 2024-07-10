<?php

namespace MarketDataApp\Endpoints;

use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatus;
use MarketDataApp\Endpoints\Responses\Utilities\Headers;

class Utilities
{

    private Client $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function api_status(): ApiStatus
    {
        // Stub
        return new ApiStatus();
    }

    public function headers(): Headers
    {
        // Stub
        return new Headers();
    }
}
