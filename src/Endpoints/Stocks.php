<?php

namespace MarketDataApp\Endpoints;

use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\BulkCandles;
use MarketDataApp\Endpoints\Responses\Stocks\BulkQuotes;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Endpoints\Responses\Stocks\Earnings;
use MarketDataApp\Endpoints\Responses\Stocks\News;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Endpoints\Responses\Stocks\Quotes;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Traits\UniversalParameters;

/**
 * Stocks class for handling stock-related API endpoints.
 */
class Stocks
{

    use UniversalParameters;

    /** @var Client The Market Data API client instance. */
    private Client $client;

    /** @var string The base URL for stock endpoints. */
    public const BASE_URL = "v1/stocks/";

    /**
     * Stocks constructor.
     *
     * @param Client $client The Market Data API client instance.
     */
    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * Get bulk candle data for stocks.
     *
     * Get bulk candle data for stocks. This endpoint returns bulk daily candle data for multiple stocks. Unlike the
     * standard candles endpoint, this endpoint returns a single daily for each symbol provided. The typical use-case
     * for this endpoint is to get a complete market snapshot during trading hours, though it can also be used for bulk
     * snapshots of historical daily candles.
     *
     * @param array           $symbols       The ticker symbols to return in the response, separated by commas. The
     *                                       symbols parameter may be omitted if the snapshot parameter is set to true.
     *
     * @param string          $resolution    The duration of each candle. Only daily candles are supported at this
     *                                       time.
     *                                       Daily Resolutions: (daily, D, 1D, 2D, ...)
     *
     * @param bool            $snapshot      Returns candles for all available symbols for the date indicated. The
     *                                       symbols parameter can be omitted if snapshot is set to true.
     *
     * @param string|null     $date          The date of the candles to be returned. If no date is specified, during
     *                                       market hours the candles returned will be from the current session. If the
     *                                       market is closed the candles will be from the most recent session.
     *                                       Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param bool            $adjust_splits Adjust historical data for historical splits and reverse splits. Market
     *                                       Data uses the CRSP methodology for adjustment. Daily candles default:
     *                                       true.
     *
     * @param Parameters|null $parameters    Universal parameters for all methods (such as format).
     *
     * @return BulkCandles
     * @throws ApiException
     * @throws GuzzleException
     */
    public function bulkCandles(
        array $symbols = [],
        string $resolution = 'D',
        bool $snapshot = false,
        string $date = null,
        bool $adjust_splits = false,
        ?Parameters $parameters = null
    ): BulkCandles {
        if (empty($symbols) && !$snapshot) {
            throw new \InvalidArgumentException('Either symbols or snapshot must be set');
        }

        $symbols = implode(',', array_map('trim', $symbols));

        return new BulkCandles($this->execute("bulkcandles/{$resolution}/",
            [
                'symbols'      => $symbols,
                'snapshot'     => $snapshot,
                'date'         => $date,
                'adjustsplits' => $adjust_splits
            ]
            , $parameters));
    }

