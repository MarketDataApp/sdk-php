<?php

namespace MarketDataApp\Exceptions;

use Psr\Http\Message\ResponseInterface;

/**
 * Base exception class for all Market Data SDK exceptions.
 *
 * This class provides common functionality for storing and retrieving
 * request context including the request ID (cf-ray header), request URL,
 * timestamp, and HTTP response. All SDK exception classes extend from this base.
 */
class MarketDataException extends \Exception
{
    /**
     * @var ResponseInterface|null The HTTP response associated with this exception.
     */
    protected ?ResponseInterface $response;

    /**
     * @var string|null The Cloudflare request ID (cf-ray header) for support tickets.
     */
    protected ?string $requestId;

    /**
     * @var string|null The URL that was requested when the error occurred.
     */
    protected ?string $requestUrl;

    /**
     * @var \DateTimeImmutable The timestamp when the exception occurred (stored in UTC).
     */
    protected \DateTimeImmutable $timestamp;

    /**
     * MarketDataException constructor.
     *
     * @param string              $message    The exception message.
     * @param int                 $code       The exception code.
     * @param \Throwable|null     $previous   The previous exception used for exception chaining.
     * @param ResponseInterface|null $response   The HTTP response associated with this exception.
     * @param string|null         $requestUrl The URL that was requested when the error occurred.
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null,
        ?ResponseInterface $response = null,
        ?string $requestUrl = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
        $this->requestUrl = $requestUrl;
        $this->requestId = $this->extractRequestId($response);
        $this->timestamp = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Extract the request ID (cf-ray header) from the response.
     *
     * @param ResponseInterface|null $response The HTTP response.
     *
     * @return string|null The request ID, or null if not available.
     */
    protected function extractRequestId(?ResponseInterface $response): ?string
    {
        if ($response === null) {
            return null;
        }

        $cfRay = $response->getHeaderLine('cf-ray');
        return $cfRay !== '' ? $cfRay : null;
    }

    /**
     * Get the HTTP response associated with this exception.
     *
     * @return ResponseInterface|null The HTTP response.
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * Get the Cloudflare request ID for support tickets.
     *
     * This ID can be provided to Market Data support to help identify
     * the specific request that failed.
     *
     * @return string|null The request ID, or null if not available.
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Get the URL that was requested when the error occurred.
     *
     * @return string|null The request URL, or null if not available.
     */
    public function getRequestUrl(): ?string
    {
        return $this->requestUrl;
    }

    /**
     * Get the timestamp when the exception occurred.
     *
     * The timestamp is stored in UTC. Convert to your preferred timezone as needed:
     * ```php
     * $localTime = $e->getTimestamp()->setTimezone(new \DateTimeZone('America/Los_Angeles'));
     * ```
     *
     * @return \DateTimeImmutable The timestamp when the exception was created (UTC).
     */
    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    /**
     * Get all support ticket context as an associative array.
     *
     * This is useful for structured logging (JSON, log aggregation systems)
     * or when you need to process the error details programmatically.
     *
     * Example:
     * ```php
     * catch (MarketDataException $e) {
     *     $logger->error('API Error', $e->getSupportContext());
     * }
     * ```
     *
     * @return array{
     *     timestamp: string,
     *     request_id: string|null,
     *     url: string|null,
     *     http_code: int,
     *     message: string,
     *     exception_type: string
     * }
     */
    public function getSupportContext(): array
    {
        // Convert to America/New_York for support tickets (matches API logs)
        $supportTimestamp = $this->timestamp->setTimezone(new \DateTimeZone('America/New_York'));

        return [
            'timestamp' => $supportTimestamp->format('c'),
            'request_id' => $this->requestId,
            'url' => $this->requestUrl,
            'http_code' => $this->getCode(),
            'message' => $this->getMessage(),
            'exception_type' => static::class,
        ];
    }

    /**
     * Get a pre-formatted string with all information needed for a support ticket.
     *
     * Copy and paste this output directly into your support request at
     * support@marketdata.app or in the customer dashboard.
     *
     * Example:
     * ```php
     * catch (MarketDataException $e) {
     *     echo $e->getSupportInfo();
     * }
     * ```
     *
     * @return string Formatted support ticket information.
     */
    public function getSupportInfo(): string
    {
        // Convert to America/New_York for support tickets (matches API logs)
        $supportTimestamp = $this->timestamp->setTimezone(new \DateTimeZone('America/New_York'));

        $lines = [
            "--- MARKET DATA SUPPORT INFO ---",
            "Timestamp:    " . $supportTimestamp->format('Y-m-d H:i:s T'),
            "Request ID:   " . ($this->requestId ?? 'N/A'),
            "URL:          " . ($this->requestUrl ?? 'N/A'),
            "HTTP Code:    " . $this->getCode(),
            "Error:        " . $this->getMessage(),
            "--------------------------------",
        ];

        return implode("\n", $lines);
    }

    /**
     * Get string representation of the exception.
     *
     * Includes the standard exception information plus request context
     * (timestamp, request ID, and URL) when available.
     *
     * @return string
     */
    public function __toString(): string
    {
        $parts = [parent::__toString()];

        $parts[] = "Timestamp: " . $this->timestamp->format('c');
        if ($this->requestId !== null) {
            $parts[] = "Request ID: {$this->requestId}";
        }
        if ($this->requestUrl !== null) {
            $parts[] = "URL: {$this->requestUrl}";
        }

        return implode("\n", $parts);
    }
}
