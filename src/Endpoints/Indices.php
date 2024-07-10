<?php

namespace MarketDataApp\Endpoints;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\IndicesCandles;
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
     * Get a real-time quote for an index.
     *
     * @param string $symbol The index symbol, without any leading or trailing index identifiers. For example, use DJI
     * do not use $DJI, ^DJI, .DJI, DJI.X, etc.
     * @throws GuzzleException
     */
    public function quote(string $symbol): IndicesQuote
    {
        return new IndicesQuote($this->client->execute(self::BASE_URL . "quotes/{$symbol}"));
    }

    /**
     * Get historical price candles for an index.
     *
     * @param string $symbol The index symbol, without any leading or trailing index identifiers. For example, use DJI
     * do not use $DJI, ^DJI, .DJI, DJI.X, etc.
     * @param Carbon $from The leftmost candle on a chart (inclusive). If you use countback, to is not required.
     * Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     * @param Carbon|null $to The rightmost candle on a chart (inclusive). Accepted timestamp inputs: ISO 8601, unix,
     * spreadsheet.
     * @param string $resolution The duration of each candle.
     * Minutely Resolutions: (minutely, 1, 3, 5, 15, 30, 45, ...) Hourly Resolutions: (hourly, H, 1H, 2H, ...)
     * Daily Resolutions: (daily, D, 1D, 2D, ...)
     * Weekly Resolutions: (weekly, W, 1W, 2W, ...)
     * Monthly Resolutions: (monthly, M, 1M, 2M, ...)
     * Yearly Resolutions:(yearly, Y, 1Y, 2Y, ...)
     * @param int|null $countback Will fetch a number of candles before (to the left of) to. If you use from, countback
     * is not required.
     * @return IndicesCandles
     * @throws GuzzleException
     */
    public function candles(
        string $symbol,
        Carbon $from,
        Carbon $to = null,
        string $resolution = 'D',
        int $countback = null
    ): IndicesCandles {
        return new IndicesCandles($this->client->execute(self::BASE_URL . "candles/{$resolution}/{$symbol}/",
            compact('from', 'to', 'countback')
        ));
    }
}
