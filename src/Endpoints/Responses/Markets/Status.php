<?php

namespace MarketDataApp\Endpoints\Responses\Markets;

use Carbon\Carbon;
use MarketDataApp\Traits\FormatsForDisplay;

/**
 * Represents the status of a market for a specific date.
 */
class Status
{
    use FormatsForDisplay;

    /**
     * Constructs a new Status instance.
     *
     * @param Carbon      $date   The date for which the market status is reported.
     * @param string|null $status The market status. This will always be 'open' or 'closed' or null. Half days or
     *                            partial trading days are reported as 'open'. Requests for days further in the past or
     *                            further in the future than our data will be returned as null.
     */
    public function __construct(
        public Carbon $date,
        public string|null $status,
    ) {
    }

    /**
     * Returns a string representation of the market status.
     *
     * @return string Human-readable market status.
     */
    public function __toString(): string
    {
        $statusText = $this->status ?? 'unknown';

        return sprintf("%s: %s", $this->formatDate($this->date), $statusText);
    }
}
