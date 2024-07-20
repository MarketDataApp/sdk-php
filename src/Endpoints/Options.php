<?php

namespace MarketDataApp\Endpoints;

use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Options\Expirations;
use MarketDataApp\Endpoints\Responses\Options\Lookup;
use MarketDataApp\Endpoints\Responses\Options\OptionChains;
use MarketDataApp\Endpoints\Responses\Options\Quotes;
use MarketDataApp\Endpoints\Responses\Options\Strikes;
use MarketDataApp\Enums\Expiration;
use MarketDataApp\Enums\Range;
use MarketDataApp\Enums\Side;
use MarketDataApp\Exceptions\ApiException;

class Options
{

    private Client $client;
    public const BASE_URL = "v1/options/";

    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * Get a list of current or historical option expiration dates for an underlying symbol. If no optional parameters
     * are used, the endpoint returns all expiration dates in the option chain.
     *
     * @param string $symbol The underlying ticker symbol for the options chain you wish to lookup.
     *
     * @param int|null $strike Limit the lookup of expiration dates to the strike provided. This will cause the endpoint to
     * only return expiration dates that include this strike.
     *
     * @param string|null $date Use to lookup a historical list of expiration dates from a specific previous trading
     * day. If date is omitted the expiration dates will be from the current trading day during market hours or from the
     * last trading day when the market is closed. Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @throws ApiException|GuzzleException
     */
    public function expirations(string $symbol, int $strike = null, string $date = null): Expirations
    {
        return new Expirations($this->client->execute(self::BASE_URL . "expirations/$symbol",
            compact('strike', 'date')));
    }

    /**
     * Generate a properly formatted OCC option symbol based on the user's human-readable description of an option. This endpoint converts text such as "AAPL 7/28/23 $200 Call" to OCC option symbol format: AAPL230728C00200000.
     *
     * @param string $input The human-readable string input that contains
     *   - (1) stock symbol
     *   - (2) strike
     *   - (3) expiration date
     *   - (4) option side (i.e. put or call).
     *
     *   This endpoint will translate the user's input into a valid OCC option symbol.
     *   Example: "AAPL 7/28/23 $200 Call".
     */
    public function lookup(string $input): Lookup
    {
        return new Lookup($this->client->execute(self::BASE_URL . "lookup/" . $input));
    }

    /**
     * Get a list of current or historical options strikes for an underlying symbol. If no optional parameters are used,
     * the endpoint returns the strikes for every expiration in the chain.
     *
     * @param string $symbol The underlying ticker symbol for the options chain you wish to lookup.
     *
     * @param string|null $expiration Limit the lookup of strikes to options that expire on a specific expiration date.
     *
     * @param string|null $date Use to lookup a historical list of strikes from a specific previous trading day. If date
     * is omitted the expiration dates will be from the current trading day during market hours or from the last trading
     * day when the market is closed. Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @throws ApiException|GuzzleException
     */
    public function strikes(string $symbol, string $expiration = null, string $date = null): Strikes
    {
        return new Strikes($this->client->execute(self::BASE_URL . "strikes/$symbol",
            compact('expiration', 'date')));
    }

