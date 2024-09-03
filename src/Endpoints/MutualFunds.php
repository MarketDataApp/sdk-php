<?php

namespace MarketDataApp\Endpoints;

use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\MutualFunds\Candles;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Traits\UniversalParameters;

/**
 * MutualFunds class for handling mutual fund-related API endpoints.
 */
class MutualFunds
{

    use UniversalParameters;

    /** @var Client The Market Data API client instance. */
    private Client $client;

    /** @var string The base URL for mutual fund endpoints. */
    public const BASE_URL = "v1/funds/";

    /**
     * MutualFunds constructor.
     *
     * @param Client $client The Market Data API client instance.
     */
    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * Get historical price candles for a mutual fund.
     *
     * @param string          $symbol     The mutual fund's ticker symbol.
     *
     * @param string          $from       The leftmost candle on a chart (inclusive). If you use countback, to is not
     *                                    required. Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param string|null     $to         The rightmost candle on a chart (inclusive). Accepted timestamp inputs: ISO
     *                                    8601, unix, spreadsheet.
     *
     * @param string          $resolution The duration of each candle.
     *                                    - Minutely Resolutions: (minutely, 1, 3, 5, 15, 30, 45, ...)
     *                                    - Hourly Resolutions: (hourly, H, 1H, 2H, ...)
     *                                    - Daily Resolutions: (daily, D, 1D, 2D, ...)
     *                                    - Weekly Resolutions: (weekly, W, 1W, 2W, ...)
     *                                    - Monthly Resolutions: (monthly, M, 1M, 2M, ...)
     *                                    - Yearly Resolutions:(yearly, Y, 1Y, 2Y, ...)
     *
     * @param int|null        $countback  Will fetch a number of candles before (to the left of) to. If you use from,
     *                                    countback is not required.
     *
     * @param Parameters|null $parameters Universal parameters for all methods (such as format).
     *
     * @return Candles
     * @throws GuzzleException|ApiException
     */
    public function candles(
        string $symbol,
        string $from,
        string $to = null,
        string $resolution = 'D',
        int $countback = null,
        ?Parameters $parameters = null
    ): Candles {
        return new Candles($this->execute("candles/{$resolution}/{$symbol}/",
            compact('from', 'to', 'countback'), $parameters
        ));
    }
}
