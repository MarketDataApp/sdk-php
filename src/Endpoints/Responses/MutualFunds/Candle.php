<?php

namespace MarketDataApp\Endpoints\Responses\MutualFunds;

use Carbon\Carbon;
use MarketDataApp\Traits\FormatsForDisplay;

/**
 * Represents a financial candle for mutual funds with open, high, low, and close prices for a specific timestamp.
 */
class Candle
{
    use FormatsForDisplay;

    /**
     * Constructs a new Candle instance.
     *
     * @param float  $open      Open price of the candle.
     * @param float  $high      High price of the candle.
     * @param float  $low       Low price of the candle.
     * @param float  $close     Close price of the candle.
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

    /**
     * Returns a string representation of the mutual fund candle.
     *
     * @return string Human-readable candle data.
     */
    public function __toString(): string
    {
        return sprintf(
            "%s: O%s H%s L%s C%s",
            $this->formatDate($this->timestamp),
            $this->formatCurrency($this->open),
            $this->formatCurrency($this->high),
            $this->formatCurrency($this->low),
            $this->formatCurrency($this->close)
        );
    }
}
