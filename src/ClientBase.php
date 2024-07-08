<?php

namespace MarketDataApp;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\PromiseInterface;

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
     */
    public function execute($method, array $arguments = []): object
    {
        $response = $this->guzzle->get($method, [
            'headers' => $this->headers(),
            'query'   => $arguments,
        ]);
        $json_response = (string)$response->getBody();

        return json_decode($json_response);
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