    /**
     * Get historical price candles for an index.
     *
     * @param string          $symbol           The company's ticker symbol.
     *
     * @param string          $from             The leftmost candle on a chart (inclusive). If you use countback, to is
     *                                          not required. Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param string|null     $to               The rightmost candle on a chart (inclusive). Accepted timestamp inputs:
     *                                          ISO 8601, unix, spreadsheet.
     *
     * @param string          $resolution       The duration of each candle.
     *                                          - Minutely Resolutions: (minutely, 1, 3, 5, 15, 30, 45, ...)
     *                                          - Hourly Resolutions: (hourly, H, 1H, 2H, ...)
     *                                          - Daily Resolutions: (daily, D, 1D, 2D, ...)
     *                                          - Weekly Resolutions: (weekly, W, 1W, 2W, ...)
     *                                          - Monthly Resolutions: (monthly, M, 1M, 2M, ...)
     *                                          - Yearly Resolutions:(yearly, Y, 1Y, 2Y, ...)
     *
     * @param int|null        $countback        Will fetch a number of candles before (to the left of) to. If you use
     *                                          from, countback is not required.
     *
     * @param string|null     $exchange         Use to specify the exchange of the ticker. This is useful when you need
     *                                          to specify a stock that quotes on several exchanges with the same
     *                                          symbol. You may specify the exchange using the EXCHANGE ACRONYM, MIC
     *                                          CODE, or two digit YAHOO FINANCE EXCHANGE CODE. If no exchange is
     *                                          specified symbols will be matched to US exchanges first.
     *
     * @param bool            $extended         Include extended hours trading sessions when returning intraday
     *                                          candles. Daily resolutions never return extended hours candles. The
     *                                          default is false.
     *
     * @param string|null     $country          Use to specify the country of the exchange (not the country of the
     *                                          company) in conjunction with the symbol argument. This argument is
     *                                          useful when you know the ticker symbol and the country of the exchange,
     *                                          but not the exchange code. Use the two digit ISO 3166 country code. If
     *                                          no country is specified, US exchanges will be assumed.
     *
     * @param bool            $adjust_splits    Adjust historical data for for historical splits and reverse splits.
     *                                          Market Data uses the CRSP methodology for adjustment. Daily candles
     *                                          default: true. Intraday candles default: false.
     *
     * @param bool            $adjust_dividends CAUTION: Adjusted dividend data is planned for the future, but not yet
     *                                          implemented. All data is currently returned unadjusted for dividends.
     *                                          Market Data uses the CRSP methodology for adjustment. Daily candles
     *                                          default: true. Intraday candles default: false.
     *
     * @param Parameters|null $parameters       Universal parameters for all methods (such as format).
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
        string $exchange = null,
        bool $extended = false,
        string $country = null,
        bool $adjust_splits = false,
        bool $adjust_dividends = false,
        ?Parameters $parameters = null
    ): Candles {
        return new Candles($this->execute("candles/{$resolution}/{$symbol}/", [
                'from'            => $from,
                'to'              => $to,
                'countback'       => $countback,
                'exchange'        => $exchange,
                'extended'        => $extended,
                'country'         => $country,
                'adjustsplits'    => $adjust_splits,
                'adjustdividends' => $adjust_dividends
            ]
            , $parameters));
    }

    /**
     * Get a real-time price quote for a stock.
     *
     * @param string          $symbol         The company's ticker symbol.
     *
     * @param bool            $fifty_two_week Enable the output of 52-week high and 52-week low data in the quote
     *                                        output. By default this parameter is false if omitted.
     *
     * @param Parameters|null $parameters     Universal parameters for all methods (such as format).
     *
     * @return Quote
     * @throws GuzzleException|ApiException
     */
    public function quote(string $symbol, bool $fifty_two_week = false, ?Parameters $parameters = null): Quote
    {
        return new Quote($this->execute("quotes/{$symbol}",
            ['52week' => $fifty_two_week], $parameters));
    }

    /**
     * Get real-time price quotes for multiple stocks by doing parallel requests.
     *
     * @param array           $symbols        The ticker symbols to return in the response.
     * @param bool            $fifty_two_week Enable the output of 52-week high and 52-week low data in the quote
     *                                        output.
     * @param Parameters|null $parameters     Universal parameters for all methods (such as format).
     *
     * @return Quotes
     * @throws \Throwable
     */
    public function quotes(array $symbols, bool $fifty_two_week = false, ?Parameters $parameters = null): Quotes
    {
        // Execute standard quotes in parallel
        $calls = [];
        foreach ($symbols as $symbol) {
            $calls[] = ["quotes/$symbol", ['52week' => $fifty_two_week]];
        }

        return new Quotes($this->execute_in_parallel($calls, $parameters));
    }

