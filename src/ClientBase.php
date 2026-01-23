<?php

namespace MarketDataApp;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\EachPromise;
use GuzzleHttp\Promise\PromiseInterface;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatusData;
use MarketDataApp\Endpoints\Utilities;
use MarketDataApp\Enums\ApiStatusResult;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Exceptions\BadStatusCodeError;
use MarketDataApp\Exceptions\RequestError;
use MarketDataApp\Exceptions\UnauthorizedException;
use MarketDataApp\Logging\LoggerFactory;
use MarketDataApp\Logging\LoggingUtilities;
use MarketDataApp\Retry\RetryConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for Market Data API client.
 *
 * This class provides core functionality for API communication,
 * including parallel execution, async requests, and response handling.
 */
abstract class ClientBase
{

    /**
     * The base URL for the Market Data API.
     */
    public const API_URL = "https://api.marketdata.app/";

    /**
     * The host for the Market Data API.
     */
    public const API_HOST = "api.marketdata.app";

    /**
     * SDK version for User-Agent header.
     */
    public const VERSION = '0.8.0';

    /**
     * @var GuzzleClient The Guzzle HTTP client instance.
     */
    protected GuzzleClient $guzzle;

    /**
     * @var string The API token for authentication.
     */
    protected string $token;

    /**
     * @var RateLimits|null Current rate limit information, automatically updated after each request.
     *                       Tracks credits (not requests), as some requests may consume multiple credits.
     */
    public ?RateLimits $rate_limits = null;

    /**
     * @var Parameters Default universal parameters for all API requests.
     *                 Can be modified programmatically: $client->default_params->format = Format::CSV;
     *                 Method-level parameters override these defaults.
     */
    public Parameters $default_params;

    /**
     * @var LoggerInterface PSR-3 logger instance for request logging.
     */
    public LoggerInterface $logger;

    /**
     * ClientBase constructor.
     *
     * @param string|null          $token  The API token for authentication. If not provided, the token will be
     *                                     automatically resolved from MARKETDATA_TOKEN environment variable or .env file.
     *                                     An empty string is allowed for accessing free symbols like AAPL.
     *                                     A valid token is required for authenticated endpoints. An invalid token will throw
     *                                     UnauthorizedException during construction.
     * @param LoggerInterface|null $logger PSR-3 logger instance. If not provided, uses the default logger.
     *
     * @throws UnauthorizedException If the token is invalid (non-empty but returns 401 from /user endpoint)
     */
    public function __construct(?string $token = null, ?LoggerInterface $logger = null)
    {
        $this->guzzle = new GuzzleClient(['base_uri' => self::API_URL]);
        $this->token = Settings::getToken($token);
        $this->default_params = Settings::getDefaultParameters();
        $this->logger = $logger ?? LoggerFactory::getLogger();
        $this->_setup_rate_limits();
    }

    /**
     * Set a custom Guzzle client.
     *
     * @param GuzzleClient $guzzleClient The Guzzle client to use.
     */
    public function setGuzzle(GuzzleClient $guzzleClient): void
    {
        $this->guzzle = $guzzleClient;
    }

    /**
     * Set up initial rate limits by fetching from the /user/ endpoint.
     *
     * This method is called during client construction to initialize rate limit
     * information. If the request fails, rate_limits will remain null until the
     * first successful request with rate limit headers.
     *
     * Rate limits track credits, not requests. Most requests consume 1 credit,
     * but bulk requests or options requests may consume multiple credits.
     *
     * If the token is empty, validation is skipped to allow free symbols like AAPL.
     * If the token is invalid (returns 401), an UnauthorizedException is thrown
     * to prevent client creation.
     *
     * @return void
     * @throws UnauthorizedException If the token is invalid (non-empty but returns 401)
     */
    protected function _setup_rate_limits(): void
    {
        // Skip validation for empty token (allows free symbols like AAPL)
        if ($this->token === '') {
            return;
        }
        
        try {
            $response = $this->makeRawRequest("user/");
            $this->validateResponseStatusCode($response, true);
            
            $rateLimits = $this->extractRateLimitsFromResponse($response);
            if ($rateLimits !== null) {
                $this->rate_limits = $rateLimits;
            }
        } catch (UnauthorizedException $e) {
            // Invalid token - re-throw to prevent client creation
            throw $e;
        } catch (\Exception $e) {
            // Gracefully handle other errors (network, timeouts, etc.)
            // rate_limits will remain null and will be populated on first successful request
        }
    }

