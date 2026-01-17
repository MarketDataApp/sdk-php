<?php

namespace MarketDataApp;

use Carbon\Carbon;

/**
 * Represents rate limit information from API responses.
 *
 * This value object holds rate limit data extracted from response headers.
 * It will be reused by both the User response class and future automatic rate limit tracking.
 *
 * Property names match the API header names (x-api-ratelimit-*):
 * - limit: Total credits allowed (from x-api-ratelimit-limit)
 * - remaining: Credits remaining (from x-api-ratelimit-remaining)
 * - reset: When rate limit resets (from x-api-ratelimit-reset)
 * - consumed: Credits consumed in current request (from x-api-ratelimit-consumed)
 */
class RateLimits
{

    /**
     * Total number of credits allowed in the current rate limit window.
     *
     * Extracted from x-api-ratelimit-limit header.
     *
     * @var int
     */
    public int $limit;

    /**
     * Number of credits remaining in the current rate limit window.
     *
     * Extracted from x-api-ratelimit-remaining header.
     *
     * @var int
     */
    public int $remaining;

    /**
     * Unix timestamp when the rate limit resets.
     *
     * Extracted from x-api-ratelimit-reset header.
     *
     * @var Carbon
     */
    public Carbon $reset;

    /**
     * Number of credits consumed in the current request.
     *
     * Extracted from x-api-ratelimit-consumed header.
     *
     * According to API documentation: "The quantity of credits that were consumed
     * in the current request." This is NOT a cumulative count - it's the quantity
     * consumed for the specific request that returned these headers.
     *
     * Note: Most requests consume 1 credit, but bulk requests or options requests
     * may consume multiple credits per request.
     *
     * @var int
     */
    public int $consumed;

    /**
     * RateLimits constructor.
     *
     * @param int    $limit     Total number of credits allowed.
     * @param int    $remaining Number of credits remaining.
     * @param Carbon $reset     Timestamp when rate limit resets.
     * @param int    $consumed  Number of credits consumed.
     */
    public function __construct(
        int $limit,
        int $remaining,
        Carbon $reset,
        int $consumed
    ) {
        $this->limit = $limit;
        $this->remaining = $remaining;
        $this->reset = $reset;
        $this->consumed = $consumed;
    }
}
