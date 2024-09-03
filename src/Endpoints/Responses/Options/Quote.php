<?php

namespace MarketDataApp\Endpoints\Responses\Options;

use Carbon\Carbon;

/**
 * Represents a single option quote with associated data.
 */
class Quote
{

    /**
     * Constructs a new Quote instance.
     *
     * @param string $option_symbol      The option symbol according to OCC symbology.
     * @param float  $ask                The ask price.
     * @param int    $ask_size           The number of contracts offered at the ask price.
     * @param float  $bid                The bid price.
     * @param int    $bid_size           The number of contracts offered at the bid price.
     * @param float  $mid                The midpoint price between the ask and the bid, also known as the mark price.
     * @param float  $last               The last price negotiated for this option contract at the time of this quote.
     * @param int    $volume             The number of contracts negotiated during the trading day at the time of this
     *                                   quote.
     * @param int    $open_interest      The total number of contracts that have not yet been settled at the time of
     *                                   this quote.
     * @param float  $underlying_price   The last price of the underlying security at the time of this quote.
     * @param bool   $in_the_money       Specifies whether the option contract was in the money true or false at the
     *                                   time of this quote.
     * @param float  $intrinsic_value    The intrinsic value of the option.
     * @param float  $extrinsic_value    The extrinsic value of the option.
     * @param float  $implied_volatility The implied volatility of the option.
     * @param float  $delta              The delta of the option.
     * @param float  $gamma              The gamma of the option.
     * @param float  $theta              The theta of the option.
     * @param float  $vega               The vega of the option.
     * @param float  $rho                The rho of the option.
     * @param Carbon $updated            The date and time of this quote snapshot in Unix time.
     */
    public function __construct(
        public string $option_symbol,
        public float $ask,
        public int $ask_size,
        public float $bid,
        public int $bid_size,
        public float $mid,
        public float $last,
        public int $volume,
        public int $open_interest,
        public float $underlying_price,
        public bool $in_the_money,
        public float $intrinsic_value,
        public float $extrinsic_value,
        public float $implied_volatility,
        public float $delta,
        public float $gamma,
        public float $theta,
        public float $vega,
        public float $rho,
        public Carbon $updated,
    ) {
    }
}
