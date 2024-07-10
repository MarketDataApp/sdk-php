<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;

class BulkCandles
{

    // Will always be ok when there is data for the candles requested.
    public string $status;

    // The ticker symbols of the stock.
    public array $symbols = [];

    /** @var Candle[] $candles */
    public array $candles = [];

    public function __construct(object $response)
    {
        // Convert the response to this object.
        $this->status = $response->s;

        if ($this->status === 'ok') {
            $this->symbols = array_map('trim', explode(',', $response->symbol));
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
        }
    }
}
