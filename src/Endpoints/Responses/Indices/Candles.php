<?php

namespace MarketDataApp\Endpoints\Responses\Indices;

use Carbon\Carbon;

class Candles
{

    /**
     * - Will always be ok when there is data for the candles requested.
     * - Status will be no_data if no candles are found for the request.
     * - Status will be error if the request produces an error response.
     */
    public string $status;

    /** @var Candle[] $candles */
    public array $candles;

    // Unix time of the next quote if there is no data in the requested period, but there is data in a subsequent
    // period.
    public int $next_time;

    /**
     * @throws \Exception
     */
    public function __construct(object $response)
    {
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
                $this->next_time = $response->nextTime;
                break;
        }
    }
}
