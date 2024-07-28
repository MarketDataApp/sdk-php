<?php

namespace MarketDataApp;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use MarketDataApp\Exceptions\ApiException;

abstract class ClientBase
{

    public const API_URL = "https://api.marketdata.app/";
    public const API_HOST = "api.marketdata.app";

    protected GuzzleClient $guzzle;
    protected string $token;

    public function __construct(string $token)
    {
        $this->guzzle = new GuzzleClient(['base_uri' => self::API_URL]);
        $this->token = $token;
    }

    public function setGuzzle(GuzzleClient $guzzleClient): void
    {
        $this->guzzle = $guzzleClient;
    }

    /**
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

    protected function async($method, array $arguments = []): PromiseInterface
    {
        return $this->guzzle->getAsync($method, [
            'headers' => $this->headers(),
            'query'   => $arguments,
        ]);
    }

    /**
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
