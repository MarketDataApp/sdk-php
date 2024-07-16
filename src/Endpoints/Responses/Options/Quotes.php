<?php

namespace MarketDataApp\Endpoints\Responses\Options;

use Carbon\Carbon;

class Quotes
{

    // Status will always be ok when there is data for the quote requested.
    public string $status;

    // Time of the next quote if there is no data in the requested period, but there is data in a subsequent period.
    public Carbon $next_time;

    // Time of the previous quote if there is no data in the requested period, but there is data in a previous period.
    public Carbon $prev_time;

    /** @var Quote[] $quotes */
    public array $quotes = [];

    public function __construct(object $response)
    {
        // Convert the response to this object.
        $this->status = $response->s;

        switch ($this->status) {
            case 'ok':
                for ($i = 0; $i < count($response->optionSymbol); $i++) {
                    $this->quotes[] = new Quote(
                        option_symbol: $response->optionSymbol[$i],
                        ask: $response->ask[$i],
                        ask_size: $response->askSize[$i],
                        bid: $response->bid[$i],
                        bid_size: $response->bidSize[$i],
                        mid: $response->mid[$i],
                        last: $response->last[$i],
                        volume: $response->volume[$i],
                        open_interest: $response->openInterest[$i],
                        underlying_price: $response->underlyingPrice[$i],
                        in_the_money: $response->inTheMoney[$i],
                        intrinsic_value: $response->intrinsicValue[$i],
                        extrinsic_value: $response->extrinsicValue[$i],
                        implied_volatility: $response->iv[$i],
                        delta: $response->delta[$i],
                        gamma: $response->gamma[$i],
                        theta: $response->theta[$i],
                        vega: $response->vega[$i],
                        rho: $response->rho[$i],
                        updated: Carbon::parse($response->updated[$i]),
                    );
                }
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
