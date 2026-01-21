<?php

namespace MarketDataApp\Endpoints\Responses\Utilities;

use Carbon\Carbon;

/**
 * Represents the status of a service.
 */
class ServiceStatus
{

    /**
     * ServiceStatus constructor.
     *
     * @param string $service               The service being monitored.
     * @param string $status                The current status of each service (online or offline).
     * @param bool   $online                The boolean online status of the service.
     * @param float  $uptime_percentage_30d The uptime percentage of each service over the last 30 days.
     * @param float  $uptime_percentage_90d The uptime percentage of each service over the last 90 days.
     * @param Carbon $updated               The timestamp of the last update for each service's status.
     */
    public function __construct(
        public string $service,
        public string $status,
        public bool $online,
        public float $uptime_percentage_30d,
        public float $uptime_percentage_90d,
        public Carbon $updated,
    ) {
    }
}
