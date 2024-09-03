<?php

namespace MarketDataApp\Endpoints\Responses\Indices;

use Carbon\Carbon;

/**
 * Represents a financial candle with open, high, low, and close prices for a specific timestamp.
 */
class Candle
{

    /**
     * Constructs a new Candle instance.
     *
     * @param float  $open      Open price.
     * @param float  $high      High price.
     * @param float  $low       Low price.
     * @param float  $close     Close price.
     * @param Carbon $timestamp Candle time (Unix timestamp, UTC). Daily, weekly, monthly, yearly candles are returned
     *                          without times.
     */
    public function __construct(
        public float $open,
        public float $high,
        public float $low,
        public float $close,
        public Carbon $timestamp,
    ) {
    }
}
