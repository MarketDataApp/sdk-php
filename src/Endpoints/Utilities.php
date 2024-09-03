<?php

namespace MarketDataApp\Endpoints;

use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatus;
use MarketDataApp\Endpoints\Responses\Utilities\Headers;
use MarketDataApp\Exceptions\ApiException;

/**
 * Utilities class for Market Data API.
 *
 * This class provides utility methods for checking API status and retrieving request headers.
 */
class Utilities
{

    /** @var Client The Market Data API client instance. */
    private Client $client;

    /**
     * Utilities constructor.
     *
     * @param Client $client The Market Data API client instance.
     */
    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * Check the current status of Market Data services.
     *
     * Check the current status of Market Data services and historical uptime. The status of the Market Data API is
     * updated every 5 minutes. Historical uptime is available for the last 30 and 90 days.
     *
     * TIP: This endpoint will continue to respond with the current status of the Market Data API, even if the API is
     * offline. This endpoint is public and does not require a token.
     *
     * @return ApiStatus The current API status and historical uptime information.
     * @throws GuzzleException|ApiException
     */
    public function api_status(): ApiStatus
    {
        return new ApiStatus($this->client->execute("status/"));
    }

    /**
     * Retrieve the headers sent by the application.
     *
     * This endpoint allows users to retrieve a JSON response of the headers their application is sending, aiding in
     * troubleshooting authentication issues, particularly with the Authorization header.
     *
     * TIP: The values in sensitive headers such as Authorization are partially redacted in the response for security
     * purposes.
     *
     * @return Headers The headers sent in the request.
     * @throws GuzzleException|ApiException
     */
    public function headers(): Headers
    {
        return new Headers($this->client->execute("headers/"));
    }
}
