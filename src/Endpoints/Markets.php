<?php

namespace MarketDataApp\Endpoints;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Markets\Statuses;
use MarketDataApp\Exceptions\ApiException;

class Markets
{

    private Client $client;
    public const BASE_URL = "v1/markets/";

    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * Get the past, present, or future status for a stock market. The endpoint will respond with "open" for trading
     * days or "closed" for weekends or market holidays.
     *
     * @param string $country The country. Use the two digit ISO 3166 country code. If no country is specified, US will
     * be assumed. Only countries that Market Data supports for stock price data are available (currently only the
     * United States).
     *
     * @param Carbon|null $date Consult whether the market was open or closed on the specified date.
     * @param Carbon|null $from The earliest date (inclusive). If you use countback, from is not required.
     * @param Carbon|null $to The last date (inclusive).
     * @param int|null $countback Countback will fetch a number of dates before to If you use from, countback is not
     * required.
     * @return Statuses
     * @throws GuzzleException|ApiException
     */
    public function status(
        string $country = "US",
        Carbon $date = null,
        Carbon $from = null,
        Carbon $to = null,
        int $countback = null
    ): Statuses {
        // Stub
        return new Statuses($this->client->execute(self::BASE_URL . "status/",
            compact('country', 'date', 'from', 'to', 'countback')));
    }
}
