<?php

namespace MarketDataApp\Endpoints\Responses\Markets;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Represents a collection of market statuses for different dates.
 */
class Statuses extends ResponseBase
{

    /**
     * The status of the response. Will always be ok when there is data for the dates requested.
     *
     * @var string
     */
    public string $status;

    /**
     * Array of Status objects representing market statuses for different dates.
     *
     * @var Status[]
     */
    public array $statuses = [];

    /**
     * Constructs a new Statuses instance from the given response object.
     *
     * @param object $response The response object containing market status data.
     */
    public function __construct(object $response)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }
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
