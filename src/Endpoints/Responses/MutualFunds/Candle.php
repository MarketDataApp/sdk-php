<?php

namespace MarketDataApp\Endpoints\Responses\MutualFunds;

use Carbon\Carbon;

class Candle
{

    public function __construct(
        // Open price.
        public float $open,

        // High price.
        public float $high,

        // Low price.
        public float $low,

        // Close price.
        public float $close,

        // Candle time (Unix timestamp, UTC). Daily, weekly, monthly, yearly candles are returned without times.
        public Carbon $timestamp,
    ) {
    }
}
