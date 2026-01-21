<?php

namespace MarketDataApp\Endpoints\Responses\Utilities;

use Carbon\Carbon;

/**
 * Represents the status of the API and its services.
 */
class ApiStatus
{

    /**
     * Will always be "ok" when the status information is successfully retrieved.
     *
     * @var string
     */
    public string $status;

    /**
     * Array of ServiceStatus objects representing the status of each service.
     *
     * @var ServiceStatus[]
     */
    public array $services;

    /**
     * ApiStatus constructor.
     *
     * @param object $response The raw response object containing API status information.
     */
    public function __construct(object $response)
    {
        // Convert the response to this object.
        $this->status = $response->s;

        for ($i = 0; $i < count($response->service); $i++) {
            // Handle online field - default to true if missing for backward compatibility
            $online = isset($response->online) && is_array($response->online) 
                ? (bool)$response->online[$i] 
                : true;

            $this->services[] = new ServiceStatus(
                $response->service[$i],
                $response->status[$i],
                $online,
                $response->{'uptimePct30d'}[$i],
                $response->{'uptimePct90d'}[$i],
                Carbon::parse($response->updated[$i]),
            );
        }
    }
}
