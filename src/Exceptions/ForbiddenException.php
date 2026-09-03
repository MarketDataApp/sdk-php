<?php

namespace MarketDataApp\Exceptions;

use Psr\Http\Message\ResponseInterface;

/**
 * Exception raised for 403 FORBIDDEN HTTP errors.
 *
 * The Market Data API may include X-API-Authorized-IP when access is blocked
 * because the caller's detected IP does not match the account's authorized IP.
 */
class ForbiddenException extends BadStatusCodeError
{
    /**
     * The IP address currently authorized for the account.
     *
     * @var string|null
     */
    public ?string $authorizedIp;

    /**
     * ForbiddenException constructor.
     *
     * @param string                 $message    The exception message.
     * @param int                    $code       The exception code (should be 403).
     * @param \Throwable|null        $previous   The previous exception used for exception chaining.
     * @param ResponseInterface|null $response   The HTTP response associated with this exception.
     * @param string|null            $requestUrl The URL that was requested when the error occurred.
     */
    public function __construct(
        string $message = "",
        int $code = 403,
        ?\Throwable $previous = null,
        ?ResponseInterface $response = null,
        ?string $requestUrl = null
    ) {
        $authorizedIp = $response?->getHeaderLine('X-API-Authorized-IP');
        $this->authorizedIp = $authorizedIp !== '' ? $authorizedIp : null;

        if ($this->authorizedIp !== null) {
            $message = rtrim($message);
            $message .= ($message !== '' ? ' ' : '') . 'Authorized IP: ' . $this->authorizedIp;
        }

        parent::__construct($message, $code, $previous, $response, $requestUrl);
    }

    /**
     * Get the IP address currently authorized for the account.
     */
    public function getAuthorizedIp(): ?string
    {
        return $this->authorizedIp;
    }
}
