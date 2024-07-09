<?php

namespace MarketDataApp\Endpoints\Responses;

use Carbon\Carbon;

class IndicesCandles
{
    // Will always be ok when there is data for the candles requested.
    public string $status;

    /** @var IndicesCandle[] $candles */
    public array $candles;

    public function __construct(object $response)
    {
        // Convert the response to this object.
        $this->status = $response->s;

        for($i = 0; $i < count($response->o); $i++) {
            $this->candles[] = new IndicesCandle(
                $response->o[$i],
                $response->h[$i],
                $response->l[$i],
                $response->c[$i],
                Carbon::parse($response->t[$i]),
            );
        }
    }
}
