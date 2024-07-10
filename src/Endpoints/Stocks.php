<?php

namespace MarketDataApp\Endpoints;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Stocks\BulkQuotes;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Endpoints\Responses\Stocks\Quotes;

class Stocks
{

    private Client $client;
    public const BASE_URL = "v1/stocks/";

    public function __construct($client)
    {
        $this->client = $client;
    }

        /**
     * Get historical price candles for an index.
     *
     * @param string $symbol The company's ticker symbol.
     * @param Carbon $from The leftmost candle on a chart (inclusive). If you use countback, to is not required.
     * @param Carbon|null $to The rightmost candle on a chart (inclusive).
     * @param string $resolution The duration of each candle.
     * Minutely Resolutions: (minutely, 1, 3, 5, 15, 30, 45, ...) Hourly Resolutions: (hourly, H, 1H, 2H, ...)
     * Daily Resolutions: (daily, D, 1D, 2D, ...)
     * Weekly Resolutions: (weekly, W, 1W, 2W, ...)
     * Monthly Resolutions: (monthly, M, 1M, 2M, ...)
     * Yearly Resolutions:(yearly, Y, 1Y, 2Y, ...)
     * @param int|null $countback Will fetch a number of candles before (to the left of) to. If you use from, countback
     * is not required.
     * @return Candles
     * @throws GuzzleException
     */
    public function candles(
        string $symbol,
        Carbon $from,
        Carbon $to = null,
        string $resolution = 'D',
        int $countback = null
    ): Candles {
        return new Candles($this->client->execute(self::BASE_URL . "candles/{$resolution}/{$symbol}/",
            compact('from', 'to', 'countback')
        ));
    }

    /**
     * Get a real-time price quote for a stock.
     *
     * @param string $symbol The company's ticker symbol.
     * @param bool $fifty_two_week Enable the output of 52-week high and 52-week low data in the quote output. By
     * default this parameter is false if omitted.
     * @throws GuzzleException
     */
    public function quote(string $symbol, bool $fifty_two_week = false): Quote
    {
        return new Quote($this->client->execute(self::BASE_URL . "quotes/{$symbol}",
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
    public function quotes(array $symbols, bool $fifty_two_week = false): Quotes|BulkQuotes
    {
        // Execute standard quotes in parallel
        $calls = [];
        foreach ($symbols as $symbol) {
            $calls[] = ["/stocks/quotes/$symbol", ['52week' => $fifty_two_week]];
        }

        return new Quotes($this->client->executeInParallel($calls));
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
    public function bulkQuotes(array $symbols = [], bool $snapshot = false): BulkQuotes
    {
        if (empty($symbols) && !$snapshot) {
            throw new \InvalidArgumentException('Either symbols or snapshot must be set');
        }

        return new BulkQuotes($this->client->execute(self::BASE_URL . "bulkquotes",
            ['symbols' => implode(',', $symbols), 'snapshot' => $snapshot]));
    }
}
