<?php

namespace MarketDataApp\Endpoints;

use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\Expirations;
use MarketDataApp\Endpoints\Responses\Options\Lookup;
use MarketDataApp\Endpoints\Responses\Options\OptionChains;
use MarketDataApp\Endpoints\Responses\Options\Quotes;
use MarketDataApp\Endpoints\Responses\Options\Strikes;
use MarketDataApp\Enums\Expiration;
use MarketDataApp\Enums\Range;
use MarketDataApp\Enums\Side;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Settings;
use MarketDataApp\Traits\UniversalParameters;
use MarketDataApp\Traits\ValidatesInputs;

/**
 * Class Options
 *
 * Handles API requests related to options data.
 */
class Options
{

    use UniversalParameters;
    use ValidatesInputs;

    /**
     * The MarketDataApp API client instance.
     *
     * @var Client
     */
    private Client $client;

    /**
     * The base URL for options-related API endpoints.
     */
    public const BASE_URL = "v1/options/";

    /**
     * Options constructor.
     *
     * @param Client $client The MarketDataApp API client instance.
     */
    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * Get a list of current or historical option expiration dates for an underlying symbol. If no optional parameters
     * are used, the endpoint returns all expiration dates in the option chain.
     *
     * @param string          $symbol     The underlying ticker symbol for the options chain you wish to lookup.
     *
     * @param int|float|null  $strike     Limit the lookup of expiration dates to the strike provided. This will cause
     *                                    the endpoint to only return expiration dates that include this strike.
     *                                    Accepts decimal values (e.g., 12.5) for non-standard strikes.
     *
     * @param string|null     $date       Use to lookup a historical list of expiration dates from a specific previous
     *                                    trading day. If date is omitted the expiration dates will be from the current
     *                                    trading day during market hours or from the last trading day when the market
     *                                    is closed. Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param Parameters|null $parameters Universal parameters for all methods (such as format).
     *
     * @return Expirations
     *
     * @throws ApiException|GuzzleException
     */
    public function expirations(
        string $symbol,
        int|float|null $strike = null,
        ?string $date = null,
        ?Parameters $parameters = null
    ): Expirations {
        // Validate inputs
        $this->validateNonEmptyString($symbol, 'symbol');
        $symbol = trim($symbol);
        $this->validatePositiveNumber($strike, 'strike');

        return new Expirations($this->execute("expirations/$symbol/",
            compact('strike', 'date'), $parameters));
    }

    /**
     * Generate a properly formatted OCC option symbol based on the user's human-readable description of an option.
     * This endpoint converts text such as "AAPL 7/28/23 $200 Call" to OCC option symbol format: AAPL230728C00200000.
     *
     * @param string          $input      The human-readable string input that contains
     *                                    - (1) stock symbol
     *                                    - (2) strike
     *                                    - (3) expiration date
     *                                    - (4) option side (i.e. put or call).
     *
     * @param Parameters|null $parameters Universal parameters for all methods (such as format).
     *
     *   This endpoint will translate the user's input into a valid OCC option symbol.
     *   Example: "AAPL 7/28/23 $200 Call".
     *
     * @return Lookup
     */
    public function lookup(string $input, ?Parameters $parameters = null): Lookup
    {
        // Validate input
        $this->validateNonEmptyString($input, 'input');

        return new Lookup($this->execute("lookup/" . rawurlencode($input) . "/", [], $parameters));
    }

    /**
     * Get a list of current or historical options strikes for an underlying symbol. If no optional parameters are
     * used,
     * the endpoint returns the strikes for every expiration in the chain.
     *
     * @param string          $symbol     The underlying ticker symbol for the options chain you wish to lookup.
     *
     * @param string|null     $expiration Limit the lookup of strikes to options that expire on a specific expiration
     *                                    date.
     *
     * @param string|null     $date       Use to lookup a historical list of strikes from a specific previous trading
     *                                    day. If date is omitted the expiration dates will be from the current trading
     *                                    day during market hours or from the last trading day when the market is
     *                                    closed. Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param Parameters|null $parameters Universal parameters for all methods (such as format).
     *
     * @return Strikes
     *
     * @throws ApiException|GuzzleException
     */
    public function strikes(
        string $symbol,
        ?string $expiration = null,
        ?string $date = null,
        ?Parameters $parameters = null
    ): Strikes {
        // Validate inputs
        $this->validateNonEmptyString($symbol, 'symbol');
        $symbol = trim($symbol);

        return new Strikes($this->execute("strikes/$symbol/",
            compact('expiration', 'date'), $parameters));
    }

