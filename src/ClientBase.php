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
    public function executeInParallel(array $calls): array
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
            $response = $this->guzzle->get($method, [
                'headers' => $this->headers(),
                'query'   => $arguments,
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = match ($e->getResponse()->getStatusCode()) {
                404 => $e->getResponse(),
                default => throw $e,
            };
        }

        $json_response = (string)$response->getBody();

        $response = json_decode($json_response);

        if (isset($response->s) && $response->s === 'error') {
            throw new ApiException(message: $response->errmsg, response: $response);
        }

        return $response;
    }

    protected function headers(): array
    {
        return [
            'Host'          => self::API_HOST,
            'Accept'        => 'application/json',
            'Authorization' => "Bearer $this->token",
        ];
    }
}
