<?php

namespace MarketDataApp\Endpoints\Responses\Markets;

use Carbon\Carbon;

class Statuses
{

    // Will always be ok when there is data for the dates requested.
    public string $status;

    /** @var Status[] $statuses */
    public array $statuses = [];

    public function __construct(object $response)
    {
        // Convert the response to this object.
        $this->status = $response->s;

        if ($this->status === 'ok') {
            for ($i = 0; $i < count($response->date); $i++) {
                $this->statuses[] = new Status(
                    Carbon::parse($response->date[$i]),
                    $response->status[$i],
                );
            }
        }
    }
}