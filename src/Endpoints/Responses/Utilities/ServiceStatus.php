<?php

namespace MarketDataApp\Endpoints\Responses\Utilities;

use Carbon\Carbon;

class ServiceStatus
{

    // The service being monitored.
    public string $service;

    // The current status of each service (online or offline).
    public string $status;

    // The uptime percentage of each service over the last 30 days.
    public float $uptime_percentage_30d;

    // The uptime percentage of each service over the last 90 days.
    public float $uptime_percentage_90d;

    // The timestamp of the last update for each service's status.
    public Carbon $updated;

    public function __construct(
        string $service,
        string $status,
        float $uptime_percentage_30d,
        float $uptime_percentage_90d,
        Carbon $updated
    ) {
        $this->service = $service;
        $this->status = $status;
        $this->uptime_percentage_30d = $uptime_percentage_30d;
        $this->uptime_percentage_90d = $uptime_percentage_90d;
        $this->updated = $updated;
    }
}
