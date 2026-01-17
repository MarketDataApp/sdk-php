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
}
