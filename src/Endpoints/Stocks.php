<?php

namespace MarketDataApp\Endpoints;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\BulkCandles;
use MarketDataApp\Endpoints\Responses\Stocks\Candle;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Endpoints\Responses\Stocks\Earnings;
use MarketDataApp\Endpoints\Responses\Stocks\News;
use MarketDataApp\Endpoints\Responses\Stocks\Prices;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Endpoints\Responses\Stocks\Quotes;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Settings;
use MarketDataApp\Traits\UniversalParameters;
use MarketDataApp\Traits\ValidatesInputs;

/**
 * Stocks class for handling stock-related API endpoints.
 */
class Stocks
{

    use UniversalParameters;
    use ValidatesInputs;

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
     * Check if a resolution is intraday (minutely or hourly).
     *
     * Intraday resolutions include:
     * - Minutely: 1, 3, 5, 15, 30, 45, minutely, or any number followed by optional suffix
     * - Hourly: H, 1H, 2H, hourly, or any number followed by H
     *
     * @param string $resolution The resolution to check.
     *
     * @return bool True if the resolution is intraday, false otherwise.
     */
    protected function isIntradayResolution(string $resolution): bool
    {
        $resolution = strtolower(trim($resolution));

        // Hourly resolutions: H, 1H, 2H, hourly, etc.
        if ($resolution === 'h' || $resolution === 'hourly' || preg_match('/^\d+h$/i', $resolution)) {
            return true;
        }

        // Minutely resolutions: 1, 3, 5, 15, 30, 45, minutely, or just numbers
        if ($resolution === 'minutely' || preg_match('/^\d+$/', $resolution)) {
            return true;
        }

        // Daily, weekly, monthly, yearly are NOT intraday
        return false;
    }

