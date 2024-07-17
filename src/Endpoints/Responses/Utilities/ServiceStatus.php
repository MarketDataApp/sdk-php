<?php

namespace MarketDataApp\Endpoints\Responses\Utilities;

use Carbon\Carbon;

class ServiceStatus
{

    public function __construct(
        // The service being monitored.
        public string $service,

        // The current status of each service (online or offline).
        public string $status,

        // The uptime percentage of each service over the last 30 days.
        public float $uptime_percentage_30d,

        // The uptime percentage of each service over the last 90 days.
        public float $uptime_percentage_90d,

        // The timestamp of the last update for each service's status.
        public Carbon $updated,
    ) {

    }
}
