<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;
use MarketDataApp\Traits\FormatsForDisplay;

/**
 * Class Quote
 *
 * Represents a stock quote and handles the response parsing for stock quote data.
 */
class Quote extends ResponseBase
{
    use FormatsForDisplay;

    /**
     * The status of the response. Will always be "ok" when there is data for the symbol requested.
     *
     * @var string
     */
    public string $status;

    /**
     * The symbol of the stock.
     *
     * @var string
     */
    public string $symbol;

    /**
     * The ask price of the stock.
     *
     * @var float
     */
    public float $ask;

    /**
     * The number of shares offered at the ask price.
     *
     * @var int
     */
    public int $ask_size;

    /**
     * The bid price.
     *
     * @var float
     */
    public float $bid;

    /**
     * The number of shares that may be sold at the bid price.
     *
     * @var int
     */
    public int $bid_size;

    /**
     * The midpoint price between the ask and the bid.
     *
     * @var float
     */
    public float $mid;

    /**
     * The last price the stock traded at.
     *
     * @var float
     */
    public float $last;

    /**
     * The difference in price in dollars (or the security's currency if different from dollars) compared to the closing
     * price of the previous day.
     *
     * @var float|null
     */
    public float|null $change;

    /**
     * The difference in price in percent, expressed as a decimal, compared to the closing price of the previous day.
     * For example, a 30% change will be represented as 0.30.
     *
     * @var float|null
     */
    public float|null $change_percent;

    /**
     * The 52-week high for the stock. This parameter is omitted unless the optional 52week request parameter is set to
     * true.
     *
     * @var float|null
     */
    public float|null $fifty_two_week_high = null;

    /**
     * The 52-week low for the stock. This parameter is omitted unless the optional 52week request parameter is set to
     * true.
     *
     * @var float|null
     */
    public float|null $fifty_two_week_low = null;

    /**
     * The number of shares traded during the current session.
     *
     * @var int
     */
    public int $volume;

    /**
     * The date/time of the current stock quote.
     *
     * @var Carbon
     */
    public Carbon $updated;

    /**
     * Constructs a new Quote object and parses the response data.
     *
     * @param object $response The raw response object to be parsed.
     */
    public function __construct(object $response)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }

        // Convert to array for easier access to keys with spaces (human-readable format)
        $responseArray = (array) $response;

        // Determine if this is human-readable format (has "Symbol" key) or regular format (has "s" status)
        $isHumanReadable = isset($responseArray['Symbol']);

        // Convert the response to this object.
        // Check for human-readable keys first (with spaces), then fall back to regular keys
        if ($isHumanReadable) {
            // Human-readable format - no "s" status field
            $this->status = 'ok'; // Human-readable format always returns data when successful
            $this->symbol = $responseArray['Symbol'][0];
            $this->ask = $responseArray['Ask'][0];
            $this->ask_size = $responseArray['Ask Size'][0];
            $this->bid = $responseArray['Bid'][0];
            $this->bid_size = $responseArray['Bid Size'][0];
            $this->mid = $responseArray['Mid'][0];
            $this->last = $responseArray['Last'][0];
            $this->change = $responseArray['Change $'][0];
            $this->change_percent = $responseArray['Change %'][0];
            $this->volume = $responseArray['Volume'][0];
            $this->updated = Carbon::parse($responseArray['Date'][0]);

            // 52-week high/low may not be present in human-readable format
            // Check if they exist (API returns "52 Week High" with space)
            if (isset($responseArray['52 Week High'][0])) {
                $this->fifty_two_week_high = $responseArray['52 Week High'][0];
            }
            if (isset($responseArray['52 Week Low'][0])) {
                $this->fifty_two_week_low = $responseArray['52 Week Low'][0];
            }
        } else {
            // Regular format
            $this->status = $response->s;

            // Handle no_data status (e.g., 204 No Content or no data available)
            if ($this->status === 'no_data') {
                return;
            }

            $this->symbol = $response->symbol[0];
            $this->ask = $response->ask[0];
            $this->ask_size = $response->askSize[0];
            $this->bid = $response->bid[0];
            $this->bid_size = $response->bidSize[0];
            $this->mid = $response->mid[0];
            $this->last = $response->last[0];
            $this->change = $response->change[0];
            $this->change_percent = $response->changepct[0];
            $this->volume = $response->volume[0];
            $this->updated = Carbon::parse($response->updated[0]);
            
            // Handle 52-week high/low
            if (isset($response->{'52weekHigh'}[0])) {
                $this->fifty_two_week_high = $response->{'52weekHigh'}[0];
            }
            if (isset($response->{'52weekLow'}[0])) {
                $this->fifty_two_week_low = $response->{'52weekLow'}[0];
            }
        }
    }

    /**
     * Returns a string representation of the quote.
     *
     * @return string Human-readable quote data.
     */
    public function __toString(): string
    {
        if (!$this->isJson()) {
            return "Quote - Non-JSON format, use getCsv() or getHtml()";
        }

        $lines = [];
        $lines[] = sprintf(
            "%s: %s (%s) Change: %s",
            $this->symbol,
            $this->formatCurrency($this->last),
            $this->formatPercent($this->change_percent),
            $this->formatChange($this->change)
        );
        $lines[] = sprintf(
            "  Bid: %s x %s  Ask: %s x %s  Mid: %s",
            $this->formatCurrency($this->bid),
            $this->formatNumber($this->bid_size),
            $this->formatCurrency($this->ask),
            $this->formatNumber($this->ask_size),
            $this->formatCurrency($this->mid)
        );
        $lines[] = sprintf(
            "  Volume: %s  Updated: %s",
            $this->formatVolume($this->volume),
            $this->formatDateTime($this->updated)
        );

        if ($this->fifty_two_week_high !== null || $this->fifty_two_week_low !== null) {
            $lines[] = sprintf(
                "  52-Week Range: %s - %s",
                $this->formatCurrency($this->fifty_two_week_low),
                $this->formatCurrency($this->fifty_two_week_high)
            );
        }

        return implode("\n", $lines);
    }
}
