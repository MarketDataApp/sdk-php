<?php

namespace MarketDataApp\Endpoints\Responses\Utilities;

use MarketDataApp\RateLimits;

/**
 * Represents user/rate limit information from the API.
 *
 * This class wraps the RateLimits value object to provide rate limit information
 * retrieved from the /user/ endpoint.
 */
class User
{

    /**
     * The rate limit information.
     *
     * @var RateLimits
     */
    public RateLimits $rate_limits;

    /**
     * User constructor.
     *
     * @param RateLimits $rateLimits The rate limit information extracted from response headers.
     */
    public function __construct(RateLimits $rateLimits)
    {
        $this->rate_limits = $rateLimits;
    }

    /**
     * Returns a string representation of the user info.
     *
     * @return string Human-readable user/rate limit information.
     */
    public function __toString(): string
    {
        return "User: " . (string) $this->rate_limits;
    }
}