    /**
     * Get a current or historical end of day options chain for an underlying ticker symbol. Optional parameters allow
     * for extensive filtering of the chain. Use the optionSymbol returned from this endpoint to get quotes, greeks, or
     * other information using the other endpoints.
     *
     * CAUTION: The from, to, month, year, weekly, monthly, and quarterly filtering parameters are not yet supported for
     * real-time quotes. If you are requesting a real-time quote you must request a single expiration date or request
     * all expirations.
     *
     * @param string $symbol The ticker symbol of the underlying asset.
     *
     * @param string|null $date Use to lookup a historical end of day options chain from a specific trading day. If no
     * date is specified the chain will be the most current chain available during market hours. When the market is
     * closed the chain will be from the last trading day. Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param string|Expiration $expiration
     * - Limits the option chain to a specific expiration date. Accepted date inputs: ISO 8601, unix, spreadsheet. This
     * parameter is only required if requesting a quote along with the chain. Accepted timestamp inputs: ISO 8601, unix,
     * spreadsheet.
     *
     * - If omitted the next monthly expiration for real-time quotes or the next monthly expiration relative to the date
     * parameter for historical quotes will be returned.
     *
     * - Use the keyword all to return the complete option chain.
     *
     * CAUTION: Combining the all parameter with large options chains such as SPX, SPY, QQQ, etc. can cause you to
     * consume your requests very quickly. The full SPX option chain has more than 20,000 contracts. A request is
     * consumed for each contact you request with a price in the option chain.
     *
     * @param string|null $from Limit the option chain to expiration dates after from (inclusive). Should be combined
     * with to create a range. Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param string|null $to Limit the option chain to expiration dates before to (not inclusive). Should be combined
     * with from to create a range. Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param int|null $month Limit the option chain to options that expire in a specific month (1-12).
     *
     * @param int|null $year Limit the option chain to options that expire in a specific year.
     *
     * @param bool $weekly Limit the option chain to weekly expirations by setting weekly to true and omitting the
     * monthly and quarterly parameters. If set to false, no weekly expirations will be returned.
     *
     * @param bool $monthly Limit the option chain to standard monthly expirations by setting monthly to true and
     * omitting the weekly and quarterly parameters. If set to false, no monthly expirations will be returned.
     *
     * @param bool $quarterly Limit the option chain to quarterly expirations by setting quarterly to true and omitting
     * the weekly and monthly parameters. If set to false, no quarterly expirations will be returned.
     *
     * @param bool $non_standard Include non-standard contracts by nonstandard to true. If set to false, no non-standard
     * options expirations will be returned. If no parameter is provided, the output will default to false.
     *
     * @param int|null $dte Days to expiry. Limit the option chain to a single expiration date closest to the dte
     * provided. Should not be used together with from and to. Take care before combining with weekly, monthly,
     * quarterly, since that will limit the expirations dte can return. If you are using the date parameter, dte is
     * relative to the date provided.
     *
     * @param float|null $delta
     *   - Limit the option chain to a single strike closest to the delta provided. (e.g. .50)
     *   - Limit the option chain to a specific set of deltas (e.g. .60,.30)
     *   - Limit the option chain to an open interval of strikes using a logical expression (e.g. >.50)
     *   - Limit the option chain to a closed interval of strikes by specifying both endpoints. (e.g. .30-.60)
     *
     * TIP: Filter strikes using the aboslulte value of the delta. The values used will always return both sides of the
     * chain (e.g. puts & calls). This means you must filter using side to exclude puts or calls. Delta cannot be used
     * to filter the side of the chain, only the strikes.
     *
     * @param Side|null $side Limit the option chain to either call or put. If omitted, both sides will be returned.
     *
     * @param Range $range Limit the option chain to strikes that are in the money, out of the money, at the money, or
     * include all. If omitted all options will be returned.
     *
     * @param string|null $strike
     *   - Limit the option chain to options with the specific strike specified. (e.g. 400)
     *   - Limit the option chain to a specific set of strikes (e.g. 400,405)
     *   - Limit the option chain to an open interval of strikes using a logical expression (e.g. >400)
     *   - Limit the option chain to a closed interval of strikes by specifying both endpoints. (e.g. 400-410)
     *
     * @param int|null $strike_limit Limit the number of total strikes returned by the option chain. For example, if a
     * complete chain included 30 strikes and the limit was set to 10, the 20 strikes furthest from the money will be
     * excluded from the response.
     *
     * TIP: If strikeLimit is combined with the range or side parameter, those parameters will be applied first. In the
     * above example, if the range were set to itm (in the money) and side set to call, all puts and out of the money
     * calls would be first excluded by the range parameter and then strikeLimit will return a maximum of 10 in the
     * money calls that are closest to the money. If the side parameter has not been used but range has been specified,
     * then strikeLimit will return the requested number of calls and puts for each side of the chain, but duplicating
     * the number of strikes that are received.
     *
     * @param float|null $min_bid Limit the option chain to options with a bid price greater than or equal to the number
     * provided.
     *
     * @param float|null $max_bid Limit the option chain to options with a bid price less than or equal to the number
     * provided.
     *
     * @param float|null $min_ask Limit the option chain to options with an ask price greater than or equal to the
     * number provided.
     *
     * @param float|null $max_ask Limit the option chain to options with an ask price less than or equal to the number
     * provided.
     *
     * @param float|null $min_bid_ask_spread Limit the option chain to options with a bid-ask spread less than or equal
     * to the number provided.
     *
     * @param float|null $max_bid_ask_spread_pct Limit the option chain to options with a bid-ask spread less than or
     * equal to the percent provided (relative to the underlying). For example, a value of 0.5% would exclude all
     * options trading with a bid-ask spread greater than $1.00 in an underlying that trades at $200.
     *
     * @param int|null $min_open_interest Limit the option chain to options with an open interest greater than or equal
     * to the number provided.
     *
     * @param int|null $min_volume Limit the option chain to options with a volume transacted greater than or equal to
     * the number provided.
     *
     * @throws GuzzleException|ApiException
     */
    public function option_chain(
        string $symbol,
        string $date = null,
        string|Expiration $expiration = Expiration::ALL,
        string $from = null,
        string $to = null,
        int $month = null,
        int $year = null,
        bool $weekly = true,
        bool $monthly = true,
        bool $quarterly = true,
        bool $non_standard = true,
        int $dte = null,
        float $delta = null,
        Side $side = null,
        Range $range = Range::ALL,
        string $strike = null,
        int $strike_limit = null,
        float $min_bid = null,
        float $max_bid = null,
        float $min_ask = null,
        float $max_ask = null,
        float $min_bid_ask_spread = null,
        float $max_bid_ask_spread_pct = null,
        int $min_open_interest = null,
        int $min_volume = null,
    ): OptionChains {
        return new OptionChains($this->client->execute(self::BASE_URL . "chain/$symbol", [
            'date'               => $date,
            'expiration'         => $expiration instanceof Expiration ? $expiration->value : $expiration,
            'from'               => $from,
            'to'                 => $to,
            'month'              => $month,
            'year'               => $year,
            'weekly'             => $weekly,
            'monthly'            => $monthly,
            'quarterly'          => $quarterly,
            'nonstandard'        => $non_standard,
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
        ]));
    }

