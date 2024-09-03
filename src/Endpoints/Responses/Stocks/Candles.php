<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Class Candles
 *
 * Represents a collection of stock candles data and handles the response parsing.
 */
class Candles extends ResponseBase
{

    /**
     * The status of the response. Will always be "ok" when there is data for the candles requested.
     *
     * @var string
     */
    public string $status;

    /**
     * Unix time of the next quote if there is no data in the requested period, but there is data in a subsequent
     * period.
     *
     * @var int
     */
    public int $next_time;

    /**
     * Array of Candle objects representing individual candle data.
     *
     * @var Candle[]
     */
    public array $candles = [];

    /**
     * Constructs a new Candles object and parses the response data.
     *
     * @param object $response The raw response object to be parsed.
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
                        $response->v[$i],
                        Carbon::parse($response->t[$i]),
                    );
                }
                break;

            case 'no_data':
                if (isset($response->nextTime)) {
                    $this->next_time = $response->nextTime;
                }
                break;
        }
    }
}