    /**
     * Get a current or historical end of day options chain for an underlying ticker symbol. Optional parameters allow
     * for extensive filtering of the chain. Use the optionSymbol returned from this endpoint to get quotes, greeks, or
     * other information using the other endpoints.
     *
     * @param string            $symbol                 The ticker symbol of the underlying asset.
     *
     * @param string|null       $date                   Use to lookup a historical end of day options chain from a
     *                                                  specific trading day. If no date is specified the chain will be
     *                                                  the most current chain available during market hours. When the
     *                                                  market is closed the chain will be from the last trading day.
     *                                                  Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param string|Expiration $expiration
     *                                                  - Limits the option chain to a specific expiration date.
     *                                                  Accepted date inputs: ISO 8601, unix, spreadsheet. This
     *                                                  parameter is only required if requesting a quote along with the
     *                                                  chain. Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * - If omitted the next monthly expiration for real-time quotes or the next monthly expiration relative to the
     * date
     * parameter for historical quotes will be returned.
     *
     * - Use the keyword all to return the complete option chain.
     *
     * CAUTION: Combining the all parameter with large options chains such as SPX, SPY, QQQ, etc. can cause you to
     * consume your requests very quickly. The full SPX option chain has more than 20,000 contracts. A request is
     * consumed for each contact you request with a price in the option chain.
     *
     * @param string|null       $from                   Limit the option chain to expiration dates after from
     *                                                  (inclusive). Should be combined with to create a range.
     *                                                  Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param string|null       $to                     Limit the option chain to expiration dates before to (not
     *                                                  inclusive). Should be combined with from to create a range.
     *                                                  Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param int|null          $month                  Limit the option chain to options that expire in a specific
     *                                                  month (1-12).
     *
     * @param int|null          $year                   Limit the option chain to options that expire in a specific
     *                                                  year.
     *
     * @param bool              $weekly                 Limit the option chain to weekly expirations by setting weekly
     *                                                  to true and omitting the monthly and quarterly parameters. If
     *                                                  set to false, no weekly expirations will be returned.
     *
     * @param bool              $monthly                Limit the option chain to standard monthly expirations by
     *                                                  setting monthly to true and omitting the weekly and quarterly
     *                                                  parameters. If set to false, no monthly expirations will be
     *                                                  returned.
     *
     * @param bool              $quarterly              Limit the option chain to quarterly expirations by setting
     *                                                  quarterly to true and omitting the weekly and monthly
     *                                                  parameters. If set to false, no quarterly expirations will be
     *                                                  returned.
     *
     * @param bool              $non_standard           Include non-standard contracts by nonstandard to true. If set
     *                                                  to false, no non-standard options expirations will be returned.
     *                                                  If no parameter is provided, the output will default to false.
     *
     * @param int|null          $dte                    Days to expiry. Limit the option chain to a single expiration
     *                                                  date closest to the dte provided. Should not be used together
     *                                                  with from and to. Take care before combining with weekly,
     *                                                  monthly, quarterly, since that will limit the expirations dte
     *                                                  can return. If you are using the date parameter, dte is
     *                                                  relative to the date provided.
     *
     * @param string|float|null $delta
     *                                                  - Limit the option chain to a single strike closest to the
     *                                                  delta provided. (e.g. .50)
     *                                                  - Limit the option chain to a specific set of deltas (e.g.
     *                                                  .60,.30)
     *                                                  - Limit the option chain to an open interval of strikes using a
     *                                                  logical expression (e.g. >.50)
     *                                                  - Limit the option chain to a closed interval of strikes by
     *                                                  specifying both endpoints. (e.g. .30-.60)
     *
     * TIP: Filter strikes using the aboslulte value of the delta. The values used will always return both sides of the
     * chain (e.g. puts & calls). This means you must filter using side to exclude puts or calls. Delta cannot be used
     * to filter the side of the chain, only the strikes.
     *
     * @param Side|null         $side                   Limit the option chain to either call or put. If omitted, both
     *                                                  sides will be returned.
     *
     * @param Range             $range                  Limit the option chain to strikes that are in the money, out of
     *                                                  the money, at the money, or include all. If omitted all options
     *                                                  will be returned.
     *
     * @param string|null       $strike
     *                                                  - Limit the option chain to options with the specific strike
     *                                                  specified. (e.g. 400)
     *                                                  - Limit the option chain to a specific set of strikes (e.g.
     *                                                  400,405)
     *                                                  - Limit the option chain to an open interval of strikes using a
     *                                                  logical expression (e.g. >400)
     *                                                  - Limit the option chain to a closed interval of strikes by
     *                                                  specifying both endpoints. (e.g. 400-410)
     *
     * @param int|null          $strike_limit           Limit the number of total strikes returned by the option chain.
     *                                                  For example, if a complete chain included 30 strikes and the
     *                                                  limit was set to 10, the 20 strikes furthest from the money
     *                                                  will be excluded from the response.
     *
     * TIP: If strikeLimit is combined with the range or side parameter, those parameters will be applied first. In the
     * above example, if the range were set to itm (in the money) and side set to call, all puts and out of the money
     * calls would be first excluded by the range parameter and then strikeLimit will return a maximum of 10 in the
     * money calls that are closest to the money. If the side parameter has not been used but range has been specified,
     * then strikeLimit will return the requested number of calls and puts for each side of the chain, but duplicating
     * the number of strikes that are received.
     *
     * @param float|null        $min_bid                Limit the option chain to options with a bid price greater than
     *                                                  or equal to the number provided.
     *
     * @param float|null        $max_bid                Limit the option chain to options with a bid price less than or
     *                                                  equal to the number provided.
     *
     * @param float|null        $min_ask                Limit the option chain to options with an ask price greater
     *                                                  than or equal to the number provided.
     *
     * @param float|null        $max_ask                Limit the option chain to options with an ask price less than
     *                                                  or equal to the number provided.
     *
     * @param float|null        $min_bid_ask_spread     Limit the option chain to options with a bid-ask spread less
     *                                                  than or equal to the number provided.
     *
     * @param float|null        $max_bid_ask_spread_pct Limit the option chain to options with a bid-ask spread less
     *                                                  than or equal to the percent provided (relative to the
     *                                                  underlying). For example, a value of 0.5% would exclude all
     *                                                  options trading with a bid-ask spread greater than $1.00 in an
     *                                                  underlying that trades at $200.
     *
     * @param int|null          $min_open_interest      Limit the option chain to options with an open interest greater
     *                                                  than or equal to the number provided.
     *
     * @param int|null          $min_volume             Limit the option chain to options with a volume transacted
     *                                                  greater than or equal to the number provided.
     *
     * @param Parameters|null   $parameters             Universal parameters for all methods (such as format).
     *
     * @return OptionChains
     *
     * @throws GuzzleException|ApiException
     */
    public function option_chain(
        string $symbol,
        ?string $date = null,
        string|Expiration|null $expiration = null,
        ?string $from = null,
        ?string $to = null,
        ?int $month = null,
        ?int $year = null,
        bool $weekly = true,
        bool $monthly = true,
        bool $quarterly = true,
        ?bool $non_standard = null,
        ?int $dte = null,
        string|float|null $delta = null,
        ?Side $side = null,
        Range $range = Range::ALL,
        ?string $strike = null,
        ?int $strike_limit = null,
        ?float $min_bid = null,
        ?float $max_bid = null,
        ?float $min_ask = null,
        ?float $max_ask = null,
        ?float $min_bid_ask_spread = null,
        ?float $max_bid_ask_spread_pct = null,
        ?int $min_open_interest = null,
        ?int $min_volume = null,
        ?Parameters $parameters = null
    ): OptionChains {
        // Validate inputs
        $this->validateNonEmptyString($symbol, 'symbol');
        $symbol = trim($symbol);

        // Validate date range
        $this->validateDateRange($from, $to);
        
        // Validate numeric ranges
        if ($month !== null && ($month < 1 || $month > 12)) {
            throw new \InvalidArgumentException("`month` must be between 1 and 12. Got: {$month}");
        }
        $this->validatePositiveInteger($year, 'year');
        $this->validatePositiveInteger($dte, 'dte');
        $this->validatePositiveInteger($strike_limit, 'strike_limit');
        $this->validatePositiveInteger($min_open_interest, 'min_open_interest');
        $this->validatePositiveInteger($min_volume, 'min_volume');
        
        // Validate min/max ranges
        $this->validateNumericRange($min_bid, $max_bid, 'min_bid', 'max_bid');
        $this->validateNumericRange($min_ask, $max_ask, 'min_ask', 'max_ask');

        $arguments = [
            'date'               => $date,
            'expiration'         => $expiration instanceof Expiration ? $expiration->value : $expiration,
            'from'               => $from,
            'to'                 => $to,
            'month'              => $month,
            'year'               => $year,
            'dte'                => $dte,
            'delta'              => $delta,
            'side'               => $side instanceof Side ? $side->value : $side,
            'range'              => $range instanceof Range ? $range->value : $range,
            'strike'             => $strike,
            'strikeLimit'        => $strike_limit,
            'minBid'             => $min_bid,
            'maxBid'             => $max_bid,
            'minAsk'             => $min_ask,
            'maxAsk'             => $max_ask,
            'minBidAskSpread'    => $min_bid_ask_spread,
            'maxBidAskSpreadPct' => $max_bid_ask_spread_pct,
            'minOpenInterest'    => $min_open_interest,
            'minVolume'          => $min_volume,
        ];

        // Boolean params: weekly, monthly, quarterly default to true on API, send 'false' when false
        if (!$weekly) {
            $arguments['weekly'] = 'false';
        }
        if (!$monthly) {
            $arguments['monthly'] = 'false';
        }
        if (!$quarterly) {
            $arguments['quarterly'] = 'false';
        }
        // nonstandard defaults to false on API, only send when explicitly set
        if ($non_standard !== null) {
            $arguments['nonstandard'] = $non_standard ? 'true' : 'false';
        }

        return new OptionChains($this->execute("chain/$symbol/", $arguments, $parameters));
    }

