<?php

namespace MarketDataApp\Endpoints\Responses\Options;

use Carbon\Carbon;

class Strikes
{
    // Will always be ok when there is data for the candles requested.
    public string $status;

    /**
     * The expiration dates requested for the underlying with the option strikes for each expiration.
     *
     * @var array[int[]] $dates
     */
    public array $dates = [];

    /**
     * The date and time of this list of options strikes was updated in Unix time. For historical strikes, this number
     * should match the date parameter.
     *
     * @var Carbon $updated
     */
    public Carbon $updated;

    // Time of the next quote if there is no data in the requested period, but there is data in a subsequent period.
    public Carbon $next_time;

    // Time of the previous quote if there is no data in the requested period, but there is data in a previous period.
    public Carbon $prev_time;

    public function __construct(object $response)
    {
        // Convert the response to this object.
        $this->status = $response->s;

        switch ($this->status) {
            case 'ok':
                $this->dates = (array) $response->dates;
                $this->updated = Carbon::parse($response->updated);
                break;

            case 'no_data' && isset($response->nextTime):
                $this->next_time = Carbon::parse($response->nextTime);
                $this->prev_time = Carbon::parse($response->prevTime);
                break;
        }
    }
}
