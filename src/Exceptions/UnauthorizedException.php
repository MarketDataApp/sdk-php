<?php

namespace MarketDataApp\Exceptions;

use Psr\Http\Message\ResponseInterface;

/**
 * UnauthorizedException class
 *
 * This exception is raised for 401 UNAUTHORIZED HTTP errors.
 * This error means: The token supplied with the request is missing, invalid, or cannot be used.
 *
 * @method string getSupportInfo() Get pre-formatted support ticket information.
 * @method array  getSupportContext() Get support context as an associative array.
 */
class UnauthorizedException extends BadStatusCodeError
{
    /**
     * UnauthorizedException constructor.
     *
     * @param string                 $message    The exception message.
     * @param int                    $code       The exception code (should be 401).
     * @param \Throwable|null        $previous   The previous exception used for exception chaining.
     * @param ResponseInterface|null $response   The HTTP response associated with this exception.
     * @param string|null            $requestUrl The URL that was requested when the error occurred.
     */
    public function __construct(
        string $message = "",
        int $code = 401,
        ?\Throwable $previous = null,
        ?ResponseInterface $response = null,
        ?string $requestUrl = null
    ) {
        parent::__construct($message, $code, $previous, $response, $requestUrl);
    }
}
