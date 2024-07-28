<?php

namespace MarketDataApp\Endpoints\Responses\Options;

use Carbon\Carbon;
use MarketDataApp\Enums\Side;

class OptionChainStrike
{

    public function __construct(
        // The option symbol according to OCC symbology.
        public string $option_symbol,

        // The ticker symbol of the underlying security.
        public string $underlying,

        // The option's expiration date in Unix time.
        public Carbon $expiration,

        // The response will be call or put.
        public Side $side,

        // The exercise price of the option.
        public float $strike,

        // The date the option was first traded.
        public Carbon $first_traded,

        // The number of days until the option expires.
        public int $dte,

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
        public float|null $last,

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
        public float|null $implied_volatility,

        // The delta of the option.
        public float|null $delta,

        // The gamma of the option.
        public float|null $gamma,

        // The theta of the option.
        public float|null $theta,

        // The vega of the option.
        public float|null $vega,

        // The rho of the option.
        public float|null $rho,

        // The date/time of the quote.
        public Carbon $updated,
    ) {
    }
}
