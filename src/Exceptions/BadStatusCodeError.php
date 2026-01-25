<?php

namespace MarketDataApp\Exceptions;

use Psr\Http\Message\ResponseInterface;

/**
 * BadStatusCodeError class
 *
 * This exception is raised for permanent HTTP errors (4xx)
 * that should not trigger retry logic.
 *
 * @method string getSupportInfo() Get pre-formatted support ticket information.
 * @method array  getSupportContext() Get support context as an associative array.
 */
class BadStatusCodeError extends MarketDataException
{
    /**
     * BadStatusCodeError constructor.
     *
     * @param string                 $message    The exception message.
     * @param int                    $code       The exception code.
     * @param \Throwable|null        $previous   The previous exception used for exception chaining.
     * @param ResponseInterface|null $response   The HTTP response associated with this exception.
     * @param string|null            $requestUrl The URL that was requested when the error occurred.
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null,
        ?ResponseInterface $response = null,
        ?string $requestUrl = null
    ) {
        parent::__construct($message, $code, $previous, $response, $requestUrl);
    }
}
