<?php

namespace MarketDataApp\Endpoints\Responses\Options;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Represents a collection of option expirations dates and related data.
 */
class Expirations extends ResponseBase
{

    /**
     * Status of the expirations request. Will always be ok when there is strike data for the underlying/expirations
     * requested.
     *
     * @var string
     */
    public string $status;

    /**
     * The expiration dates requested for the underlying with the option strikes for each expiration.
     *
     * @var Carbon[]
     */
    public array $expirations = [];

    /**
     * The date and time this list of options strikes was updated in Unix time.
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
     * Constructs a new Expirations instance from the given response object.
     *
     * @param object $response The response object containing expirations data.
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
                $this->expirations = array_map(function ($expiration) {
                    return Carbon::parse($expiration);
                }, $response->expirations);
                $this->updated = Carbon::parse($response->updated);
                break;

            case 'no_data':
                if (isset($response->nextTime)) {
                    $this->next_time = Carbon::parse($response->nextTime);
                }

                if (isset($response->prevTime)) {
                    $this->prev_time = Carbon::parse($response->prevTime);
                }
                break;
        }
    }
}
