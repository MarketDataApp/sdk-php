<?php

namespace MarketDataApp\Endpoints\Responses\Options;

use Carbon\Carbon;
use MarketDataApp\Enums\Side;
use MarketDataApp\Traits\FormatsForDisplay;

/**
 * Represents a single option quote with associated data.
 */
class OptionQuote
{
    use FormatsForDisplay;

    /**
     * Constructs a new OptionQuote instance.
     *
     * @param string     $option_symbol      The option symbol according to OCC symbology.
     * @param string     $underlying         The ticker symbol of the underlying security.
     * @param Carbon     $expiration         The option's expiration date in Unix time.
     * @param Side       $side               The response will be call or put.
     * @param float      $strike             The exercise price of the option.
     * @param Carbon     $first_traded       The date the option was first traded.
     * @param int        $dte                The number of days until the option expires.
     * @param float      $ask                The ask price.
     * @param int        $ask_size           The number of contracts offered at the ask price.
     * @param float      $bid                The bid price.
     * @param int        $bid_size           The number of contracts offered at the bid price.
     * @param float      $mid                The midpoint price between the ask and the bid, also known as the mark
     *                                       price.
     * @param float|null $last               The last price negotiated for this option contract at the time of this
     *                                       quote.
     * @param int        $volume             The number of contracts negotiated during the trading day at the time of
     *                                       this quote.
     * @param int        $open_interest      The total number of contracts that have not yet been settled at the time
     *                                       of this quote.
     * @param float      $underlying_price   The last price of the underlying security at the time of this quote.
     * @param bool       $in_the_money       Specifies whether the option contract was in the money true or false at
     *                                       the time of this quote.
     * @param float      $intrinsic_value    The intrinsic value of the option.
     * @param float      $extrinsic_value    The extrinsic value of the option.
     * @param float|null $implied_volatility The implied volatility of the option.
     * @param float|null $delta              The delta of the option.
     * @param float|null $gamma              The gamma of the option.
     * @param float|null $theta              The theta of the option.
     * @param float|null $vega               The vega of the option.
     * @param Carbon     $updated            The date/time of the quote.
     */
    public function __construct(
        public string $option_symbol,
        public string $underlying,
        public Carbon $expiration,
        public Side $side,
        public float $strike,
        public Carbon $first_traded,
        public int $dte,
        public float $ask,
        public int $ask_size,
        public float $bid,
        public int $bid_size,
        public float $mid,
        public float|null $last,
        public int $volume,
        public int $open_interest,
        public float $underlying_price,
        public bool $in_the_money,
        public float $intrinsic_value,
        public float $extrinsic_value,
        public float|null $implied_volatility,
        public float|null $delta,
        public float|null $gamma,
        public float|null $theta,
        public float|null $vega,
        public Carbon $updated,
    ) {
    }

    /**
     * Returns a string representation of the option quote.
     *
     * @return string Human-readable option quote data.
     */
    public function __toString(): string
    {
        $sideStr = strtoupper($this->side->value);
        $itmStr = $this->in_the_money ? 'ITM' : 'OTM';

        $lines = [];
        $lines[] = sprintf("%s (%s) %s", $this->option_symbol, $sideStr, $itmStr);
        $lines[] = sprintf(
            "  Underlying: %s @ %s",
            $this->underlying,
            $this->formatCurrency($this->underlying_price)
        );
        $lines[] = sprintf(
            "  Strike: %s  Exp: %s (%d DTE)",
            $this->formatCurrency($this->strike),
            $this->formatDate($this->expiration),
            $this->dte
        );
        $lines[] = sprintf(
            "  Bid: %s x %s  Ask: %s x %s  Mid: %s  Last: %s",
            $this->formatCurrency($this->bid),
            $this->formatNumber($this->bid_size),
            $this->formatCurrency($this->ask),
            $this->formatNumber($this->ask_size),
            $this->formatCurrency($this->mid),
            $this->formatCurrency($this->last)
        );
        $lines[] = sprintf(
            "  IV: %s  Delta: %s  Gamma: %s  Theta: %s  Vega: %s",
            $this->formatPercentRaw($this->implied_volatility),
            $this->formatGreek($this->delta),
            $this->formatGreek($this->gamma),
            $this->formatGreek($this->theta),
            $this->formatGreek($this->vega)
        );
        $lines[] = sprintf(
            "  Intrinsic: %s  Extrinsic: %s",
            $this->formatCurrency($this->intrinsic_value),
            $this->formatCurrency($this->extrinsic_value)
        );
        $lines[] = sprintf(
            "  Volume: %s  OI: %s",
            $this->formatNumber($this->volume),
            $this->formatNumber($this->open_interest)
        );
        $lines[] = sprintf(
            "  First Traded: %s  Updated: %s",
            $this->formatDate($this->first_traded),
            $this->formatDateTime($this->updated)
        );

        return implode("\n", $lines);
    }
}
