<?php

namespace MarketDataApp\Endpoints\Responses\Options;

use Carbon\Carbon;

class Quote
{

    // The option symbol according to OCC symbology.
    public string $option_symbol;

    // The ask price.
    public float $ask;

    // The number of contracts offered at the ask price.
    public int $ask_size;

    // The bid price.
    public float $bid;

    // The number of contracts offered at the bid price.
    public int $bid_size;

    // The midpoint price between the ask and the bid, also known as the mark price.
    public float $mid;

    // The last price negotiated for this option contract at the time of this quote.
    public float $last;

    // The number of contracts negotiated during the trading day at the time of this quote.
    public int $volume;

    // The total number of contracts that have not yet been settled at the time of this quote.
    public int $open_interest;

    // The last price of the underlying security at the time of this quote.
    public float $underlying_price;

    // Specifies whether the option contract was in the money true or false at the time of this quote.
    public bool $in_the_money;

    // The intrinsic value of the option.
    public float $intrinsic_value;

    // The extrinsic value of the option.
    public float $extrinsic_value;

    // The implied volatility of the option.
    public float $implied_volatility;

    // The delta of the option.
    public float $delta;

    // The gamma of the option.
    public float $gamma;

    // The theta of the option.
    public float $theta;

    // The vega of the option.
    public float $vega;

    // The rho of the option.
    public float $rho;

    // The date and time of this quote snapshot in Unix time.
    public Carbon $updated;

    public function __construct(
        string $option_symbol,
        float $ask,
        int $ask_size,
        float $bid,
        int $bid_size,
        float $mid,
        float $last,
        int $volume,
        int $open_interest,
        float $underlying_price,
        bool $in_the_money,
        float $intrinsic_value,
        float $extrinsic_value,
        float $implied_volatility,
        float $delta,
        float $gamma,
        float $theta,
        float $vega,
        float $rho,
        Carbon $updated,
    ) {
        $this->option_symbol = $option_symbol;
        $this->ask = $ask;
        $this->ask_size = $ask_size;
        $this->bid = $bid;
        $this->bid_size = $bid_size;
        $this->mid = $mid;
        $this->last = $last;
        $this->volume = $volume;
        $this->open_interest = $open_interest;
        $this->underlying_price = $underlying_price;
        $this->in_the_money = $in_the_money;
        $this->intrinsic_value = $intrinsic_value;
        $this->extrinsic_value = $extrinsic_value;
        $this->implied_volatility = $implied_volatility;
        $this->delta = $delta;
        $this->gamma = $gamma;
        $this->theta = $theta;
        $this->vega = $vega;
        $this->rho = $rho;
        $this->updated = $updated;
    }
}