    /**
     * Execute multiple API calls in parallel with concurrency limiting.
     *
     * Uses Guzzle's EachPromise to maintain a sliding window of concurrent requests.
     * Unlike batch processing, this approach starts new requests as soon as previous
     * ones complete, maintaining optimal throughput up to MAX_CONCURRENT_REQUESTS (50).
     *
     * @param array $calls An array of method calls, each containing the method name and arguments.
     *
     * @return array An array of decoded JSON responses in the same order as input calls.
     * @throws \Throwable
     */
    public function execute_in_parallel(array $calls): array
    {
        $maxConcurrent = Settings::MAX_CONCURRENT_REQUESTS;
        $results = [];
        $exceptions = [];

        // Create a generator that yields promises with their original indices
        $promiseGenerator = function () use ($calls) {
            foreach ($calls as $index => $call) {
                yield $index => $this->async($call[0], $call[1]);
            }
        };

        // Use EachPromise for concurrency-limited parallel execution
        $eachPromise = new EachPromise($promiseGenerator(), [
            'concurrency' => $maxConcurrent,
            'fulfilled' => function ($response, $index) use (&$results, $calls) {
                // Extract format from the call arguments, default to 'json'
                $format = $calls[$index][1]['format'] ?? 'json';
                $arguments = $calls[$index][1];

                // Process and store result at original index to maintain order
                $results[$index] = $this->processResponse($response, $format, $arguments);
            },
            'rejected' => function ($reason, $index) use (&$exceptions) {
                // Store exception at index for later throwing
                $exceptions[$index] = $reason;
            },
        ]);

        // Wait for all promises to complete
        $eachPromise->promise()->wait();

        // If any requests failed, throw the first exception
        if (!empty($exceptions)) {
            ksort($exceptions);
            throw reset($exceptions);
        }

        // Sort by index to maintain original order
        ksort($results);

        return array_values($results);
    }

