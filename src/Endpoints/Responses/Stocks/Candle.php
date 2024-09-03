<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;

/**
 * Represents a single stock candle with open, high, low, close prices, volume, and timestamp.
 */
class Candle
{

    /**
     * Constructs a new Candle instance.
     *
     * @param float  $open      Open price of the candle.
     * @param float  $high      High price of the candle.
     * @param float  $low       Low price of the candle.
     * @param float  $close     Close price of the candle.
     * @param int    $volume    Trading volume during the candle period.
     * @param Carbon $timestamp Candle time (Unix timestamp, UTC). Daily, weekly, monthly, yearly candles are returned
     *                          without times.
     */
    public function __construct(
        public float $open,
        public float $high,
        public float $low,
        public float $close,
        public int $volume,
        public Carbon $timestamp,
    ) {
    }
}