    /**
     * Get current or historical end of day quotes for one or more options contracts.
     *
     * When multiple option symbols are provided, requests are made concurrently using
     * a sliding window of up to 50 concurrent requests for optimal throughput.
     *
     * @param string|array    $option_symbols The option symbol(s) (as defined by the OCC) for the option(s) you wish
     *                                        to lookup. Use the current OCC option symbol format, even for historic
     *                                        options that quoted before the format change in 2010.
     *                                        Can be a single string or an array of strings for multiple symbols.
     *
     * @param string|null     $date           Use to lookup a historical end of day quote from a specific trading day.
     *                                        If no date is specified the quote will be the most current price available
     *                                        during market hours. When the market is closed the quote will be from the
     *                                        last trading day. Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param string|null     $from           Use to lookup a series of end of day quotes. From is the oldest (leftmost)
     *                                        date to return (inclusive). If from/to is not specified the quote will be
     *                                        the most current price available during market hours. When the market is
     *                                        closed the quote will be from the last trading day. Accepted timestamp
     *                                        inputs: ISO 8601, unix, spreadsheet.
     *
     * @param string|null     $to             Use to lookup a series of end of day quotes. To is the newest (rightmost)
     *                                        date to return (exclusive). If from/to is not specified the quote will be
     *                                        the most current price available during market hours. When the market is
     *                                        closed the quote will be from the last trading day. Accepted timestamp
     *                                        inputs: ISO 8601, unix, spreadsheet.
     *
     * @param Parameters|null $parameters     Universal parameters for all methods (such as format).
     *
     * @return Quotes
     *
     * @throws ApiException|GuzzleException|\Throwable
     */
    public function quotes(
        string|array $option_symbols,
        ?string $date = null,
        ?string $from = null,
        ?string $to = null,
        ?Parameters $parameters = null
    ): Quotes {
        // Validate date range
        $this->validateDateRange($from, $to);

        // Handle single symbol (string) - existing behavior
        if (is_string($option_symbols)) {
            $this->validateNonEmptyString($option_symbols, 'option_symbols');
            $option_symbols = trim($option_symbols);

            return new Quotes($this->execute("quotes/$option_symbols/",
                compact('date', 'from', 'to'), $parameters));
        }

        // Handle multiple symbols (array)
        return $this->quotesMultiple($option_symbols, $date, $from, $to, $parameters);
    }

