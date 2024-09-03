<?php

namespace MarketDataApp;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use MarketDataApp\Exceptions\ApiException;

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
     * @var GuzzleClient The Guzzle HTTP client instance.
     */
    protected GuzzleClient $guzzle;

    /**
     * @var string The API token for authentication.
     */
    protected string $token;

    /**
     * ClientBase constructor.
     *
     * @param string $token The API token for authentication.
     */
    public function __construct(string $token)
    {
        $this->guzzle = new GuzzleClient(['base_uri' => self::API_URL]);
        $this->token = $token;
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
     * Execute multiple API calls in parallel.
     *
     * @param array $calls An array of method calls, each containing the method name and arguments.
     *
     * @return array An array of decoded JSON responses.
     * @throws \Throwable
     */
    public function execute_in_parallel(array $calls): array
    {
        $promises = [];
        foreach ($calls as $call) {
            $promises[] = $this->async($call[0], $call[1]);
        }
        $responses = Promise\Utils::unwrap($promises);

        return array_map(function ($response) {
            return json_decode((string)$response->getBody());
        }, $responses);
    }

    /**
     * Perform an asynchronous API request.
     *
     * @param string $method    The API method to call.
     * @param array  $arguments The arguments for the API call.
     *
     * @return PromiseInterface
     */
    protected function async($method, array $arguments = []): PromiseInterface
    {
        return $this->guzzle->getAsync($method, [
            'headers' => $this->headers(),
            'query'   => $arguments,
        ]);
    }

    /**
     * Execute a single API request.
     *
     * @param string $method    The API method to call.
     * @param array  $arguments The arguments for the API call.
     *
     * @return object The API response as an object.
     * @throws GuzzleException
     * @throws ApiException
     */
    public function execute($method, array $arguments = []): object
    {
        try {
            $format = array_key_exists('format', $arguments) ? $arguments['format'] : 'json';
            $response = $this->guzzle->get($method, [
                'headers' => $this->headers($format),
                'query'   => $arguments,
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = match ($e->getResponse()->getStatusCode()) {
                404 => $e->getResponse(),
                default => throw $e,
            };
        }

        switch ($format) {
            case 'csv':
            case 'html':
                $object_response = (object)array(
                    $arguments['format'] => (string)$response->getBody()
                );
                break;

            case 'json':
            default:
                $json_response = (string)$response->getBody();

                $object_response = json_decode($json_response);

                if (isset($object_response->s) && $object_response->s === 'error') {
                    throw new ApiException(message: $object_response->errmsg, response: $response);
                }
        }

        return $object_response;
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
            'Accept'        => match ($format) {
                'json' => 'application/json',
                'csv' => 'text/csv',
                'html' => 'text/html',
            },
            'Authorization' => "Bearer $this->token",
        ];
    }
}
