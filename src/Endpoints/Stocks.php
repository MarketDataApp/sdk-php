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
     * @param bool $fifty_two_week Enable the output of 52-week high and 52-week low data in the quote output. By
     * default this parameter is false if omitted.
     * @throws GuzzleException
     */
    public function quote(string $symbol, bool $fifty_two_week = false): StocksQuote
    {
        return new StocksQuote($this->client->execute(self::BASE_URL . "quotes/{$symbol}",
            ['52week' => $fifty_two_week]));
    }

    /**
     * Get a real-time price quote for a multiple stocks by doing parallel requests.
     *
     * @param array $symbols The ticker symbols to return in the response.
     * @param bool $fifty_two_week Enable the output of 52-week high and 52-week low data in the quote output. By
     * default this parameter is false if omitted.
     * @throws \Throwable
     */
    public function quotes(array $symbols, bool $fifty_two_week = false): StocksQuotes|StocksBulkQuotes
    {
        // Execute standard quotes in parallel
        $calls = [];
        foreach ($symbols as $symbol) {
            $calls[] = ["/stocks/quotes/$symbol", ['52week' => $fifty_two_week]];
        }

        return new StocksQuotes($this->client->executeInParallel($calls));
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
     * @param bool $snapshot Returns a full market snapshot with quotes for all symbols when set to true. The symbols
     * parameter may be omitted if the snapshot parameter is set.
     * @throws GuzzleException
     * @throws \Exception
     */
    public function bulkQuotes(array $symbols = [], bool $snapshot = false): StocksBulkQuotes
    {
        if (empty($symbols) && !$snapshot) {
            throw new \InvalidArgumentException('Either symbols or snapshot must be set');
        }

        return new StocksBulkQuotes($this->client->execute(self::BASE_URL . "bulkquotes",
            ['symbols' => implode(',', $symbols), 'snapshot' => $snapshot]));
    }
}
