<?php

namespace MarketDataApp\Endpoints\Responses\Utilities;

use Carbon\Carbon;

class ApiStatus
{

    // Will always be ok when the status information is successfully retrieved.
    public string $status;

    /* @var ServiceStatus[] $services */
    public array $services;

    public function __construct(object $response)
    {
        // Convert the response to this object.
        $this->status = $response->s;

        for ($i = 0; $i < count($response->service); $i++) {
            $this->services[] = new ServiceStatus(
                $response->service[$i],
                $response->status[$i],
                $response->{'uptimePct30d'}[$i],
                $response->{'uptimePct90d'}[$i],
                Carbon::parse($response->updated[$i]),
            );
        }
    }
}