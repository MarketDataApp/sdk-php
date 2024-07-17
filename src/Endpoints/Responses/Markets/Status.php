<?php

namespace MarketDataApp\Endpoints\Responses\Markets;

use Carbon\Carbon;

class Status
{

    public function __construct(
        // The date.
        public Carbon $date,

        // The market status. This will always be open or closed or null. Half days or partial trading days are reported
        // as open. Requests for days further in the past or further in the future than our data will be returned as
        // null.
        public string|null $status,
    ) {
    }
}
