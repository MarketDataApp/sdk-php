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
     * Parse a user-provided date string into a Carbon instance.
     *
     * Handles both standard date formats and unix timestamps (9-10 digit strings).
     * This should be used whenever parsing user input that could be a unix timestamp.
     *
     * @param string $date The date string to parse (ISO 8601, unix timestamp, etc.).
     *
     * @return Carbon The parsed Carbon instance.
     */
    protected function parseUserDate(string $date): Carbon
    {
        $date = trim($date);

        // Check for Unix timestamp (9-10 digit number representing seconds since epoch)
        if (preg_match('/^\d{9,10}$/', $date)) {
            return Carbon::createFromTimestamp((int) $date);
        }

        // Check for spreadsheet serial number (Excel/Google Sheets dates are typically < 100000)
        // Excel epoch is 1899-12-30, serial number represents days since then
        if (is_numeric($date)) {
            $num = (float) $date;
            if ($num > 0 && $num < 100000) {
                $excelEpoch = Carbon::parse('1899-12-30');
                return $excelEpoch->addDays((int) $num);
            }
        }

        return Carbon::parse($date);
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

        // Check for spreadsheet serial number (Excel/Google Sheets dates are typically < 100000)
        // These represent days since 1899-12-30 (Excel epoch)
        if (is_numeric($date)) {
            $num = (float) $date;
            if ($num > 0 && $num < 100000) {
                return true;
            }
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
        $fromDate = $this->parseUserDate($from);
        $toDate = $this->parseUserDate($to);

        $chunks = [];
        $currentStart = $fromDate->copy()->startOfDay();
        $isFirstChunk = true;

        while ($currentStart->lte($toDate)) {
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
        $fromDate = $this->parseUserDate($from);
        $toDate = $this->parseUserDate($to);
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
     * @param array  $responses Array of raw response objects from the API.
     * @param string $symbol    The symbol to associate with all candles.
     *
     * @return Candles A single Candles object containing all candles.
     */
    protected function mergeCandleResponses(array $responses, string $symbol): Candles
    {
        $allCandles = [];
        $overallStatus = 'no_data';
        $nextTime = null;

        foreach ($responses as $response) {
            // Parse each response, passing the symbol so candles have it set
            $candlesResponse = new Candles($response, $symbol);

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
     * @api
     * @link https://www.marketdata.app/docs/api/stocks/bulkcandles API Documentation
     * @see  candles() For historical candles of a single symbol
     *
     * @example
     * // Get bulk candles for multiple symbols
     * $candles = $client->stocks->bulkCandles(['AAPL', 'MSFT', 'GOOGL']);
     *
     * // Get a market snapshot of all symbols
     * $snapshot = $client->stocks->bulkCandles(snapshot: true);
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

        // Validate symbols if provided
        if (!empty($symbols)) {
            $this->validateSymbols($symbols);
        }

        // Validate resolution
        $this->validateResolution($resolution);

        // Deduplicate and trim symbols to avoid redundant API calls
        $symbolsString = implode(',', array_unique(array_map('trim', $symbols)));

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
     * Get historical price candles for a stock.
     *
     * @api
     * @link https://www.marketdata.app/docs/api/stocks/candles API Documentation
     * @see  bulkCandles() For bulk daily candles across multiple symbols
     *
     * @example
     * // Get daily candles for AAPL
     * $candles = $client->stocks->candles('AAPL', '2024-01-01', '2024-01-31');
     *
     * // Get 5-minute candles with extended hours
     * $candles = $client->stocks->candles('AAPL', '2024-01-15', '2024-01-15', '5', extended: true);
     *
     * @param string          $symbol        The company's ticker symbol.
     *
     * @param string          $from          The leftmost candle on a chart (inclusive). If you use countback, to is
     *                                       not required. Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param string|null     $to            The rightmost candle on a chart (inclusive). Accepted timestamp inputs:
     *                                       ISO 8601, unix, spreadsheet.
     *
     * @param string          $resolution    The duration of each candle.
     *                                       - Minutely Resolutions: (minutely, 1, 3, 5, 15, 30, 45, ...)
     *                                       - Hourly Resolutions: (hourly, H, 1H, 2H, ...)
     *                                       - Daily Resolutions: (daily, D, 1D, 2D, ...)
     *                                       - Weekly Resolutions: (weekly, W, 1W, 2W, ...)
     *                                       - Monthly Resolutions: (monthly, M, 1M, 2M, ...)
     *                                       - Yearly Resolutions:(yearly, Y, 1Y, 2Y, ...)
     *
     * @param int|null        $countback     Will fetch a number of candles before (to the left of) to. If you use
     *                                       from, countback is not required.
     *
     * @param bool            $extended      Include extended hours trading sessions when returning intraday
     *                                       candles. Daily resolutions never return extended hours candles. The
     *                                       default is false.
     *
     * @param bool            $adjust_splits Adjust historical data for for historical splits and reverse splits.
     *                                       Market Data uses the CRSP methodology for adjustment. Daily candles
     *                                       default: true. Intraday candles default: false.
     *
     * @param Parameters|null $parameters    Universal parameters for all methods (such as format).
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
        bool $extended = false,
        ?bool $adjust_splits = null,
        ?Parameters $parameters = null
    ): Candles {
        // Validate inputs
        $this->validateNonEmptyString($symbol, 'symbol');
        $symbol = trim($symbol);
        $this->validateResolution($resolution);
        $this->validateDateRange($from, $to, $countback);

        // Check if automatic splitting is needed for large intraday date ranges
        if ($this->needsAutomaticSplitting($resolution, $from, $to, $countback)) {
            return $this->candlesConcurrent(
                $symbol,
                $from,
                $to,
                $resolution,
                $extended,
                $adjust_splits,
                $parameters
            );
        }

        // Standard single request
        $arguments = [
            'from'      => $from,
            'to'        => $to,
            'countback' => $countback,
        ];
        if ($extended) {
            $arguments['extended'] = 'true';
        }
        if ($adjust_splits !== null) {
            $arguments['adjustsplits'] = $adjust_splits ? 'true' : 'false';
        }

        return new Candles($this->execute("candles/{$resolution}/{$symbol}/", $arguments, $parameters), $symbol);
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
     * @param string          $symbol        The stock symbol.
     * @param string          $from          The start date.
     * @param string          $to            The end date.
     * @param string          $resolution    The candle resolution.
     * @param bool            $extended      Include extended hours.
     * @param bool|null       $adjust_splits Adjust for splits.
     * @param Parameters|null $parameters    Universal parameters.
     *
     * @return Candles The merged candles response.
     * @throws \Throwable
     */
    protected function candlesConcurrent(
        string $symbol,
        string $from,
        string $to,
        string $resolution,
        bool $extended,
        ?bool $adjust_splits,
        ?Parameters $parameters
    ): Candles {
        // Check format to handle CSV/HTML specially
        $mergedParams = $this->mergeParameters($parameters);
        $format = $mergedParams->format;

        // HTML format is not supported for split requests (API limitation)
        if ($format === \MarketDataApp\Enums\Format::HTML) {
            throw new \InvalidArgumentException(
                'HTML format is not supported for intraday candle requests spanning more than 1 year. ' .
                'Use JSON or CSV format instead, or reduce the date range.'
            );
        }

        // CSV format requires special handling to combine responses
        if ($format === \MarketDataApp\Enums\Format::CSV) {
            return $this->candlesConcurrentCsv(
                $symbol,
                $from,
                $to,
                $resolution,
                $extended,
                $adjust_splits,
                $parameters,
                $mergedParams
            );
        }

        // Split the date range into year-long chunks
        $chunks = $this->splitDateRangeIntoYearChunks($from, $to);

        // Build the API calls for parallel execution
        $calls = [];
        foreach ($chunks as $chunk) {
            $arguments = [
                'from' => $chunk[0],
                'to'   => $chunk[1],
            ];
            if ($extended) {
                $arguments['extended'] = 'true';
            }
            if ($adjust_splits !== null) {
                $arguments['adjustsplits'] = $adjust_splits ? 'true' : 'false';
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
        return $this->mergeCandleResponses($responses, $symbol);
    }

    /**
     * Handle CSV format for concurrent candle requests.
     *
     * Makes separate requests for each date chunk, with headers=true on ALL requests
     * (unless user explicitly set add_headers=false). This ensures headers are present
     * even if the first chunk fails. Duplicate headers are stripped when combining.
     *
     * @param string          $symbol        The stock symbol.
     * @param string          $from          The start date.
     * @param string          $to            The end date.
     * @param string          $resolution    The candle resolution.
     * @param bool            $extended      Include extended hours.
     * @param bool|null       $adjust_splits Adjust for splits.
     * @param Parameters|null $parameters    Original parameters from caller.
     * @param Parameters      $mergedParams  Merged parameters with defaults applied.
     *
     * @return Candles Candles object containing combined CSV.
     * @throws \Throwable
     */
    protected function candlesConcurrentCsv(
        string $symbol,
        string $from,
        string $to,
        string $resolution,
        bool $extended,
        ?bool $adjust_splits,
        ?Parameters $parameters,
        Parameters $mergedParams
    ): Candles {
        // Validate that filename is not provided with parallel requests
        if ($mergedParams->filename !== null) {
            throw new \InvalidArgumentException(
                'filename parameter cannot be used with parallel requests. ' .
                'Each parallel response would conflict writing to the same file. ' .
                'Use filename only with single requests, or use saveToFile() method on individual response objects.'
            );
        }

        // Split the date range into year-long chunks
        $chunks = $this->splitDateRangeIntoYearChunks($from, $to);

        // Determine if user explicitly requested no headers
        $userRequestedNoHeaders = $mergedParams->add_headers === false;

        // Build calls - request headers on ALL calls (unless user explicitly requested no headers).
        // We'll strip duplicate header rows when combining responses.
        // This ensures headers are present even if the first request fails.
        $calls = [];
        foreach ($chunks as $chunk) {
            $arguments = [
                'from' => $chunk[0],
                'to'   => $chunk[1],
            ];
            if ($extended) {
                $arguments['extended'] = 'true';
            }
            if ($adjust_splits !== null) {
                $arguments['adjustsplits'] = $adjust_splits ? 'true' : 'false';
            }
            $arguments['headers'] = $userRequestedNoHeaders ? 'false' : 'true';

            $calls[] = [
                "candles/{$resolution}/{$symbol}/",
                $arguments,
            ];
        }

        // Create modified parameters without add_headers (we're handling it manually per-call)
        $csvParams = new Parameters(
            format: $mergedParams->format,
            use_human_readable: $mergedParams->use_human_readable,
            mode: $mergedParams->mode,
            maxage: $mergedParams->maxage,
            date_format: $mergedParams->date_format,
            columns: $mergedParams->columns,
            add_headers: null, // We handle headers per-call
            filename: null     // Cannot use filename with split requests
        );

        // Execute all requests concurrently
        $failedRequests = [];
        $responses = $this->execute_in_parallel($calls, $csvParams, $failedRequests);

        // If ALL requests failed via exceptions, throw the first exception
        if (empty($responses) && !empty($failedRequests)) {
            throw reset($failedRequests);
        }

        // Combine CSV responses, filtering out JSON error responses
        // (API returns JSON even when CSV is requested if there's an error)
        $combinedCsv = '';
        $validResponseCount = 0;
        $lastErrorMessage = null;
        $headerRow = null;
        ksort($responses); // Ensure responses are in original order
        foreach ($responses as $response) {
            if (isset($response->csv)) {
                $csv = $response->csv;
                // Trim trailing newlines to avoid extra blank lines when combining
                $csv = rtrim($csv, "\r\n");

                // Check if this is a JSON error response instead of valid CSV
                // API returns JSON for errors even when CSV format is requested
                // Use ltrim() to handle responses with leading whitespace
                if ($csv !== '' && str_starts_with(ltrim($csv), '{')) {
                    $decoded = json_decode($csv);
                    if (isset($decoded->s) && $decoded->s === 'error') {
                        // This is a JSON error response, skip it but record the error
                        $lastErrorMessage = $decoded->errmsg ?? 'Unknown error';
                        continue;
                    }
                }

                if ($csv !== '') {
                    // Only strip duplicate header rows when headers are actually present.
                    // When add_headers=false, all rows are data rows - don't strip anything.
                    if ($userRequestedNoHeaders) {
                        // No headers - just concatenate all data rows
                        $combinedCsv .= $csv . "\n";
                    } elseif ($headerRow === null) {
                        // First valid response - capture header and include entire response
                        $firstNewline = strpos($csv, "\n");
                        if ($firstNewline !== false) {
                            $headerRow = substr($csv, 0, $firstNewline);
                        }
                        $combinedCsv .= $csv . "\n";
                    } else {
                        // Subsequent responses - strip header row if present
                        $firstNewline = strpos($csv, "\n");
                        if ($firstNewline !== false) {
                            $firstLine = substr($csv, 0, $firstNewline);
                            // Trim whitespace for robust comparison
                            if (trim($firstLine) === trim($headerRow)) {
                                // Skip the header row
                                $csv = substr($csv, $firstNewline + 1);
                            }
                        }
                        if ($csv !== '') {
                            $combinedCsv .= $csv . "\n";
                        }
                    }
                    $validResponseCount++;
                }
            }
        }

        // If ALL responses were errors (no valid CSV data), throw an exception
        if ($validResponseCount === 0) {
            if ($lastErrorMessage !== null) {
                throw new \MarketDataApp\Exceptions\ApiException(
                    message: $lastErrorMessage
                );
            } elseif (!empty($failedRequests)) {
                throw reset($failedRequests);
            } else {
                throw new \MarketDataApp\Exceptions\ApiException(
                    message: 'No data available for the requested date range'
                );
            }
        }

        // Create a response object with the combined CSV
        $combinedResponse = (object) ['csv' => $combinedCsv];

        return new Candles($combinedResponse, $symbol);
    }

    /**
     * Get a real-time price quote for a stock.
     *
     * @api
     * @link https://www.marketdata.app/docs/api/stocks/quotes API Documentation
     * @see  quotes() For quotes of multiple symbols in a single request
     * @see  prices() For SmartMid midpoint prices
     *
     * @example
     * // Get a real-time quote
     * $quote = $client->stocks->quote('AAPL');
     * echo $quote->last; // Last traded price
     *
     * // Get quote with 52-week high/low
     * $quote = $client->stocks->quote('AAPL', fifty_two_week: true);
     *
     * @param string          $symbol         The company's ticker symbol.
     *
     * @param bool            $fifty_two_week Enable the output of 52-week high and 52-week low data in the quote
     *                                        output. By default this parameter is false if omitted.
     *
     * @param bool            $extended       Control the inclusion of extended hours data in the quote output.
     *                                        Defaults to true if omitted.
     *                                        - When set to true, the most recent quote is always returned, without
     *                                          regard to whether the market is open for primary trading or extended
     *                                          hours trading.
     *                                        - When set to false, only quotes from the primary trading session are
     *                                          returned. When the market is closed or in extended hours, a historical
     *                                          quote from the last closing bell of the primary trading session is
     *                                          returned instead of an extended hours quote.
     *
     * @param Parameters|null $parameters     Universal parameters for all methods (such as format).
     *
     * @return Quote
     * @throws GuzzleException|ApiException
     */
    public function quote(
        string $symbol,
        bool $fifty_two_week = false,
        bool $extended = true,
        ?Parameters $parameters = null
    ): Quote {
        // Validate symbol
        $this->validateNonEmptyString($symbol, 'symbol');
        $symbol = trim($symbol);

        $arguments = [];
        if ($fifty_two_week) {
            $arguments['52week'] = 'true';
        }
        // extended defaults to true on the API, so only send when false
        if (!$extended) {
            $arguments['extended'] = 'false';
        }

        return new Quote($this->execute("quotes/{$symbol}/", $arguments, $parameters));
    }

    /**
     * Get real-time price quotes for multiple stocks in a single API request.
     *
     * @api
     * @link https://www.marketdata.app/docs/api/stocks/quotes API Documentation
     * @see  quote() For a single symbol quote
     * @see  bulkCandles() For bulk daily candle data
     *
     * @example
     * // Get quotes for multiple symbols
     * $quotes = $client->stocks->quotes(['AAPL', 'MSFT', 'GOOGL']);
     * foreach ($quotes->quotes as $q) {
     *     echo "{$q->symbol}: \${$q->last}\n";
     * }
     *
     * @param array           $symbols        The ticker symbols to return in the response.
     * @param bool            $fifty_two_week Enable the output of 52-week high and 52-week low data in the quote
     *                                        output.
     * @param bool            $extended       Control the inclusion of extended hours data in the quote output.
     *                                        Defaults to true if omitted.
     *                                        - When set to true, the most recent quote is always returned, without
     *                                          regard to whether the market is open for primary trading or extended
     *                                          hours trading.
     *                                        - When set to false, only quotes from the primary trading session are
     *                                          returned. When the market is closed or in extended hours, a historical
     *                                          quote from the last closing bell of the primary trading session is
     *                                          returned instead of an extended hours quote.
     * @param Parameters|null $parameters     Universal parameters for all methods (such as format).
     *
     * @return Quotes
     * @throws GuzzleException|ApiException
     */
    public function quotes(
        array $symbols,
        bool $fifty_two_week = false,
        bool $extended = true,
        ?Parameters $parameters = null
    ): Quotes {
        // Validate symbols array
        $this->validateSymbols($symbols);

        // Build comma-separated symbols string
        // Deduplicate and trim symbols to avoid redundant API calls
        $symbolsString = implode(',', array_unique(array_map('trim', $symbols)));

        $arguments = ['symbols' => $symbolsString];
        if ($fifty_two_week) {
            $arguments['52week'] = 'true';
        }
        // extended defaults to true on the API, so only send when false
        if (!$extended) {
            $arguments['extended'] = 'false';
        }

        return new Quotes($this->execute("quotes/", $arguments, $parameters));
    }

    /**
     * Get real-time midpoint prices for one or more stocks.
     *
     * This endpoint returns real-time prices for stocks, using the SmartMid model.
     * The endpoint supports both single symbol (path parameter) and multiple symbols (query parameter) formats.
     *
     * @api
     * @link https://www.marketdata.app/docs/api/stocks/prices API Documentation
     * @see  quote() For full quote data including bid/ask
     *
     * @example
     * // Get price for a single symbol
     * $prices = $client->stocks->prices('AAPL');
     *
     * // Get prices for multiple symbols
     * $prices = $client->stocks->prices(['AAPL', 'MSFT', 'GOOGL']);
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
            $symbols = trim($symbols);
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
            // Deduplicate and trim symbols to avoid redundant API calls
        $symbolsString = implode(',', array_unique(array_map('trim', $symbols)));
            $arguments['symbols'] = $symbolsString;
            return new Prices($this->execute("prices/", $arguments, $parameters));
        }
    }

    /**
     * Get historical earnings per share data or a future earnings calendar for a stock.
     *
     * Premium subscription required.
     *
     * @api
     * @link https://www.marketdata.app/docs/api/stocks/earnings API Documentation
     *
     * @example
     * // Get upcoming earnings
     * $earnings = $client->stocks->earnings('AAPL');
     *
     * // Get historical earnings for a date range
     * $earnings = $client->stocks->earnings('AAPL', from: '2023-01-01', to: '2023-12-31');
     *
     * @param string          $symbol     The company's ticker symbol.
     *
     * @param string|null     $from       The earliest earnings report to include in the output. Optional - if omitted
     *                                    without countback, returns recent/upcoming earnings.
     *
     * @param string|null     $to         The latest earnings report to include in the output. Optional.
     *
     * @param int|null        $countback  Countback will fetch a specific number of earnings reports before to. Optional.
     *
     * @param string|null     $date       Retrieve a specific earnings report by date. Optional.
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
        ?Parameters $parameters = null
    ): Earnings {
        // Validate inputs
        $this->validateNonEmptyString($symbol, 'symbol');
        $symbol = trim($symbol);

        // Validate date range and countback if provided
        $this->validateDateRange($from, $to, $countback);

        return new Earnings($this->execute("earnings/{$symbol}/",
            compact('from', 'to', 'countback', 'date'), $parameters));
    }

    /**
     * Retrieve news articles for a given stock symbol.
     *
     * CAUTION: This endpoint is in beta.
     *
     * @api
     * @link https://www.marketdata.app/docs/api/stocks/news API Documentation
     *
     * @example
     * // Get recent news for a symbol
     * $news = $client->stocks->news('AAPL');
     *
     * // Get news for a specific date range
     * $news = $client->stocks->news('AAPL', from: '2024-01-01', to: '2024-01-31');
     *
     * @param string          $symbol     The ticker symbol of the stock.
     *
     * @param string|null     $from       The earliest news to include in the output. Optional - if omitted without
     *                                    countback, returns recent news.
     *
     * @param string|null     $to         The latest news to include in the output. Optional.
     *
     * @param int|null        $countback  Countback will fetch a specific number of news before to. Optional.
     *
     * @param string|null     $date       Retrieve news for a specific day. Optional.
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
        $symbol = trim($symbol);

        // Validate date range and countback if provided
        $this->validateDateRange($from, $to, $countback);

        return new News($this->execute("news/{$symbol}/",
            compact('from', 'to', 'countback', 'date'), $parameters));
    }
}
