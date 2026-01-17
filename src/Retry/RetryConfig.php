<?php

namespace MarketDataApp\Retry;

/**
 * Retry configuration constants matching the Python SDK.
 */
class RetryConfig
{
    /**
     * Maximum number of retry attempts.
     */
    public const MAX_RETRY_ATTEMPTS = 3;

    /**
     * Exponential backoff multiplier.
     */
    public const RETRY_BACKOFF = 0.5;

    /**
     * Minimum backoff time in seconds.
     */
    public const MIN_RETRY_BACKOFF = 0.5;

    /**
     * Maximum backoff time in seconds.
     */
    public const MAX_RETRY_BACKOFF = 5.0;

    /**
     * Check if a status code is retryable (status code > 500).
     *
     * @param int $statusCode The HTTP status code.
     *
     * @return bool True if the status code is retryable.
     */
    public static function isRetryableStatusCode(int $statusCode): bool
    {
        return $statusCode > 500;
    }
}
