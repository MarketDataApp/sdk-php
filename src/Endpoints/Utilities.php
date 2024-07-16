<?php

namespace MarketDataApp\Endpoints;

use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatus;
use MarketDataApp\Endpoints\Responses\Utilities\Headers;
use MarketDataApp\Exceptions\ApiException;

class Utilities
{

    private Client $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * Check the current status of Market Data services and historical uptime. The status of the Market Data API is
     * updated every 5 minutes. Historical uptime is available for the last 30 and 90 days.
     *
     * TIP: This endpoint will continue to respond with the current status of the Market Data API, even if the API is
     * offline. This endpoint is public and does not require a token.
     *
     * @throws GuzzleException|ApiException
     */
    public function api_status(): ApiStatus
    {
        return new ApiStatus($this->client->execute("status/"));
    }

    /**
     * This endpoint allows users to retrieve a JSON response of the headers their application is sending, aiding in
     * troubleshooting authentication issues, particularly with the Authorization header.
     *
     * TIP: The values in sensitive headers such as Authorization are partially redacted in the response for security
     * purposes.
     */
    public function headers(): Headers
    {
        return new Headers($this->client->execute("headers/"));
    }
}
