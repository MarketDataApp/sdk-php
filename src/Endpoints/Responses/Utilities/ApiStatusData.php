<?php

namespace MarketDataApp\Endpoints\Responses\Utilities;

use Carbon\Carbon;
use GuzzleHttp\Promise\PromiseInterface;
use MarketDataApp\Client;
use MarketDataApp\Enums\ApiStatusResult;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Exceptions\BadStatusCodeError;
use MarketDataApp\Exceptions\RequestError;
use MarketDataApp\Settings;

/**
 * Manages API status caching and provides service status checking.
 *
 * This class implements smart caching with:
 * - Cache validity: 5 minutes
 * - Refresh trigger window: 4 minutes 30 seconds to 5 minutes
 * - Async refresh in refresh window
 * - Blocking refresh when cache is stale
 */
class ApiStatusData
{
    /** @var array Service names array */
    private array $service = [];

    /** @var array Status strings array */
    private array $status = [];

    /** @var array Boolean online status array */
    private array $online = [];

    /** @var array 30-day uptime percentages */
    private array $uptimePct30d = [];

    /** @var array 90-day uptime percentages */
    private array $uptimePct90d = [];

    /** @var array Timestamp array */
    private array $updated = [];

    /** @var Carbon|null When cache was last refreshed */
    private ?Carbon $lastRefreshed = null;

    /** @var PromiseInterface|null Async refresh promise (to prevent duplicate refreshes) */
    private ?PromiseInterface $refreshPromise = null;

    /**
     * Update internal state from API response.
     *
     * @param object $data The raw response object containing API status information.
     * @return void
     * @throws \InvalidArgumentException If required fields are missing
     */
    public function update(object $data): void
    {
        if (!isset($data->service) || !is_array($data->service)) {
            throw new \InvalidArgumentException("Invalid status data: service field is missing or not an array");
        }
        if (!isset($data->status) || !is_array($data->status)) {
            throw new \InvalidArgumentException("Invalid status data: status field is missing or not an array");
        }
        if (!isset($data->{'uptimePct30d'}) || !is_array($data->{'uptimePct30d'})) {
            throw new \InvalidArgumentException("Invalid status data: uptimePct30d field is missing or not an array");
        }
        if (!isset($data->{'uptimePct90d'}) || !is_array($data->{'uptimePct90d'})) {
            throw new \InvalidArgumentException("Invalid status data: uptimePct90d field is missing or not an array");
        }
        if (!isset($data->updated) || !is_array($data->updated)) {
            throw new \InvalidArgumentException("Invalid status data: updated field is missing or not an array");
        }

        $this->service = $data->service;
        $this->status = $data->status;
        
        // Handle online field - default to true if missing for backward compatibility
        if (isset($data->online) && is_array($data->online)) {
            $this->online = array_map('boolval', $data->online);
        } else {
            // Default all services to online=true for backward compatibility
            $this->online = array_fill(0, count($this->service), true);
        }
        
        $this->uptimePct30d = $data->{'uptimePct30d'};
        $this->uptimePct90d = $data->{'uptimePct90d'};
        $this->updated = $data->updated;
        $this->lastRefreshed = Carbon::now();
    }

    /**
     * Check if cache is still valid (within 5 minutes).
     *
     * @return bool True if cache is valid, false otherwise
     */
    public function isValid(): bool
    {
        if ($this->lastRefreshed === null) {
            return false;
        }

        $age = Carbon::now()->diffInSeconds($this->lastRefreshed);
        return $age < Settings::API_STATUS_CACHE_VALIDITY;
    }

    /**
     * Check if cache is in refresh window (4min30sec - 5min).
     *
     * @return bool True if cache is in refresh window, false otherwise
     */
    public function inRefreshWindow(): bool
    {
        if ($this->lastRefreshed === null) {
            return false;
        }

        $age = Carbon::now()->diffInSeconds($this->lastRefreshed);
        return $age >= Settings::REFRESH_API_STATUS_INTERVAL && $age < Settings::API_STATUS_CACHE_VALIDITY;
    }

    /**
     * Fetch fresh status from API.
     *
     * @param Client $client The API client instance.
     * @param bool $blocking Whether to wait for response (true) or trigger async refresh (false).
     * @return bool True on success, false on failure (only meaningful for blocking mode)
     */
    public function refresh(Client $client, bool $blocking = false): bool
    {
        if ($blocking) {
            return $this->refreshBlocking($client);
        } else {
            $this->refreshAsync($client);
            // Return true if we have cache, false if no cache
            return $this->lastRefreshed !== null;
        }
    }

    /**
     * Blocking refresh - wait for response.
     *
     * @param Client $client The API client instance.
     * @return bool True on success, false on failure
     */
    private function refreshBlocking(Client $client): bool
    {
        try {
            $response = $client->execute("status/");
            $this->update($response);
            return true;
        } catch (\Exception $e) {
            // If we have existing cache, don't overwrite it
            if ($this->lastRefreshed !== null) {
                return false;
            }
            // If no cache exists, re-throw the exception
            throw $e;
        }
    }

