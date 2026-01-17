<?php

namespace MarketDataApp\Endpoints;

use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatus;
use MarketDataApp\Endpoints\Responses\Utilities\Headers;
use MarketDataApp\Endpoints\Responses\Utilities\User;
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

    /**
     * Retrieve rate limit information for the current user.
     *
     * This endpoint returns rate limit information from response headers, including:
     * - The maximum number of credits permitted (per day for Free/Starter/Trader plans or per minute for Prime users)
     * - The number of credits remaining in the current rate period
     * - The quantity of credits consumed in the current request (not cumulative)
     * - When the current rate limit window resets (UTC epoch seconds)
     *
     * Note: Rate limits track credits, not requests. Most requests consume 1 credit,
     * but bulk requests or options requests may consume multiple credits.
     *
     * @return User The user/rate limit information.
     * @throws GuzzleException|ApiException
     */
    public function user(): User
    {
        $response = $this->client->makeRawRequest("user/");
        
        // Validate response status code
        $this->client->validateResponseStatusCode($response, true);
        
        // Extract rate limits from response headers
        $rateLimits = $this->client->extractRateLimitsFromResponse($response);
        
        if ($rateLimits === null) {
            throw new ApiException("Rate limit headers not found in response", 0, null, $response);
        }
        
        return new User($rateLimits);
    }
}
