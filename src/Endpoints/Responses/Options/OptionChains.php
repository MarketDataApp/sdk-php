<?php

namespace MarketDataApp\Endpoints\Responses\Options;

use Carbon\Carbon;
use MarketDataApp\Enums\Side;

class OptionChains
{

    // Status will always be ok when there is the quote requested.
    public string $status;

    // Time of the next quote if there is no data in the requested period, but there is data in a subsequent period.
    public Carbon $next_time;

    // Time of the previous quote if there is no data in the requested period, but there is data in a previous period.
    public Carbon $prev_time;

    /** @var OptionChain[] $option_chains */
    public array $option_chains = [];

    public function __construct(object $response)
    {
        // Convert the response to this object.
        $this->status = $response->s;

        switch ($this->status) {
            case 'ok':
                for ($i = 0; $i < count($response->optionSymbol); $i++) {
                    $this->option_chains[] = new OptionChain(
                        $response->optionSymbol[$i],
                        $response->underlying[$i],
                        Carbon::parse($response->expiration[$i]),
                        Side::from($response->side[$i]),
                        $response->strike[$i],
                        Carbon::parse($response->firstTraded[$i]),
                        $response->dte[$i],
                        $response->ask[$i],
                        $response->askSize[$i],
                        $response->bid[$i],
                        $response->bidSize[$i],
                        $response->mid[$i],
                        $response->last[$i],
                        $response->volume[$i],
                        $response->openInterest[$i],
                        $response->underlyingPrice[$i],
                        $response->inTheMoney[$i],
                        $response->intrinsicValue[$i],
                        $response->extrinsicValue[$i],
                        $response->iv[$i],
                        $response->delta[$i],
                        $response->gamma[$i],
                        $response->theta[$i],
                        $response->vega[$i],
                        $response->rho[$i],
                        Carbon::parse($response->updated[$i]),
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
