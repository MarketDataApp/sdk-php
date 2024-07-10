<?php

namespace MarketDataApp\Endpoints;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\StocksBulkQuotes;
use MarketDataApp\Endpoints\Responses\StocksQuote;
use MarketDataApp\Endpoints\Responses\StocksQuotes;

class Stocks
{

    private Client $client;
    public const BASE_URL = "v1/stocks/";

    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * Get a real-time price quote for a stock.
     *
     * @param string $symbol The company's ticker symbol.
     * @throws GuzzleException
     */
    public function quote(string $symbol): StocksQuote
    {
        return new StocksQuote($this->client->execute(self::BASE_URL . "quotes/{$symbol}"));
    }

    /**
     * Get a real-time price quote for a multiple stocks in a single API request.
     *
     * The bulkquotes endpoint is designed to return hundreds of symbols at once or full market snapshots. Response
     * times for less than 50 symbols will be quicker using the standard quotes endpoint and sending your requests in
     * parallel.
     *
     * @param array $symbols The ticker symbols to return in the response, separated by commas. The symbols parameter
     * may be omitted if the snapshot parameter is set to true.
     * @throws \Throwable
     */
    public function quotes(array $symbols): StocksQuotes|StocksBulkQuotes
    {
        // Execute standard quotes in parallel
        $calls = [];
        foreach ($symbols as $symbol) {
            $calls[] = ["/stocks/quotes/$symbol", []];
        }

        return new StocksQuotes($this->client->executeInParallel($calls));
    }

    public function bulkQuotes(array $symbols): StocksBulkQuotes
    {
        return new StocksBulkQuotes($this->client->execute(self::BASE_URL . "bulkquotes",
            ['symbols' => implode(',', $symbols)]));
    }
}
