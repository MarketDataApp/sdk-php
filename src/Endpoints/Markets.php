<?php

namespace MarketDataApp\Endpoints;

use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Markets\Statuses;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Traits\UniversalParameters;

/**
 * Markets class for handling market-related API endpoints.
 */
class Markets
{

    use UniversalParameters;

    /** @var Client The Market Data API client instance. */
    private Client $client;

    /** @var string The base URL for market endpoints. */
    public const BASE_URL = "v1/markets/";

    /**
     * Markets constructor.
     *
     * @param Client $client The Market Data API client instance.
     */
    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * Get the market status for a specific country and date range.
     *
     * Get the past, present, or future status for a stock market. The endpoint will respond with "open" for trading
     * days or "closed" for weekends or market holidays.
     *
     * @param string          $country    The country. Use the two-digit ISO 3166 country code. If no country is
     *                                    specified, US will be assumed. Only countries that Market Data supports for
     *                                    stock price data are available (currently only the United States).
     *
     * @param string|null     $date       Consult whether the market was open or closed on the specified date. Accepted
     *                                    timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param string|null     $from       The earliest date (inclusive). If you use countback, from is not required.
     *                                    Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param string|null     $to         The last date (inclusive). Accepted timestamp inputs: ISO 8601, unix,
     *                                    spreadsheet.
     *
     * @param int|null        $countback  Countback will fetch a number of dates before to If you use from, countback
     *                                    is not required.
     *
     * @param Parameters|null $parameters Universal parameters for all methods (such as format).
     *
     * @return Statuses
     * @throws GuzzleException|ApiException
     */
    public function status(
        string $country = "US",
        string $date = null,
        string $from = null,
        string $to = null,
        int $countback = null,
        ?Parameters $parameters = null
    ): Statuses {
        return new Statuses($this->execute("status/",
            compact('country', 'date', 'from', 'to', 'countback'), $parameters));
    }
}
