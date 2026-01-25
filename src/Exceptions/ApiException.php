<?php

namespace MarketDataApp\Exceptions;

use Psr\Http\Message\ResponseInterface;

/**
 * ApiException class
 *
 * This exception is thrown when an API error occurs (business logic errors like
 * "no data found" or invalid symbol). It extends the base MarketDataException class
 * and provides access to request context for debugging and support.
 *
 * @method string getSupportInfo() Get pre-formatted support ticket information.
 * @method array  getSupportContext() Get support context as an associative array.
 */
class ApiException extends MarketDataException
{
    /**
     * ApiException constructor.
     *
     * @param string                 $message    The exception message.
     * @param int                    $code       The exception code.
     * @param \Throwable|null        $previous   The previous exception used for exception chaining.
     * @param ResponseInterface|null $response   The HTTP response associated with this exception.
     * @param string|null            $requestUrl The URL that was requested when the error occurred.
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        ?ResponseInterface $response = null,
        ?string $requestUrl = null
    ) {
        parent::__construct($message, $code, $previous, $response, $requestUrl);
    }
}
