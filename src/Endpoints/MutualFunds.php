<?php

namespace MarketDataApp\Endpoints;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\MutualFunds\Candles;
use MarketDataApp\Exceptions\ApiException;

class MutualFunds
{

    private Client $client;
    public const BASE_URL = "v1/funds/";

    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * Get historical price candles for a mutual fund.
     *
     * @param string $symbol The mutual fund's ticker symbol.
     * @param Carbon $from The leftmost candle on a chart (inclusive). If you use countback, to is not required.
     * @param Carbon|null $to The rightmost candle on a chart (inclusive).
     * @param string $resolution The duration of each candle.
     * Minutely Resolutions: (minutely, 1, 3, 5, 15, 30, 45, ...) Hourly Resolutions: (hourly, H, 1H, 2H, ...)
     * Daily Resolutions: (daily, D, 1D, 2D, ...)
     * Weekly Resolutions: (weekly, W, 1W, 2W, ...)
     * Monthly Resolutions: (monthly, M, 1M, 2M, ...)
     * Yearly Resolutions:(yearly, Y, 1Y, 2Y, ...)
     *
     * @param int|null $countback Will fetch a number of candles before (to the left of) to. If you use from, countback
     * is not required.
     *
     * @return Candles
     * @throws GuzzleException|ApiException
     */
    public function candles(
        string $symbol,
        Carbon $from,
        Carbon $to = null,
        string $resolution = 'D',
        int $countback = null,
    ): Candles {
        return new Candles($this->client->execute(self::BASE_URL . "candles/{$resolution}/{$symbol}/",
            compact('from', 'to', 'countback')
        ));
    }
}