    /**
     * Perform an asynchronous API request with retry logic.
     *
     * @param string $method    The API method to call.
     * @param array  $arguments The arguments for the API call.
     *
     * @return PromiseInterface
     * @throws RequestError
     * @throws BadStatusCodeError
     * @throws UnauthorizedException
     */
    protected function async($method, array $arguments = []): PromiseInterface
    {
        $format = array_key_exists('format', $arguments) ? $arguments['format'] : 'json';
        $maxAttempts = RetryConfig::MAX_RETRY_ATTEMPTS;
        $attempt = 0;

        // Build full URL for logging
        $fullUrl = self::API_URL . $method;
        if (!empty($arguments)) {
            $fullUrl .= '?' . http_build_query($arguments);
        }
        $logLevel = $this->isInternalRequest($method) ? 'debug' : 'info';

        // Track start time for each request attempt
        $startTime = microtime(true);

        $makeRequest = function() use ($method, $format, $arguments, &$startTime) {
            $startTime = microtime(true);
            return $this->guzzle->getAsync($method, [
                'headers' => $this->headers($format),
                'query'   => $arguments,
            ]);
        };

        $retry = function($promise) use (&$attempt, $maxAttempts, $makeRequest, &$retry, $method, $fullUrl, $logLevel, &$startTime) {
            return $promise->then(
                function($response) use (&$attempt, $maxAttempts, $makeRequest, &$retry, $method, $fullUrl, $logLevel, &$startTime) {
                    $durationMs = (microtime(true) - $startTime) * 1000;

                    // Log the request
                    $this->logRequest('GET', $response, $durationMs, $fullUrl, $logLevel);

                    // Validate status code
                    try {
                        $this->validateResponseStatusCode($response, true);

                        // Automatically update rate limits from response headers
                        $rateLimits = $this->extractRateLimitsFromResponse($response);
                        if ($rateLimits !== null) {
                            $this->rate_limits = $rateLimits;
                        }

                        return $response;
                    } catch (RequestError $e) {
                        // Retryable error (5xx) - check if service is offline
                        if ($this->shouldSkipRetryDueToOfflineService($method)) {
                            $this->logger->error('Service {service} is offline', ['service' => $method]);
                            throw $e;
                        }

                        $attempt++;
                        if ($attempt < $maxAttempts) {
                            $delay = $this->calculateBackoffDelay($attempt);
                            // Use promise-based delay (non-blocking)
                            return $this->createDelayedPromise($delay)
                                ->then(function() use ($makeRequest, &$retry) {
                                    return $retry($makeRequest());
                                });
                        }
                        throw $e;
                    } catch (BadStatusCodeError $e) {
                        // Non-retryable error (4xx)
                        throw $e;
                    }
                },
                function($reason) use (&$attempt, $maxAttempts, $makeRequest, &$retry, $method, $fullUrl, $logLevel, &$startTime) {
                    $durationMs = (microtime(true) - $startTime) * 1000;

                    // Handle ServerException (5xx)
                    if ($reason instanceof \GuzzleHttp\Exception\ServerException) {
                        // Log the failed request
                        $this->logRequest('GET', $reason->getResponse(), $durationMs, $fullUrl, $logLevel);

                        $statusCode = $reason->getResponse()->getStatusCode();
                        if (RetryConfig::isRetryableStatusCode($statusCode)) {
                            // Check if service is offline - skip retries if offline
                            if ($this->shouldSkipRetryDueToOfflineService($method)) {
                                $this->logger->error('Service {service} is offline', ['service' => $method]);
                                throw new RequestError(
                                    $this->getErrorMessage($reason->getResponse()),
                                    $statusCode,
                                    $reason,
                                    $reason->getResponse()
                                );
                            }

                            $attempt++;
                            if ($attempt < $maxAttempts) {
                                $delay = $this->calculateBackoffDelay($attempt);
                                return $this->createDelayedPromise($delay)
                                    ->then(function() use ($makeRequest, &$retry) {
                                        return $retry($makeRequest());
                                    });
                            }
                            throw new RequestError(
                                $this->getErrorMessage($reason->getResponse()),
                                $statusCode,
                                $reason,
                                $reason->getResponse()
                            );
                        }
                        throw new RequestError(
                            $this->getErrorMessage($reason->getResponse()),
                            $statusCode,
                            $reason,
                            $reason->getResponse()
                        );
                    }

                    // Handle ClientException (4xx)
                    if ($reason instanceof \GuzzleHttp\Exception\ClientException) {
                        // Log the failed request
                        $this->logRequest('GET', $reason->getResponse(), $durationMs, $fullUrl, $logLevel);

                        $statusCode = $reason->getResponse()->getStatusCode();
                        // 404 is handled specially - return response
                        if ($statusCode === 404) {
                            $response = $reason->getResponse();
                            // Automatically update rate limits from response headers
                            $rateLimits = $this->extractRateLimitsFromResponse($response);
                            if ($rateLimits !== null) {
                                $this->rate_limits = $rateLimits;
                            }
                            return $response;
                        }
                        // 401 UNAUTHORIZED gets a specific exception
                        if ($statusCode === 401) {
                            throw new UnauthorizedException(
                                $this->getErrorMessage($reason->getResponse()),
                                $statusCode,
                                $reason,
                                $reason->getResponse()
                            );
                        }
                        // Other 4xx errors are non-retryable
                        throw new BadStatusCodeError(
                            $this->getErrorMessage($reason->getResponse()),
                            $statusCode,
                            $reason,
                            $reason->getResponse()
                        );
                    }

                    // Handle RequestException (network errors, timeouts) - always retryable
                    if ($reason instanceof \GuzzleHttp\Exception\RequestException) {
                        $attempt++;
                        if ($attempt < $maxAttempts) {
                            $delay = $this->calculateBackoffDelay($attempt);
                            return $this->createDelayedPromise($delay)
                                ->then(function() use ($makeRequest, &$retry) {
                                    return $retry($makeRequest());
                                });
                        }
                        throw new RequestError(
                            "Request failed: " . $reason->getMessage(),
                            $reason->getCode(),
                            $reason,
                            $reason->hasResponse() ? $reason->getResponse() : null
                        );
                    }

                    // Re-throw other exceptions
                    throw $reason;
                }
            );
        };

        return $retry($makeRequest());
    }

