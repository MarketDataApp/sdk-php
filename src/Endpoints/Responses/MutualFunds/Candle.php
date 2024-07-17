<?php

namespace MarketDataApp\Endpoints\Responses\MutualFunds;

use Carbon\Carbon;

class Candle
{

    // Open price.
    public float $open;

    // High price.
    public float $high;

    // Low price.
    public float $low;

    // Close price.
    public float $close;

    // Candle time (Unix timestamp, UTC). Daily, weekly, monthly, yearly candles are returned without times.
    public Carbon $timestamp;

    public function __construct(float $open, float $high, float $low, float $close, Carbon $timestamp)
    {
        $this->open = $open;
        $this->high = $high;
        $this->low = $low;
        $this->close = $close;
        $this->timestamp = $timestamp;
    }
}
