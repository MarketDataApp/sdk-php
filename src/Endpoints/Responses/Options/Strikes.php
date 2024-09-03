<?php

namespace MarketDataApp\Endpoints\Responses\Options;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Represents a collection of option strikes with associated data.
 */
class Strikes extends ResponseBase
{

    /**
     * Status of the strikes request. Will always be ok when there is data for the candles requested.
     *
     * @var string
     */
    public string $status;

    /**
     * The expiration dates requested for the underlying with the option strikes for each expiration.
     *
     * @var array<string, int[]>
     */
    public array $dates = [];

    /**
     * The date and time of this list of options strikes was updated in Unix time.
     * For historical strikes, this number should match the date parameter.
     *
     * @var Carbon
     */
    public Carbon $updated;

    /**
     * Time of the next quote if there is no data in the requested period, but there is data in a subsequent period.
     *
     * @var Carbon
     */
    public Carbon $next_time;

    /**
     * Time of the previous quote if there is no data in the requested period, but there is data in a previous period.
     *
     * @var Carbon
     */
    public Carbon $prev_time;

    /**
     * Constructs a new Strikes instance from the given response object.
     *
     * @param object $response The response object containing strikes data.
     */
    public function __construct(object $response)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }

        // Convert the response to this object.
        $this->status = $response->s;

        switch ($this->status) {
            case 'ok':
                foreach ($response as $key => $value) {
                    if (in_array($key, ['s', 'updated'])) {
                        continue;
                    }

                    $this->dates[$key] = $value;
                }
                $this->updated = Carbon::parse($response->updated);
                break;

            case 'no_data' && isset($response->nextTime):
                $this->next_time = Carbon::parse($response->nextTime);
                $this->prev_time = Carbon::parse($response->prevTime);
                break;
        }
    }
}