    /**
     * Get a current or historical end of day quote for a single options contract.
     *
     * @param string $option_symbol The option symbol (as defined by the OCC) for the option you wish to lookup. Use the
     * current OCC option symbol format, even for historic options that quoted before the format change in 2010.
     *
     * @param string|null $date Use to lookup a historical end of day quote from a specific trading day. If no date is
     * specified the quote will be the most current price available during market hours. When the market is closed the
     * quote will be from the last trading day. Accepted timestamp inputs: ISO 8601, unix, spreadsheet.
     *
     * @param string|null $from Use to lookup a series of end of day quotes. From is the oldest (leftmost) date to
     * return (inclusive). If from/to is not specified the quote will be the most current price available during market
     * hours. When the market is closed the quote will be from the last trading day. Accepted timestamp inputs: ISO
     * 8601, unix, spreadsheet.
     *
     * @param string|null $to Use to lookup a series of end of day quotes. From is the newest (rightmost) date to return
     * (exclusive). If from/to is not specified the quote will be the most current price available during market hours.
     * When the market is closed the quote will be from the last trading day. Accepted timestamp inputs: ISO 8601, unix,
     * spreadsheet.
     *
     * @throws ApiException|GuzzleException
     */
    public function quotes(string $option_symbol, string $date = null, string $from = null, string $to = null): Quotes
    {
        return new Quotes($this->client->execute(self::BASE_URL . "quotes/$option_symbol/",
            compact('date', 'from', 'to')));
    }
}
