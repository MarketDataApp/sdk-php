<?php

namespace MarketDataApp\Endpoints;

use Carbon\Carbon;
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

    public function expirations(): Expirations
    {
        // Stub
        return new Expirations();
    }

    public function lookup(): Lookup
    {
        // Stub
        return new Lookup();
    }

    public function strikes(): Strikes
    {
        // Stub
        return new Strikes();
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
     * @param Carbon|null $date Use to lookup a historical end of day options chain from a specific trading day. If no
     * date is specified the chain will be the most current chain available during market hours. When the market is
     * closed the chain will be from the last trading day.
     *
     * @param Carbon|Expiration $expiration
     * - Limits the option chain to a specific expiration date. Accepted date inputs: ISO 8601, unix, spreadsheet. This
     * parameter is only required if requesting a quote along with the chain.
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
     * @param Carbon|null $from Limit the option chain to expiration dates after from (inclusive). Should be combined
     * with to create a range.
     *
     * @param Carbon|null $to Limit the option chain to expiration dates before to (not inclusive). Should be combined
     * with from to create a range.
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
        Carbon $date = null,
        Carbon|Expiration $expiration = Expiration::ALL,
        Carbon $from = null,
        Carbon $to = null,
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
        return new OptionChains($this->client->execute(self::BASE_URL . "options/chain/$symbol", [
            'date' => $date,
            'expiration' => $expiration,
            'from' => $from,
            'to' => $to,
            'month' => $month,
            'year' => $year,
            'weekly' => $weekly,
            'monthly' => $monthly,
            'quarterly' => $quarterly,
            'nonstandard' => $non_standard,
            'dte' => $dte,
            'delta' => $delta,
            'side' => $side,
            'range' => $range,
            'strike' => $strike,
            'strikeLimit' => $strike_limit,
            'minBid' => $min_bid,
            'maxBid' => $max_bid,
            'minAsk' => $min_ask,
            'maxAsk' => $max_ask,
            'minBidAskSpread' => $min_bid_ask_spread,
            'maxBidAskSpreadPct' => $max_bid_ask_spread_pct,
            'minOpenInterest' => $min_open_interest,
            'minVolume' => $min_volume,
        ]));
    }

    public function quotes(): Quotes
    {
        // Stub
        return new Quotes();
    }
}
