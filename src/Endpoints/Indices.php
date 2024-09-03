<?php

namespace MarketDataApp\Endpoints;

use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Indices\Candles;
use MarketDataApp\Endpoints\Responses\Indices\Quote;
use MarketDataApp\Endpoints\Responses\Indices\Quotes;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Traits\UniversalParameters;

/**
 * Indices class for handling index-related API endpoints.
 */
class Indices
{

    use UniversalParameters;

    /** @var Client The Market Data API client instance. */
    private Client $client;

    /** @var string The base URL for index endpoints. */
    public const BASE_URL = "v1/indices/";

    /**
     * Indices constructor.
     *
     * @param Client $client The Market Data API client instance.
     */
    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * Get a real-time quote for an index.
     *
     * @param string          $symbol         The index symbol, without any leading or trailing index identifiers. For
     *                                        example, use DJI do not use $DJI, ^DJI, .DJI, DJI.X, etc.
     *
     * @param bool            $fifty_two_week Enable the output of 52-week high and 52-week low data in the quote
     *                                        output.
     *
     * @param Parameters|null $parameters     Universal parameters for all methods (such as format).
     *
     * @return Quote
     * @throws GuzzleException|ApiException
     */
    public function quote(
        string $symbol,
        bool $fifty_two_week = false,
        ?Parameters $parameters = null
    ): Quote {
        return new Quote($this->execute("quotes/$symbol", ['52week' => $fifty_two_week], $parameters));
    }

    /**
     * Get real-time price quotes for multiple indices by doing parallel requests.
     *
     * @param array           $symbols        The ticker symbols to return in the response.
     * @param bool            $fifty_two_week Enable the output of 52-week high and 52-week low data in the quote
     *                                        output.
     * @param Parameters|null $parameters     Universal parameters for all methods (such as format).
     *
     * @return Quotes
     * @throws \Throwable
     */
    public function quotes(
        array $symbols,
        bool $fifty_two_week = false,
        ?Parameters $parameters = null
    ): Quotes {
        // Execute standard quotes in parallel
        $calls = [];
        foreach ($symbols as $symbol) {
            $calls[] = ["quotes/$symbol", ['52week' => $fifty_two_week]];
        }

        return new Quotes($this->execute_in_parallel($calls, $parameters));
    }

    /**
     * Get historical price candles for an index.
     *
     * @param string          $symbol     The index symbol, without any leading or trailing index identifiers. For
     *                                    example, use DJI do not use $DJI, ^DJI, .DJI, DJI.X, etc.
     *
     * @param string          $from       The leftmost candle on a chart (inclusive). If you use countback, to is not
     *                                    required. Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param string|null     $to         The rightmost candle on a chart (inclusive). Accepted timestamp inputs: ISO
     *                                    8601, unix, spreadsheet.
     *
     * @param string          $resolution The duration of each candle.
     *                                    Minutely Resolutions: (minutely, 1, 3, 5, 15, 30, 45, ...) Hourly
     *                                    Resolutions: (hourly, H, 1H, 2H, ...) Daily Resolutions: (daily, D, 1D, 2D,
     *                                    ...) Weekly Resolutions: (weekly, W, 1W, 2W, ...) Monthly Resolutions:
     *                                    (monthly, M, 1M, 2M, ...) Yearly Resolutions:(yearly, Y, 1Y, 2Y, ...)
     *
     * @param int|null        $countback  Will fetch a number of candles before (to the left of) to. If you use from,
     *                                    countback is not required.
     *
     * @param Parameters|null $parameters Universal parameters for all methods (such as format).
     *
     * @return Candles
     * @throws ApiException|GuzzleException
     */
    public function candles(
        string $symbol,
        string $from,
        string $to = null,
        string $resolution = 'D',
        int $countback = null,
        ?Parameters $parameters = null
    ): Candles {
        return new Candles($this->execute("candles/{$resolution}/{$symbol}/", compact('from', 'to', 'countback'),
            $parameters));
    }
}
