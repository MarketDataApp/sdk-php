<?php

namespace MarketDataApp;

use Carbon\Carbon;

/**
 * Represents rate limit information from API responses.
 *
 * This value object holds rate limit data extracted from response headers.
 * It will be reused by both the User response class and future automatic rate limit tracking.
 */
class RateLimits
{

    /**
     * Total number of requests allowed in the current rate limit window.
     *
     * @var int
     */
    public int $requests_limit;

    /**
     * Number of requests remaining in the current rate limit window.
     *
     * @var int
     */
    public int $requests_remaining;

    /**
     * Unix timestamp when the rate limit resets.
     *
     * @var Carbon
     */
    public Carbon $requests_reset;

    /**
     * Number of requests consumed in the current request.
     *
     * According to API documentation: "The quantity of requests that were consumed
     * in the current request." This is NOT a cumulative count - it's the quantity
     * consumed for the specific request that returned these headers.
     *
     * @var int
     */
    public int $requests_consumed;

    /**
     * RateLimits constructor.
     *
     * @param int    $requests_limit     Total number of requests allowed.
     * @param int    $requests_remaining Number of requests remaining.
     * @param Carbon $requests_reset     Timestamp when rate limit resets.
     * @param int    $requests_consumed  Number of requests consumed.
     */
    public function __construct(
        int $requests_limit,
        int $requests_remaining,
        Carbon $requests_reset,
        int $requests_consumed
    ) {
        $this->requests_limit = $requests_limit;
        $this->requests_remaining = $requests_remaining;
        $this->requests_reset = $requests_reset;
        $this->requests_consumed = $requests_consumed;
    }
}