    /**
     * Execute a single API request with retry logic.
     *
     * @param string $method    The API method to call.
     * @param array  $arguments The arguments for the API call.
     *
     * @return object The API response as an object.
     * @throws GuzzleException
     * @throws ApiException
     * @throws RequestError
     * @throws BadStatusCodeError
     * @throws UnauthorizedException
     */
    public function execute($method, array $arguments = []): object
    {
        $format = array_key_exists('format', $arguments) ? $arguments['format'] : 'json';

        // Build full URL for logging (base URL + method + query params)
        $fullUrl = self::API_URL . $method;
        if (!empty($arguments)) {
            $fullUrl .= '?' . http_build_query($arguments);
        }
        $logLevel = $this->isInternalRequest($method) ? 'debug' : 'info';

        // Retry logic matching Python SDK behavior
        $attempt = 0;
        $maxAttempts = RetryConfig::MAX_RETRY_ATTEMPTS;

        while ($attempt < $maxAttempts) {
            $startTime = microtime(true);
            try {
                $response = $this->guzzle->get($method, [
                    'headers' => $this->headers($format),
                    'query'   => $arguments,
                ]);
                $durationMs = (microtime(true) - $startTime) * 1000;

                // Log the request
                $this->logRequest('GET', $response, $durationMs, $fullUrl, $logLevel);

                // Validate response status code
                $this->validateResponseStatusCode($response, true);

                // Automatically update rate limits from response headers
                $rateLimits = $this->extractRateLimitsFromResponse($response);
                if ($rateLimits !== null) {
                    $this->rate_limits = $rateLimits;
                }

                // Success - process response
                return $this->processResponse($response, $format, $arguments);
                
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $durationMs = (microtime(true) - $startTime) * 1000;
                $statusCode = $e->getResponse()->getStatusCode();

                // Log the failed request
                $this->logRequest('GET', $e->getResponse(), $durationMs, $fullUrl, $logLevel);

                // 404 is handled specially (return response instead of throwing)
                if ($statusCode === 404) {
                    $response = $e->getResponse();
                    // Automatically update rate limits from response headers
                    $rateLimits = $this->extractRateLimitsFromResponse($response);
                    if ($rateLimits !== null) {
                        $this->rate_limits = $rateLimits;
                    }
                    return $this->processResponse($response, $format, $arguments);
                }
                
                // Non-retryable client errors (4xx except 404)
                $this->validateResponseStatusCode($e->getResponse(), false);
                // 401 UNAUTHORIZED gets a specific exception
                if ($statusCode === 401) {
                    throw new UnauthorizedException(
                        $this->getErrorMessage($e->getResponse()),
                        $statusCode,
                        $e,
                        $e->getResponse()
                    );
                }
                throw new BadStatusCodeError(
                    $this->getErrorMessage($e->getResponse()),
                    $statusCode,
                    $e,
                    $e->getResponse()
                );
                
            } catch (\GuzzleHttp\Exception\ServerException $e) {
                $durationMs = (microtime(true) - $startTime) * 1000;

                // Log the failed request
                $this->logRequest('GET', $e->getResponse(), $durationMs, $fullUrl, $logLevel);

                // Server errors (5xx) - check if retryable
                $statusCode = $e->getResponse()->getStatusCode();
                if (RetryConfig::isRetryableStatusCode($statusCode)) {
                    // Check if service is offline - skip retries if offline
                    if ($this->shouldSkipRetryDueToOfflineService($method)) {
                        $this->logger->error('Service {service} is offline', ['service' => $method]);
                        throw new RequestError(
                            $this->getErrorMessage($e->getResponse()),
                            $statusCode,
                            $e,
                            $e->getResponse()
                        );
                    }
                    
                    $attempt++;
                    if ($attempt < $maxAttempts) {
                        $this->waitForRetry($attempt);
                        continue; // Retry
                    }
                }
                
                // Retries exhausted or non-retryable 5xx
                throw new RequestError(
                    $this->getErrorMessage($e->getResponse()),
                    $statusCode,
                    $e,
                    $e->getResponse()
                );
                
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                // Network errors, timeouts, etc. - always retryable
                $attempt++;
                if ($attempt < $maxAttempts) {
                    $this->waitForRetry($attempt);
                    continue; // Retry
                }
                
                // Retries exhausted
                throw new RequestError(
                    "Request failed: " . $e->getMessage(),
                    $e->getCode(),
                    $e,
                    $e->hasResponse() ? $e->getResponse() : null
                );
                
            } catch (RequestError $e) {
                // RequestError from validateResponseStatusCode - retry if retryable
                $response = $e->getResponse();
                if ($response && RetryConfig::isRetryableStatusCode($response->getStatusCode())) {
                    // Check if service is offline - skip retries if offline
                    if ($this->shouldSkipRetryDueToOfflineService($method)) {
                        throw $e;
                    }
                    
                    $attempt++;
                    if ($attempt < $maxAttempts) {
                        $this->waitForRetry($attempt);
                        continue; // Retry
                    }
                }
                
                // Retries exhausted
                throw $e;
            }
        }
        
        // @codeCoverageIgnoreStart
        // Should never reach here, but just in case
        throw new RequestError("Request failed after $maxAttempts attempts", 0);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Process the response and return the appropriate object.
     *
     * @param \Psr\Http\Message\ResponseInterface $response The HTTP response.
     * @param string                                $format   The response format.
     * @param array                                 $arguments The request arguments.
     *
     * @return object The processed response.
     * @throws ApiException
     */
    protected function processResponse($response, string $format, array $arguments): object
    {
        switch ($format) {
            case 'csv':
            case 'html':
                $content = (string)$response->getBody();
                $responseObject = (object)array(
                    $arguments['format'] => $content
                );

                // If filename is provided, write to file
                if (isset($arguments['_filename']) && $arguments['_filename'] !== null) {
                    $filename = $arguments['_filename'];
                    $directory = dirname($filename);

                    // Create directory if it doesn't exist (for relative paths)
                    if ($directory !== '.' && $directory !== '' && !is_dir($directory)) {
                        if (!mkdir($directory, 0755, true)) {
                            throw new \RuntimeException("Failed to create directory: {$directory}");
                        }
                    }

                    // Write content to file
                    $bytesWritten = file_put_contents($filename, $content);
                    if ($bytesWritten === false) {
                        throw new \RuntimeException("Failed to write file: {$filename}");
                    }

                    // Store saved filename in response object for reference
                    $responseObject->_saved_filename = $filename;
                }

                return $responseObject;

            case 'json':
            default:
                $json_response = (string)$response->getBody();
                $object_response = json_decode($json_response);

                if (isset($object_response->s) && $object_response->s === 'error') {
                    throw new ApiException(message: $object_response->errmsg, response: $response);
                }

                return $object_response;
        }
    }

    /**
     * Validate response status code and raise appropriate exceptions.
     *
     * @param \Psr\Http\Message\ResponseInterface $response The HTTP response.
     * @param bool                                 $raiseForStatus Whether to raise for non-2xx status codes.
     *
     * @return void
     * @throws RequestError
     * @throws BadStatusCodeError
     * @throws UnauthorizedException
     */
    public function validateResponseStatusCode($response, bool $raiseForStatus = true): void
    {
        if (!$response) {
            return;
        }

        $statusCode = $response->getStatusCode();

        // Valid status codes (200-299)
        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        $errorMessage = $this->getErrorMessage($response);

        // Check if status code is retryable (> 500)
        if (RetryConfig::isRetryableStatusCode($statusCode)) {
            throw new RequestError($errorMessage, $statusCode, null, $response);
        }

        // Non-retryable errors (4xx)
        if ($raiseForStatus) {
            // 401 UNAUTHORIZED gets a specific exception
            if ($statusCode === 401) {
                throw new UnauthorizedException($errorMessage, $statusCode, null, $response);
            }
            throw new BadStatusCodeError($errorMessage, $statusCode, null, $response);
        }
    }

    /**
     * Get error message from response.
     *
     * @param \Psr\Http\Message\ResponseInterface $response The HTTP response.
     *
     * @return string The error message.
     */
    protected function getErrorMessage($response): string
    {
        if (!$response) {
            return "Request failed";
        }

        try {
            $body = (string)$response->getBody();
            $data = json_decode($body, true);
            if (isset($data['errmsg'])) {
                return $data['errmsg'];
            }
            return $body ?: "Request failed with status code: " . $response->getStatusCode();
        } catch (\Exception $e) {
            return "Request failed with status code: " . $response->getStatusCode();
        }
    }

    /**
     * Extract rate limit information from response headers.
     *
     * This method extracts rate limit data from API response headers and returns
     * a RateLimits object. Returns null if headers are missing, allowing
     * graceful degradation. This method is designed to be reusable for future
     * automatic rate limit tracking across all API requests.
     *
     * @param \Psr\Http\Message\ResponseInterface $response The HTTP response.
     *
     * @return RateLimits|null The rate limit information, or null if headers are missing.
     */
    public function extractRateLimitsFromResponse($response): ?RateLimits
    {
        if (!$response) {
            return null;
        }

        $headers = $response->getHeaders();
        
        // Helper function to get header value (case-insensitive)
        $getHeader = function($name) use ($headers) {
            $nameLower = strtolower($name);
            foreach ($headers as $key => $values) {
                if (strtolower($key) === $nameLower && !empty($values)) {
                    return $values[0];
                }
            }
            return null;
        };

        // Extract rate limit headers
        $limitHeader = $getHeader('x-api-ratelimit-limit');
        $remainingHeader = $getHeader('x-api-ratelimit-remaining');
        $resetHeader = $getHeader('x-api-ratelimit-reset');
        $consumedHeader = $getHeader('x-api-ratelimit-consumed');

        // If any required header is missing, return null
        if ($limitHeader === null || $remainingHeader === null || 
            $resetHeader === null || $consumedHeader === null) {
            return null;
        }

        // Validate that header values are numeric
        if (!is_numeric($limitHeader) || !is_numeric($remainingHeader) || 
            !is_numeric($resetHeader) || !is_numeric($consumedHeader)) {
            return null;
        }

        // Convert to integers
        $limit = (int)$limitHeader;
        $remaining = (int)$remainingHeader;
        $consumed = (int)$consumedHeader;
        
        // Convert reset timestamp to Carbon datetime
        $reset = \Carbon\Carbon::createFromTimestamp((int)$resetHeader);

        return new RateLimits(
            $limit,
            $remaining,
            $reset,
            $consumed
        );
    }

    /**
     * Log a completed HTTP request.
     *
     * Logs one line per request with format: METHOD STATUS DURATION REQUEST_ID URL
     *
     * @param string            $method     HTTP method (GET, POST, etc.).
     * @param ResponseInterface $response   The HTTP response.
     * @param float             $durationMs Request duration in milliseconds.
     * @param string            $url        The full request URL.
     * @param string            $logLevel   Log level: 'info' for API requests, 'debug' for internal.
     *
     * @return void
     */
    protected function logRequest(
        string $method,
        ResponseInterface $response,
        float $durationMs,
        string $url,
        string $logLevel = 'info'
    ): void {
        $cfRay = $response->getHeaderLine('cf-ray') ?: '-';
        $status = $response->getStatusCode();
        $duration = LoggingUtilities::formatDuration($durationMs);

        // Unified format: METHOD STATUS DURATION REQUEST_ID URL
        $message = "{$method} {$status} {$duration} {$cfRay} {$url}";
        $this->logger->log($logLevel, $message);
    }

    /**
     * Check if a URL is for an internal request.
     *
     * Internal requests (rate limit setup, API status) are logged at DEBUG level.
     * API requests are logged at INFO level.
     *
     * @param string $url The request URL.
     *
     * @return bool True if internal request, false otherwise.
     */
    protected function isInternalRequest(string $url): bool
    {
        return str_contains($url, 'user/') || str_contains($url, 'utilities/status');
    }

    /**
     * Calculate exponential backoff delay.
     *
     * @param int $attempt The current attempt number (1-based).
     *
     * @return float The delay in seconds.
     */
    protected function calculateBackoffDelay(int $attempt): float
    {
        $delay = RetryConfig::RETRY_BACKOFF * (2 ** ($attempt - 1));
        return min(max($delay, RetryConfig::MIN_RETRY_BACKOFF), RetryConfig::MAX_RETRY_BACKOFF);
    }

    /**
     * Create a promise that resolves after a delay.
     * 
     * Note: PHP doesn't have native async timers, so this uses a micro-delay
     * approach. For true non-blocking behavior, an event loop would be needed.
     * This implementation provides the delay while maintaining promise chaining.
     *
     * @param float $delay The delay in seconds.
     *
     * @return PromiseInterface A promise that resolves after the delay.
     */
    protected function createDelayedPromise(float $delay): PromiseInterface
    {
        // Create a promise that resolves after the delay
        // Since PHP doesn't have native async timers, we use a small delay
        // that allows other promises to process
        return Create::promiseFor(null)->then(function() use ($delay) {
            // Use usleep for the delay (this will block the current execution,
            // but allows the promise chain to work correctly)
            usleep((int)($delay * 1000000));
            return null;
        });
    }

    /**
     * Wait for retry with exponential backoff.
     *
     * @param int $attempt The current attempt number (1-based).
     *
     * @return void
     */
    protected function waitForRetry(int $attempt): void
    {
        $delay = $this->calculateBackoffDelay($attempt);
        usleep((int)($delay * 1000000)); // Convert seconds to microseconds
    }

    /**
     * Get service path from method path using hardcoded mapping.
     *
     * Maps method paths like "v1/stocks/quotes/AAPL" to service paths like "/v1/stocks/quotes/".
     * Returns null for status endpoint (to avoid checking its own status) or unknown services.
     *
     * @param string $method The method path (e.g., "v1/stocks/quotes/AAPL").
     * @return string|null The service path (e.g., "/v1/stocks/quotes/") or null if not found/special case.
     */
    protected function getServicePath(string $method): ?string
    {
        // Skip status checking for status endpoint itself (would cause infinite loop)
        if ($method === 'status/' || str_starts_with($method, 'status/')) {
            return null;
        }

        // Hardcoded mapping based on known services from API status response
        // Match method paths that start with these prefixes
        $serviceMappings = [
            'v1/stocks/quotes' => '/v1/stocks/quotes/',
            'v1/stocks/candles' => '/v1/stocks/candles/',
            'v1/stocks/bulkcandles' => '/v1/stocks/bulkcandles/',
            'v1/stocks/bulkquotes' => '/v1/stocks/bulkquotes/',
            'v1/stocks/earnings' => '/v1/stocks/earnings/',
            'v1/stocks/news' => '/v1/stocks/news/',
            'v1/options/chain' => '/v1/options/chain/',
            'v1/options/expirations' => '/v1/options/expirations/',
            'v1/options/lookup' => '/v1/options/lookup/',
            'v1/options/quotes' => '/v1/options/quotes/',
            'v1/options/strikes' => '/v1/options/strikes/',
            'v1/markets/status' => '/v1/markets/status/',
        ];

        // Remove query string if present
        $methodPath = strtok($method, '?');

        // Check each mapping
        foreach ($serviceMappings as $prefix => $servicePath) {
            if (str_starts_with($methodPath, $prefix)) {
                return $servicePath;
            }
        }

        // No mapping found - return null (will default to retrying - UNKNOWN behavior)
        return null;
    }

    /**
     * Check if service is offline and should skip retries.
     *
     * @param string $method The method path being called.
     * @return bool True if service is offline (should skip retries), false otherwise.
     */
    protected function shouldSkipRetryDueToOfflineService(string $method): bool
    {
        $servicePath = $this->getServicePath($method);
        
        // If no service path found, default to retrying (UNKNOWN behavior)
        if ($servicePath === null) {
            return false;
        }

        try {
            // Get ApiStatusData singleton instance
            // Use reflection to access Utilities::getApiStatusData() since ClientBase doesn't have direct access
            $utilitiesReflection = new \ReflectionClass(Utilities::class);
            $getApiStatusDataMethod = $utilitiesReflection->getMethod('getApiStatusData');
            $apiStatusData = $getApiStatusDataMethod->invoke(null);
            
            // Check service status
            // Skip blocking refresh during retry logic to avoid extra API calls
            // If cache is stale/empty, return UNKNOWN (allows retry)
            $status = $apiStatusData->getApiStatus($this, $servicePath, true);
            
            // Skip retries if service is offline
            return $status === ApiStatusResult::OFFLINE;
        } catch (\Exception $e) {
            // If status check fails, default to retrying (UNKNOWN behavior)
            // This ensures we don't break existing functionality
            return false;
        }
    }

    /**
     * Generate headers for API requests.
     *
     * @param string $format The desired response format (json, csv, or html).
     *
     * @return array An array of headers.
     */
    protected function headers(string $format = 'json'): array
    {
        return [
            'Host'          => self::API_HOST,
            'User-Agent'    => 'marketdata-sdk-php/' . self::VERSION,
            'Accept'        => match ($format) {
                'json' => 'application/json',
                'csv' => 'text/csv',
                'html' => 'text/html',
            },
            'Authorization' => "Bearer $this->token",
        ];
    }

    /**
     * Make a raw API request and return the response object.
     *
     * This method is useful for endpoints that need access to response headers,
     * such as the /user/ endpoint for rate limit information.
     *
     * @param string $method    The API method to call (no API version prefix).
     * @param array  $arguments Optional query parameters.
     *
     * @return \Psr\Http\Message\ResponseInterface The HTTP response.
     * @throws GuzzleException
     * @throws UnauthorizedException
     */
    public function makeRawRequest(string $method, array $arguments = []): ResponseInterface
    {
        // Build full URL for logging
        $fullUrl = self::API_URL . $method;
        if (!empty($arguments)) {
            $fullUrl .= '?' . http_build_query($arguments);
        }

        $startTime = microtime(true);
        try {
            $response = $this->guzzle->get($method, [
                'headers' => $this->headers('json'),
                'query'   => $arguments,
            ]);
            $durationMs = (microtime(true) - $startTime) * 1000;

            // Internal requests logged at DEBUG level
            $this->logRequest('GET', $response, $durationMs, $fullUrl, 'debug');

            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $durationMs = (microtime(true) - $startTime) * 1000;

            // Log the failed request
            $this->logRequest('GET', $e->getResponse(), $durationMs, $fullUrl, 'debug');

            $statusCode = $e->getResponse()->getStatusCode();
            // 401 UNAUTHORIZED gets a specific exception
            if ($statusCode === 401) {
                throw new UnauthorizedException(
                    $this->getErrorMessage($e->getResponse()),
                    $statusCode,
                    $e,
                    $e->getResponse()
                );
            }
            // Re-throw other ClientExceptions
            throw $e;
        }
    }
}
