<?php

namespace MarketDataApp\Endpoints;

use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\IndicesQuote;

class Indices
{

    private Client $client;
    public const BASE_URL = "v1/indices/";

    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * @param string $symbol The index symbol, without any leading or trailing index identifiers. For example, use DJI
     * do not use $DJI, ^DJI, .DJI, DJI.X, etc.
     * @throws GuzzleException
     */
    public function quote(string $symbol): IndicesQuote
    {
        return new IndicesQuote($this->client->execute(self::BASE_URL . "quotes/{$symbol}"));
    }
}