    /**
     * Get quotes for multiple option symbols concurrently.
     *
     * Uses a sliding window of up to 50 concurrent requests. As each request completes,
     * the next one starts immediately for optimal throughput.
     *
     * @param array           $option_symbols Array of option symbols (OCC format).
     * @param string|null     $date           Historical date for EOD quotes.
     * @param string|null     $from           Start date for series of EOD quotes.
     * @param string|null     $to             End date for series of EOD quotes.
     * @param Parameters|null $parameters     Universal parameters.
     *
     * @return Quotes Merged quotes from all symbols.
     * @throws \Throwable
     */
    protected function quotesMultiple(
        array $option_symbols,
        ?string $date,
        ?string $from,
        ?string $to,
        ?Parameters $parameters
    ): Quotes {
        // Validate non-empty array with non-empty string elements
        if (empty($option_symbols)) {
            throw new \InvalidArgumentException('`option_symbols` array cannot be empty.');
        }

        foreach ($option_symbols as $symbol) {
            if (!is_string($symbol) || trim($symbol) === '') {
                throw new \InvalidArgumentException(
                    'All elements in `option_symbols` must be non-empty strings.'
                );
            }
        }

        // Deduplicate and normalize symbols
        $symbols = array_values(array_unique(array_map('trim', $option_symbols)));

        // If only one symbol after deduplication, delegate to single-symbol path
        if (count($symbols) === 1) {
            return new Quotes($this->execute("quotes/{$symbols[0]}/",
                compact('date', 'from', 'to'), $parameters));
        }

        // Check format to handle CSV/HTML specially
        $mergedParams = $this->mergeParameters($parameters);
        $format = $mergedParams->format;

        // HTML format is not supported for multi-symbol requests
        if ($format === \MarketDataApp\Enums\Format::HTML) {
            throw new \InvalidArgumentException(
                'HTML format is not supported for multi-symbol options quotes. ' .
                'Use JSON or CSV format instead.'
            );
        }

        // CSV format requires special handling to combine responses
        if ($format === \MarketDataApp\Enums\Format::CSV) {
            return $this->quotesMultipleCsv($symbols, $date, $from, $to, $parameters, $mergedParams);
        }

        // JSON format: existing behavior
        // Build API calls for all symbols
        $calls = [];
        foreach ($symbols as $symbol) {
            $calls[] = [
                "quotes/{$symbol}/",
                compact('date', 'from', 'to'),
            ];
        }

        // Execute all requests concurrently with partial failure tolerance
        // (sliding window up to MAX_CONCURRENT_REQUESTS)
        $failedRequests = [];
        $responses = $this->execute_in_parallel($calls, $parameters, $failedRequests);

        // If ALL requests failed, throw the first exception
        if (empty($responses) && !empty($failedRequests)) {
            throw reset($failedRequests);
        }

        // Merge all successful responses into a single Quotes object
        // (partial failures are tolerated - we return whatever data we got)
        return $this->mergeQuotesResponses($responses, $failedRequests, $symbols);
    }