    /**
     * Check if a date string can be parsed as an absolute date.
     *
     * This is used to determine if we can calculate date ranges for automatic splitting.
     * Relative dates (like "today", "-5 days") or unparseable dates will return false.
     * Unix timestamps (pure digit strings) are accepted.
     *
     * @param string $date The date string to check.
     *
     * @return bool True if the date can be parsed, false otherwise.
     */
    protected function isParseableDate(string $date): bool
    {
        $date = trim($date);

        // Check for common relative date patterns that Carbon would accept but we don't want
        $relativePatterns = [
            '/^today$/i',
            '/^yesterday$/i',
            '/^tomorrow$/i',
            '/^now$/i',
            '/^[+-]\d+\s*(day|week|month|year)/i',
            '/^\d+\s*(day|week|month|year)/i',
        ];

        foreach ($relativePatterns as $pattern) {
            if (preg_match($pattern, $date)) {
                return false;
            }
        }

        // Check for Unix timestamp (9-10 digit number representing seconds since epoch)
        // Valid range: 1970-01-01 to ~2286-11-20
        if (preg_match('/^\d{9,10}$/', $date)) {
            $timestamp = (int) $date;
            // Reasonable Unix timestamp range (1970-2100)
            return $timestamp >= 0 && $timestamp <= 4102444800;
        }

        // Try to parse as ISO 8601 or similar format
        try {
            Carbon::parse($date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Split a date range into year-long chunks for concurrent fetching.
     *
     * This method splits a date range into chunks of approximately 1 year each,
     * which is the maximum recommended range for intraday data requests.
     *
     * @param string $from The start date (ISO 8601 format).
     * @param string $to   The end date (ISO 8601 format).
     *
     * @return array Array of [from, to] date pairs representing each chunk.
     */
    protected function splitDateRangeIntoYearChunks(string $from, string $to): array
    {
        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);

        $chunks = [];
        $currentStart = $fromDate->copy()->startOfDay();
        $isFirstChunk = true;

        while ($currentStart->lt($toDate)) {
            $currentEnd = $currentStart->copy()->addYear()->subDay()->endOfDay();

            // For the first chunk, use original 'from' timestamp to preserve time-of-day
            $chunkFrom = $isFirstChunk ? $from : $currentStart->toDateString();
            $isFirstChunk = false;

            // Don't go past the original end date
            $isLastChunk = $currentEnd->gte($toDate);
            if ($isLastChunk) {
                // For the last chunk, use original 'to' timestamp to preserve time-of-day
                $chunks[] = [$chunkFrom, $to];
                break;
            }

            $chunks[] = [$chunkFrom, $currentEnd->toDateString()];

            $currentStart = $currentEnd->copy()->addDay()->startOfDay();
        }

        return $chunks;
    }

    /**
     * Determine if a candles request needs automatic date range splitting.
     *
     * Splitting is needed when:
     * 1. Resolution is intraday (minutely or hourly)
     * 2. Both from and to dates are parseable
     * 3. The date range spans more than 1 year
     * 4. countback is not specified (we can't split countback requests)
     *
     * @param string      $resolution The candle resolution.
     * @param string      $from       The start date.
     * @param string|null $to         The end date.
     * @param int|null    $countback  The countback value.
     *
     * @return bool True if automatic splitting is needed, false otherwise.
     */
    protected function needsAutomaticSplitting(
        string $resolution,
        string $from,
        ?string $to,
        ?int $countback
    ): bool {
        // Can't split countback requests
        if ($countback !== null) {
            return false;
        }

        // Need a 'to' date to calculate range
        if ($to === null) {
            return false;
        }

        // Only split intraday resolutions
        if (!$this->isIntradayResolution($resolution)) {
            return false;
        }

        // Both dates must be parseable
        if (!$this->isParseableDate($from) || !$this->isParseableDate($to)) {
            return false;
        }

        // Check if range spans more than 1 year
        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);
        $diffInDays = $fromDate->diffInDays($toDate);

        // More than 365 days = more than 1 year
        return $diffInDays > 365;
    }

    /**
     * Merge multiple candle responses into a single Candles object.
     *
     * This method combines candles from multiple API responses, typically from
     * concurrent requests for different date chunks. The candles are sorted by
     * timestamp to maintain chronological order.
     *
     * @param array $responses Array of raw response objects from the API.
     *
     * @return Candles A single Candles object containing all candles.
     */
    protected function mergeCandleResponses(array $responses): Candles
    {
        $allCandles = [];
        $overallStatus = 'no_data';
        $nextTime = null;

        foreach ($responses as $response) {
            // Parse each response
            $candlesResponse = new Candles($response);

            if ($candlesResponse->status === 'ok') {
                $overallStatus = 'ok';
                foreach ($candlesResponse->candles as $candle) {
                    $allCandles[] = $candle;
                }
            } elseif ($candlesResponse->status === 'no_data' && isset($candlesResponse->next_time)) {
                // Keep track of the earliest next_time if we have no data
                if ($nextTime === null || $candlesResponse->next_time < $nextTime) {
                    $nextTime = $candlesResponse->next_time;
                }
            }
        }

        // Sort candles by timestamp
        usort($allCandles, function (Candle $a, Candle $b) {
            return $a->timestamp->timestamp <=> $b->timestamp->timestamp;
        });

        // Remove duplicates (same timestamp)
        $uniqueCandles = [];
        $seenTimestamps = [];
        foreach ($allCandles as $candle) {
            $ts = $candle->timestamp->timestamp;
            if (!isset($seenTimestamps[$ts])) {
                $seenTimestamps[$ts] = true;
                $uniqueCandles[] = $candle;
            }
        }

        // Create a merged Candles object
        return Candles::createMerged($overallStatus, $uniqueCandles, $nextTime);
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
        ?string $date = null,
        ?bool $adjust_splits = null,
        ?Parameters $parameters = null
    ): BulkCandles {
        if (empty($symbols) && !$snapshot) {
            throw new \InvalidArgumentException('Either symbols or snapshot must be set');
        }

        // Validate resolution
        $this->validateResolution($resolution);

        $symbolsString = implode(',', array_map('trim', $symbols));

        $arguments = [
            'date' => $date,
        ];
        if ($symbolsString !== '') {
            $arguments['symbols'] = $symbolsString;
        }
        if ($snapshot) {
            $arguments['snapshot'] = 'true';
        }
        if ($adjust_splits !== null) {
            $arguments['adjustsplits'] = $adjust_splits ? 'true' : 'false';
        }

        return new BulkCandles($this->execute("bulkcandles/{$resolution}/", $arguments, $parameters));
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
        ?string $to = null,
        string $resolution = 'D',
        ?int $countback = null,
        ?string $exchange = null,
        bool $extended = false,
        ?string $country = null,
        ?bool $adjust_splits = null,
        ?bool $adjust_dividends = null,
        ?Parameters $parameters = null
    ): Candles {
        // Validate inputs
        $this->validateNonEmptyString($symbol, 'symbol');
        $this->validateResolution($resolution);
        $this->validateDateRange($from, $to, $countback);

        // Check if automatic splitting is needed for large intraday date ranges
        if ($this->needsAutomaticSplitting($resolution, $from, $to, $countback)) {
            return $this->candlesConcurrent(
                $symbol,
                $from,
                $to,
                $resolution,
                $exchange,
                $extended,
                $country,
                $adjust_splits,
                $adjust_dividends,
                $parameters
            );
        }

        // Standard single request
        $arguments = [
            'from'      => $from,
            'to'        => $to,
            'countback' => $countback,
            'exchange'  => $exchange,
            'country'   => $country,
        ];
        if ($extended) {
            $arguments['extended'] = 'true';
        }
        if ($adjust_splits !== null) {
            $arguments['adjustsplits'] = $adjust_splits ? 'true' : 'false';
        }
        if ($adjust_dividends !== null) {
            $arguments['adjustdividends'] = $adjust_dividends ? 'true' : 'false';
        }

        return new Candles($this->execute("candles/{$resolution}/{$symbol}/", $arguments, $parameters));
    }

    /**
     * Fetch candles concurrently by splitting date range into year-long chunks.
     *
     * This method is called automatically when:
     * 1. Resolution is intraday (minutely or hourly)
     * 2. The date range spans more than 1 year
     * 3. countback is not specified
     *
     * The date range is split into year-long chunks, which are fetched concurrently
     * (up to MAX_CONCURRENT_REQUESTS at a time). The responses are then merged
     * into a single Candles object.
     *
     * @param string          $symbol           The stock symbol.
     * @param string          $from             The start date.
     * @param string          $to               The end date.
     * @param string          $resolution       The candle resolution.
     * @param string|null     $exchange         The exchange code.
     * @param bool            $extended         Include extended hours.
     * @param string|null     $country          The country code.
     * @param bool            $adjust_splits    Adjust for splits.
     * @param bool            $adjust_dividends Adjust for dividends.
     * @param Parameters|null $parameters       Universal parameters.
     *
     * @return Candles The merged candles response.
     * @throws \Throwable
     */
    protected function candlesConcurrent(
        string $symbol,
        string $from,
        string $to,
        string $resolution,
        ?string $exchange,
        bool $extended,
        ?string $country,
        ?bool $adjust_splits,
        ?bool $adjust_dividends,
        ?Parameters $parameters
    ): Candles {
        // Split the date range into year-long chunks
        $chunks = $this->splitDateRangeIntoYearChunks($from, $to);

        // Build the API calls for parallel execution
        $calls = [];
        foreach ($chunks as $chunk) {
            $arguments = [
                'from'     => $chunk[0],
                'to'       => $chunk[1],
                'exchange' => $exchange,
                'country'  => $country,
            ];
            if ($extended) {
                $arguments['extended'] = 'true';
            }
            if ($adjust_splits !== null) {
                $arguments['adjustsplits'] = $adjust_splits ? 'true' : 'false';
            }
            if ($adjust_dividends !== null) {
                $arguments['adjustdividends'] = $adjust_dividends ? 'true' : 'false';
            }

            $calls[] = [
                "candles/{$resolution}/{$symbol}/",
                $arguments,
            ];
        }

        // Execute all requests in parallel with partial failure tolerance
        // (some chunks may 404 if historical data doesn't exist for that range)
        $failedRequests = [];
        $responses = $this->execute_in_parallel($calls, $parameters, $failedRequests);

        // If ALL requests failed, throw the first exception
        if (empty($responses) && !empty($failedRequests)) {
            throw reset($failedRequests);
        }

        // Merge all successful responses into a single Candles object
        // (partial failures are tolerated - we return whatever data we got)
        return $this->mergeCandleResponses($responses);
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
        // Validate symbol
        $this->validateNonEmptyString($symbol, 'symbol');

        $arguments = [];
        if ($fifty_two_week) {
            $arguments['52week'] = 'true';
        }

        return new Quote($this->execute("quotes/{$symbol}/", $arguments, $parameters));
    }

    /**
     * Get real-time price quotes for multiple stocks in a single API request.
     *
     * @param array           $symbols        The ticker symbols to return in the response.
     * @param bool            $fifty_two_week Enable the output of 52-week high and 52-week low data in the quote
     *                                        output.
     * @param Parameters|null $parameters     Universal parameters for all methods (such as format).
     *
     * @return Quotes
     * @throws GuzzleException|ApiException
     */
    public function quotes(array $symbols, bool $fifty_two_week = false, ?Parameters $parameters = null): Quotes
    {
        // Validate symbols array
        $this->validateSymbols($symbols);

        // Build comma-separated symbols string
        $symbolsString = implode(',', array_map('trim', $symbols));

        $arguments = ['symbols' => $symbolsString];
        if ($fifty_two_week) {
            $arguments['52week'] = 'true';
        }

        return new Quotes($this->execute("quotes/", $arguments, $parameters));
    }

    /**
     * Get real-time midpoint prices for one or more stocks.
     *
     * This endpoint returns real-time prices for stocks, using the SmartMid model.
     * The endpoint supports both single symbol (path parameter) and multiple symbols (query parameter) formats.
     *
     * @param string|array    $symbols     The ticker symbol(s). Can be a single string or an array of strings.
     * @param bool            $extended    Control the inclusion of extended hours data in the price output.
     *                                     Defaults to true if omitted.
     *                                     - When set to true, the most recent price is always returned, without regard
     *                                       to whether the market is open for primary trading or extended hours trading.
     *                                     - When set to false, only prices from the primary trading session are returned.
     *                                       When the market is closed or in extended hours, a historical price from the
     *                                       last closing bell of the primary trading session is returned instead of an
     *                                       extended hours price.
     * @param Parameters|null $parameters  Universal parameters for all methods (such as format).
     *
     * @return Prices
     * @throws GuzzleException|ApiException
     */
    public function prices(string|array $symbols, bool $extended = true, ?Parameters $parameters = null): Prices
    {
        // Validate symbols
        if (is_string($symbols)) {
            $this->validateNonEmptyString($symbols, 'symbols');
        } else {
            $this->validateSymbols($symbols);
        }

        // extended defaults to true on the API, so only send when false
        $arguments = [];
        if (!$extended) {
            $arguments['extended'] = 'false';
        }

        if (is_string($symbols)) {
            // Single symbol: use path format prices/{symbol}/
            return new Prices($this->execute("prices/{$symbols}/", $arguments, $parameters));
        } else {
            // Multiple symbols: use query format prices/?symbols={comma-separated}
            $symbolsString = implode(',', array_map('trim', $symbols));
            $arguments['symbols'] = $symbolsString;
            return new Prices($this->execute("prices/", $arguments, $parameters));
        }
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
        ?string $from = null,
        ?string $to = null,
        ?int $countback = null,
        ?string $date = null,
        ?string $datekey = null,
        ?Parameters $parameters = null
    ): Earnings {
        // Validate inputs
        $this->validateNonEmptyString($symbol, 'symbol');
        
        if (is_null($from) && (is_null($countback) || is_null($to))) {
            throw new \InvalidArgumentException('Either `from` or `countback` and `to` must be set');
        }

        // Validate date range and countback
        $this->validateDateRange($from, $to, $countback);

        return new Earnings($this->execute("earnings/{$symbol}/",
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
        ?string $from = null,
        ?string $to = null,
        ?int $countback = null,
        ?string $date = null,
        ?Parameters $parameters = null
    ): News {
        // Validate inputs
        $this->validateNonEmptyString($symbol, 'symbol');
        
        if (is_null($from) && (is_null($countback) || is_null($to))) {
            throw new \InvalidArgumentException('Either `from` or `countback` and `to` must be set');
        }

        // Validate date range and countback
        $this->validateDateRange($from, $to, $countback);

        return new News($this->execute("news/{$symbol}/",
            compact('from', 'to', 'countback', 'date'), $parameters));
    }
}
