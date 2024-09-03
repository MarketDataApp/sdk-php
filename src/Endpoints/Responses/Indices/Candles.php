<?php

namespace MarketDataApp\Endpoints\Responses\Indices;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Represents a collection of financial candles with additional metadata.
 */
class Candles extends ResponseBase
{

    /**
     * Status of the candles request.
     *
     * - Will always be 'ok' when there is data for the candles requested.
     * - Status will be 'no_data' if no candles are found for the request.
     * - Status will be 'error' if the request produces an error response.
     *
     * @var string
     */
    public string $status;

    /**
     * Array of Candle objects representing financial data.
     *
     * @var Candle[]
     */
    public array $candles = [];

    /**
     * Unix time of the next quote if there is no data in the requested period, but there is data in a subsequent
     * period.
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
     * Constructs a new Candles instance from the given response object.
     *
     * @param object $response The response object containing candle data.
     *
     * @throws \Exception If there's an error parsing the response.
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
                for ($i = 0; $i < count($response->o); $i++) {
                    $this->candles[] = new Candle(
                        $response->o[$i],
                        $response->h[$i],
                        $response->l[$i],
                        $response->c[$i],
                        Carbon::parse($response->t[$i]),
                    );
                }
                break;

            case 'no_data' && isset($response->nextTime):
                $this->next_time = Carbon::parse($response->nextTime);
                $this->prev_time = Carbon::parse($response->prevTime);
                break;
        }
    }
}