    /**
     * Trigger non-blocking async refresh.
     *
     * @param Client $client The API client instance.
     * @return void
     */
    public function refreshAsync(Client $client): void
    {
        // Prevent duplicate concurrent refreshes
        if ($this->refreshPromise !== null) {
            return;
        }

        // Use reflection to access protected methods for async request
        $reflection = new \ReflectionClass($client);
        $parentClass = $reflection->getParentClass();
        
        $guzzleProperty = $parentClass->getProperty('guzzle');
        $guzzleClient = $guzzleProperty->getValue($client);
        
        $headersMethod = $parentClass->getMethod('headers');
        $headers = $headersMethod->invoke($client, 'json');
        
        $this->refreshPromise = $guzzleClient->getAsync("status/", [
            'headers' => $headers,
        ])->then(
            function ($response) use ($client, $parentClass) {
                try {
                    // Validate response status code
                    $validateMethod = $parentClass->getMethod('validateResponseStatusCode');
                    $validateMethod->invoke($client, $response, true);
                    
                    // Process response
                    $jsonResponse = (string)$response->getBody();
                    $objectResponse = json_decode($jsonResponse);

                    if (isset($objectResponse->s) && $objectResponse->s === 'error') {
                        throw new ApiException(message: $objectResponse->errmsg, response: $response);
                    }

                    // Only update cache on successful response
                    $this->update($objectResponse);
                } catch (\Exception $e) {
                    // Silently fail - don't update cache if refresh fails
                    // Existing cache remains valid
                } finally {
                    $this->refreshPromise = null;
                }
            },
            function ($reason) {
                // Silently fail - don't update cache if refresh fails
                // Existing cache remains valid
                $this->refreshPromise = null;
            }
        );
    }

    /**
     * Get status for specific service.
     *
     * @param Client $client The API client instance.
     * @param string $service The service path to check (e.g., "/v1/stocks/quotes/").
     * @return ApiStatusResult The status result (ONLINE, OFFLINE, or UNKNOWN)
     */
    public function getApiStatus(Client $client, string $service): ApiStatusResult
    {
        // If cache is fresh (< 4min30sec): Return immediately, no async update
        if ($this->lastRefreshed !== null) {
            $age = Carbon::now()->diffInSeconds($this->lastRefreshed);
            if ($age < Settings::REFRESH_API_STATUS_INTERVAL) {
                return $this->getServiceStatus($service);
            }

            // If cache is in refresh window (4min30sec - 5min): Return immediately AND trigger async refresh
            if ($age >= Settings::REFRESH_API_STATUS_INTERVAL && $age < Settings::API_STATUS_CACHE_VALIDITY) {
                $this->refreshAsync($client);
                return $this->getServiceStatus($service);
            }
        }

        // If cache is stale (> 5min): Block and wait for fresh data
        if (!$this->isValid()) {
            $this->refresh($client, true);
            return $this->getServiceStatus($service);
        }

        return $this->getServiceStatus($service);
    }

    /**
     * Get status for a specific service from cached data.
     *
     * @param string $service The service path to check.
     * @return ApiStatusResult The status result
     */
    private function getServiceStatus(string $service): ApiStatusResult
    {
        if (empty($this->service)) {
            return ApiStatusResult::UNKNOWN;
        }

        $serviceIndex = array_search($service, $this->service);
        if ($serviceIndex === false) {
            return ApiStatusResult::UNKNOWN;
        }

        // Check if service is offline based on status field or online field
        if (isset($this->status[$serviceIndex]) && $this->status[$serviceIndex] === ApiStatusResult::OFFLINE->value) {
            return ApiStatusResult::OFFLINE;
        }

        // Check online boolean field
        if (isset($this->online[$serviceIndex]) && !$this->online[$serviceIndex]) {
            return ApiStatusResult::OFFLINE;
        }

        // If status is online and online field is true, service is online
        if (isset($this->status[$serviceIndex]) && $this->status[$serviceIndex] === ApiStatusResult::ONLINE->value) {
            if (isset($this->online[$serviceIndex]) && $this->online[$serviceIndex]) {
                return ApiStatusResult::ONLINE;
            }
            // Status says online but online field is false - treat as offline
            return ApiStatusResult::OFFLINE;
        }

        // Default to unknown if we can't determine
        return ApiStatusResult::UNKNOWN;
    }

    /**
     * Get last refresh timestamp.
     *
     * @return Carbon|null The last refresh timestamp, or null if never refreshed
     */
    public function getLastRefreshed(): ?Carbon
    {
        return $this->lastRefreshed;
    }

    /**
     * Check if cache has data.
     *
     * @return bool True if cache has data, false otherwise
     */
    public function hasData(): bool
    {
        return !empty($this->service) && $this->lastRefreshed !== null;
    }

    /**
     * Get cached ApiStatus object.
     *
     * @return ApiStatus|null The cached ApiStatus object, or null if no cache
     */
    public function getCachedApiStatus(): ?ApiStatus
    {
        if (!$this->hasData()) {
            return null;
        }

        // Reconstruct response object from cache
        $response = (object)[
            's' => 'ok',
            'service' => $this->service,
            'status' => $this->status,
            'online' => $this->online,
            'uptimePct30d' => $this->uptimePct30d,
            'uptimePct90d' => $this->uptimePct90d,
            'updated' => $this->updated,
        ];

        return new ApiStatus($response);
    }
}