    /**
     * Handle CSV format for multiple option symbols.
     *
     * Makes separate requests for each symbol, with headers=true on the first request
     * (unless user explicitly set add_headers=false) and headers=false on subsequent
     * requests. Combines all responses into a single CSV output.
     *
     * @param array           $symbols      Deduplicated and trimmed option symbols.
     * @param string|null     $date         Historical date for EOD quotes.
     * @param string|null     $from         Start date for series of EOD quotes.
     * @param string|null     $to           End date for series of EOD quotes.
     * @param Parameters|null $parameters   Original parameters from caller.
     * @param Parameters      $mergedParams Merged parameters with defaults applied.
     *
     * @return Quotes Quotes object containing combined CSV.
     * @throws \Throwable
     */
    protected function quotesMultipleCsv(
        array $symbols,
        ?string $date,
        ?string $from,
        ?string $to,
        ?Parameters $parameters,
        Parameters $mergedParams
    ): Quotes {
        // Determine if user explicitly requested no headers
        $userRequestedNoHeaders = $mergedParams->add_headers === false;

        // Build calls with appropriate header settings
        $calls = [];
        foreach ($symbols as $index => $symbol) {
            $callArgs = compact('date', 'from', 'to');

            // First request: headers=true unless user explicitly requested no headers
            // Subsequent requests: always headers=false
            if ($index === 0) {
                $callArgs['headers'] = $userRequestedNoHeaders ? 'false' : 'true';
            } else {
                $callArgs['headers'] = 'false';
            }

            $calls[] = [
                "quotes/{$symbol}/",
                $callArgs,
            ];
        }

        // Create modified parameters without add_headers (we're handling it manually per-call)
        $csvParams = new Parameters(
            format: $mergedParams->format,
            use_human_readable: $mergedParams->use_human_readable,
            mode: $mergedParams->mode,
            date_format: $mergedParams->date_format,
            columns: $mergedParams->columns,
            add_headers: null, // We handle headers per-call
            filename: null     // Cannot use filename with multi-symbol
        );

        // Execute all requests concurrently
        $failedRequests = [];
        $responses = $this->execute_in_parallel($calls, $csvParams, $failedRequests);

        // If ALL requests failed, throw the first exception
        if (empty($responses) && !empty($failedRequests)) {
            throw reset($failedRequests);
        }

        // Combine CSV responses
        $combinedCsv = '';
        ksort($responses); // Ensure responses are in original order
        foreach ($responses as $response) {
            if (isset($response->csv)) {
                $csv = $response->csv;
                // Trim trailing newlines to avoid extra blank lines when combining
                $csv = rtrim($csv, "\r\n");
                if ($csv !== '') {
                    $combinedCsv .= $csv . "\n";
                }
            }
        }

        // Create a response object with the combined CSV
        $combinedResponse = (object) ['csv' => $combinedCsv];

        return new Quotes($combinedResponse);
    }

