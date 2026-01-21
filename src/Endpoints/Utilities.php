<?php

namespace MarketDataApp\Endpoints;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatus;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatusData;
use MarketDataApp\Endpoints\Responses\Utilities\Headers;
use MarketDataApp\Endpoints\Responses\Utilities\User;
use MarketDataApp\Enums\ApiStatusResult;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Settings;

/**
 * Utilities class for Market Data API.
 *
 * This class provides utility methods for checking API status and retrieving request headers.
 */
class Utilities
{

    /** @var Client The Market Data API client instance. */
    private Client $client;

    /** @var ApiStatusData Static singleton instance for API status caching. */
    private static ?ApiStatusData $apiStatusData = null;

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
     * Get the singleton ApiStatusData instance.
     *
     * @return ApiStatusData The singleton instance
     */
    public static function getApiStatusData(): ApiStatusData
    {
        if (self::$apiStatusData === null) {
            self::$apiStatusData = new ApiStatusData();
        }
        return self::$apiStatusData;
    }

    /**
     * Clear the API status cache (useful for testing).
     *
     * @return void
     */
    public static function clearApiStatusCache(): void
    {
        self::$apiStatusData = null;
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
     * Uses smart caching:
     * - If cache is fresh (< 4min30sec): Return cached data immediately, no async update
     * - If cache is in refresh window (4min30sec - 5min): Return cached data immediately AND trigger async refresh
     * - If cache is stale (> 5min): Block and fetch fresh data
     *
     * @return ApiStatus The current API status and historical uptime information.
     * @throws GuzzleException|ApiException
     */
    public function api_status(): ApiStatus
    {
        $apiStatusData = self::getApiStatusData();
        
        // If cache is fresh (< 4min30sec): Return immediately, no async update
        if ($apiStatusData->hasData()) {
            $cached = $apiStatusData->getCachedApiStatus();
            if ($cached !== null) {
                $lastRefreshed = $apiStatusData->getLastRefreshed();
                if ($lastRefreshed !== null) {
                    $age = Carbon::now()->diffInSeconds($lastRefreshed);
                    if ($age < Settings::REFRESH_API_STATUS_INTERVAL) {
                        return $cached;
                    }
                    
                    // If cache is in refresh window (4min30sec - 5min): Return immediately AND trigger async refresh
                    if ($age >= Settings::REFRESH_API_STATUS_INTERVAL && 
                        $age < Settings::API_STATUS_CACHE_VALIDITY) {
                        $apiStatusData->refreshAsync($this->client);
                        return $cached;
                    }
                }
            }
        }
        
        // If cache is stale (> 5min): Block and fetch fresh data
        if (!$apiStatusData->isValid()) {
            $apiStatusData->refresh($this->client, true);
            $cached = $apiStatusData->getCachedApiStatus();
            if ($cached !== null) {
                return $cached;
            }
        }
        
        // Fallback: fetch fresh data
        $response = $this->client->execute("status/");
        $apiStatusData->update($response);
        return new ApiStatus($response);
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

    /**
     * Get the status of a specific service.
     *
     * Checks if a specific service (e.g., "/v1/stocks/quotes/") is online, offline, or unknown.
     * Uses the same smart caching logic as api_status().
     *
     * @param string $service The service path to check (e.g., "/v1/stocks/quotes/").
     * @return ApiStatusResult The status result (ONLINE, OFFLINE, or UNKNOWN)
     * @throws GuzzleException|ApiException
     */
    public function getServiceStatus(string $service): ApiStatusResult
    {
        $apiStatusData = self::getApiStatusData();
        // Client extends ClientBase, so this works
        return $apiStatusData->getApiStatus($this->client, $service);
    }

    /**
     * Manually refresh the API status cache.
     *
     * @param bool $blocking Whether to wait for response (true) or trigger async refresh (false).
     * @return bool True on success, false on failure (only meaningful for blocking mode)
     * @throws GuzzleException|ApiException
     */
    public function refreshApiStatus(bool $blocking = false): bool
    {
        $apiStatusData = self::getApiStatusData();
        return $apiStatusData->refresh($this->client, $blocking);
    }
}
