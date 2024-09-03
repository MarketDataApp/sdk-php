<?php

namespace MarketDataApp\Endpoints\Responses\Markets;

use Carbon\Carbon;

/**
 * Represents the status of a market for a specific date.
 */
class Status
{

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
}
