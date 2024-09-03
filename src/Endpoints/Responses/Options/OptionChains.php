<?php

namespace MarketDataApp\Endpoints\Responses\Options;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;
use MarketDataApp\Enums\Side;

/**
 * Represents a collection of option chains with associated data.
 */
class OptionChains extends ResponseBase
{

    /**
     * Status of the option chains request. Will always be ok when there is the quote requested.
     *
     * @var string
     */
    public string $status;

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
     * Multidimensional array of OptionChainStrike objects organized by date.
     *
     * @var array<string, OptionChainStrike[]>
     */
    public array $option_chains = [];

    /**
     * Constructs a new OptionChains instance from the given response object.
     *
     * @param object $response The response object containing option chains data.
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
                for ($i = 0; $i < count($response->optionSymbol); $i++) {
                    $expiration = Carbon::parse($response->expiration[$i]);
                    $this->option_chains[$expiration->toDateString()][] = new OptionChainStrike(
                        option_symbol: $response->optionSymbol[$i],
                        underlying: $response->underlying[$i],
                        expiration: $expiration,
                        side: Side::from($response->side[$i]),
                        strike: $response->strike[$i],
                        first_traded: Carbon::parse($response->firstTraded[$i]),
                        dte: $response->dte[$i],
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