    /**
     * Merge multiple quotes responses into a single Quotes object.
     *
     * @param array $responses      Array of response objects from execute_in_parallel, keyed by call index.
     * @param array $failedRequests Array of exceptions from failed requests, keyed by call index.
     * @param array $symbols        Original symbols array for error reporting.
     *
     * @return Quotes Merged quotes response.
     */
    protected function mergeQuotesResponses(array $responses, array $failedRequests = [], array $symbols = []): Quotes
    {
        $allQuotes = [];
        $overallStatus = 'no_data';
        $nextTime = null;
        $prevTime = null;

        foreach ($responses as $response) {
            $quotesResponse = new Quotes($response);

            if ($quotesResponse->status === 'ok') {
                $overallStatus = 'ok';
                $allQuotes = array_merge($allQuotes, $quotesResponse->quotes);
            } elseif ($quotesResponse->status === 'no_data') {
                // Track earliest next_time
                if (isset($quotesResponse->next_time)) {
                    if ($nextTime === null || $quotesResponse->next_time->lt($nextTime)) {
                        $nextTime = $quotesResponse->next_time;
                    }
                }
                // Track latest prev_time
                if (isset($quotesResponse->prev_time)) {
                    if ($prevTime === null || $quotesResponse->prev_time->gt($prevTime)) {
                        $prevTime = $quotesResponse->prev_time;
                    }
                }
            }
        }

        // Build errors array for failed requests
        $errors = [];
        foreach ($failedRequests as $index => $exception) {
            $symbol = $symbols[$index] ?? "unknown (index $index)";
            $errors[$symbol] = $exception->getMessage();
        }

        return Quotes::createMerged($overallStatus, $allQuotes, $nextTime, $prevTime, $errors);
    }
}
