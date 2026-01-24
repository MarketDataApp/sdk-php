<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Traits\FormatsForDisplay;

/**
 * Represents a single stock candle with open, high, low, close prices, volume, and timestamp.
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

    /**
     * Returns a string representation of the candle.
     *
     * @return string Human-readable candle data.
     */
    public function __toString(): string
    {
        // Use datetime for intraday candles (non-midnight times), date-only for daily+
        $isIntraday = $this->timestamp->hour !== 0 || $this->timestamp->minute !== 0;
        $timeFormat = $isIntraday ? $this->formatDateTime($this->timestamp) : $this->formatDate($this->timestamp);

        return sprintf(
            "%s: O%s H%s L%s C%s Vol:%s",
            $timeFormat,
            $this->formatCurrency($this->open),
            $this->formatCurrency($this->high),
            $this->formatCurrency($this->low),
            $this->formatCurrency($this->close),
            $this->formatVolume($this->volume)
        );
    }
}
