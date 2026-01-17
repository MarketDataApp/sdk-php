<?php

namespace MarketDataApp\Exceptions;

/**
 * UnauthorizedException class
 *
 * This exception is raised for 401 UNAUTHORIZED HTTP errors.
 * This error means: The token supplied with the request is missing, invalid, or cannot be used.
 */
class UnauthorizedException extends BadStatusCodeError
{
    /**
     * UnauthorizedException constructor.
     *
     * @param string          $message  The exception message.
     * @param int             $code     The exception code (should be 401).
     * @param \Exception|null $previous The previous exception used for exception chaining.
     * @param mixed           $response The API response associated with this exception.
     */
    public function __construct($message = "", $code = 401, ?\Exception $previous = null, $response = null)
    {
        parent::__construct($message, $code, $previous, $response);
    }
}
