<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Traits\FormatsForDisplay;

/**
 * Represents a single stock price with mid, change, and timestamp data.
 */
class Price
{
    use FormatsForDisplay;

    /**
     * Constructs a new Price instance.
     *
     * @param string $symbol    The ticker symbol for this price.
     * @param float  $mid       The midpoint price as calculated by the SmartMid model.
     * @param float  $change    Price change in currency units compared to previous day's close.
     * @param float  $changepct Price change in percent (as decimal, e.g., 0.03 = 3%).
     * @param Carbon $updated   Date/time when this price was last updated.
     */
    public function __construct(
        public string $symbol,
        public float $mid,
        public float $change,
        public float $changepct,
        public Carbon $updated,
    ) {
    }

    /**
     * Returns a string representation of the price.
     *
     * @return string Human-readable price data.
     */
    public function __toString(): string
    {
        return sprintf(
            "%s: %s (%s) Change: %s  Updated: %s",
            $this->symbol,
            $this->formatCurrency($this->mid),
            $this->formatPercent($this->changepct),
            $this->formatChange($this->change),
            $this->formatDateTime($this->updated)
        );
    }
}
