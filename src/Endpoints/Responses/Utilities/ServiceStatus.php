<?php

namespace MarketDataApp\Endpoints\Responses\Utilities;

use Carbon\Carbon;
use MarketDataApp\Traits\FormatsForDisplay;

/**
 * Represents the status of a service.
 */
class ServiceStatus
{
    use FormatsForDisplay;

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

    /**
     * Returns a string representation of the service status.
     *
     * @return string Human-readable service status.
     */
    public function __toString(): string
    {
        $onlineStr = $this->online ? 'true' : 'false';

        return sprintf(
            "%s: %s (online: %s, 30d: %.2f%%, 90d: %.2f%%, updated: %s)",
            $this->service,
            $this->status,
            $onlineStr,
            $this->uptime_percentage_30d,
            $this->uptime_percentage_90d,
            $this->formatDateTime($this->updated)
        );
    }
}