    /**
     * Get real-time price quotes for multiple stocks in a single API request.
     *
     * The bulkQuotes endpoint is designed to return hundreds of symbols at once or full market snapshots. Response
     * times for less than 50 symbols will be quicker using the standard quotes endpoint and sending your requests in
     * parallel.
     *
     * @param array           $symbols    The ticker symbols to return in the response, separated by commas. The
     *                                    symbols parameter may be omitted if the snapshot parameter is set to true.
     *
     * @param bool            $snapshot   Returns a full market snapshot with quotes for all symbols when set to true.
     *                                    The symbols parameter may be omitted if the snapshot parameter is set.
     *
     * @param Parameters|null $parameters Universal parameters for all methods (such as format).
     *
     * @return BulkQuotes
     * @throws GuzzleException
     * @throws \Exception
     */
    public function bulkQuotes(array $symbols = [], bool $snapshot = false, ?Parameters $parameters = null): BulkQuotes
    {
        if (empty($symbols) && !$snapshot) {
            throw new \InvalidArgumentException('Either symbols or snapshot must be set');
        }

        return new BulkQuotes($this->execute("bulkquotes",
            ['symbols' => implode(',', $symbols), 'snapshot' => $snapshot], $parameters));
    }

    /**
     * Get historical earnings per share data or a future earnings calendar for a stock.
     *
     * Premium subscription required.
     *
     * @param string          $symbol     The company's ticker symbol.
     *
     * @param string|null     $from       The earliest earnings report to include in the output. If you use countback,
     *                                    from is not required.
     *
     * @param string|null     $to         The latest earnings report to include in the output.
     *
     * @param int|null        $countback  Countback will fetch a specific number of earnings reports before to. If you
     *                                    use from, countback is not required.
     *
     * @param string|null     $date       Retrieve a specific earnings report by date.
     *
     * @param string|null     $datekey    Retrieve a specific earnings report by date and quarter. Example: 2023-Q4.
     *                                    This allows you to retrieve a 4th quarter value without knowing the company's
     *                                    specific fiscal year.
     *
     * @param Parameters|null $parameters Universal parameters for all methods (such as format).
     *
     * @return Earnings
     * @throws ApiException
     * @throws GuzzleException
     */
    public function earnings(
        string $symbol,
        string $from = null,
        string $to = null,
        int $countback = null,
        string $date = null,
        string $datekey = null,
        ?Parameters $parameters = null
    ): Earnings {
        if (is_null($from) && (is_null($countback) || is_null($to))) {
            throw new \InvalidArgumentException('Either `from` or `countback` and `to` must be set');
        }

        return new Earnings($this->execute("earnings/{$symbol}",
            compact('from', 'to', 'countback', 'date', 'datekey'), $parameters));
    }

    /**
     * Retrieve news articles for a given stock symbol within a specified date range.
     *
     * CAUTION: This endpoint is in beta.
     *
     * @param string          $symbol     The ticker symbol of the stock.
     *
     * @param string|null     $from       The earliest news to include in the output. If you use countback, from is not
     *                                    required.
     *
     * @param string|null     $to         The latest news to include in the output.
     *
     * @param int|null        $countback  Countback will fetch a specific number of news before to. If you use from,
     *                                    countback is not required.
     *
     * @param string|null     $date       Retrieve news for a specific day.
     *
     * @param Parameters|null $parameters Universal parameters for all methods (such as format).
     *
     * @return News
     * @throws \InvalidArgumentException
     */
    public function news(
        string $symbol,
        string $from = null,
        string $to = null,
        int $countback = null,
        string $date = null,
        ?Parameters $parameters = null
    ): News {
        if (is_null($from) && (is_null($countback) || is_null($to))) {
            throw new \InvalidArgumentException('Either `from` or `countback` and `to` must be set');
        }

        return new News($this->execute("news/{$symbol}",
            compact('from', 'to', 'countback', 'date'), $parameters));
    }
}
