<?php

namespace MarketDataApp\Endpoints\Responses\Options;

use Carbon\Carbon;

class Quote
{

    public function __construct(
        // The option symbol according to OCC symbology.
        public string $option_symbol,

        // The ask price.
        public float $ask,

        // The number of contracts offered at the ask price.
        public int $ask_size,

        // The bid price.
        public float $bid,

        // The number of contracts offered at the bid price.
        public int $bid_size,

        // The midpoint price between the ask and the bid, also known as the mark price.
        public float $mid,

        // The last price negotiated for this option contract at the time of this quote.
        public float $last,

        // The number of contracts negotiated during the trading day at the time of this quote.
        public int $volume,

        // The total number of contracts that have not yet been settled at the time of this quote.
        public int $open_interest,

        // The last price of the underlying security at the time of this quote.
        public float $underlying_price,

        // Specifies whether the option contract was in the money true or false at the time of this quote.
        public bool $in_the_money,

        // The intrinsic value of the option.
        public float $intrinsic_value,

        // The extrinsic value of the option.
        public float $extrinsic_value,

        // The implied volatility of the option.
        public float $implied_volatility,

        // The delta of the option.
        public float $delta,

        // The gamma of the option.
        public float $gamma,

        // The theta of the option.
        public float $theta,

        // The vega of the option.
        public float $vega,

        // The rho of the option.
        public float $rho,

        // The date and time of this quote snapshot in Unix time.
        public Carbon $updated,
    ) {
    }
}
