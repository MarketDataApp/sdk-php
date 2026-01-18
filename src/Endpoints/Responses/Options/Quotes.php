<?php

namespace MarketDataApp\Endpoints\Responses\Options;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Represents a collection of option quotes with associated data.
 */
class Quotes extends ResponseBase
{

    /**
     * Status of the quotes request. Will always be ok when there is data for the quote requested.
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
     * Array of Quote objects.
     *
     * @var Quote[]
     */
    public array $quotes = [];

    /**
     * Constructs a new Quotes instance from the given response object.
     *
     * @param object $response The response object containing quotes data.
     */
    public function __construct(object $response)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }

        // Convert to array for easier access to keys with spaces (human-readable format)
        $responseArray = (array) $response;

        // Determine if this is human-readable format (has "Symbol" key) or regular format (has "s" status)
        $isHumanReadable = isset($responseArray['Symbol']);

        if ($isHumanReadable) {
            // Human-readable format - no "s" status field
            $this->status = 'ok';
            
            $count = count($responseArray['Symbol']);
            for ($i = 0; $i < $count; $i++) {
                $this->quotes[] = new Quote(
                    option_symbol: $responseArray['Symbol'][$i],
                    ask: $responseArray['Ask'][$i],
                    ask_size: $responseArray['Ask Size'][$i],
                    bid: $responseArray['Bid'][$i],
                    bid_size: $responseArray['Bid Size'][$i],
                    mid: $responseArray['Mid'][$i],
                    last: $responseArray['Last'][$i] ?? null,
                    volume: $responseArray['Volume'][$i],
                    open_interest: $responseArray['Open Interest'][$i],
                    underlying_price: $responseArray['Underlying Price'][$i],
                    in_the_money: $responseArray['In The Money'][$i],
                    intrinsic_value: $responseArray['Intrinsic Value'][$i],
                    extrinsic_value: $responseArray['Extrinsic Value'][$i],
                    implied_volatility: $responseArray['IV'][$i] ?? null,
                    delta: $responseArray['Delta'][$i] ?? null,
                    gamma: $responseArray['Gamma'][$i] ?? null,
                    theta: $responseArray['Theta'][$i] ?? null,
                    vega: $responseArray['Vega'][$i] ?? null,
                    updated: Carbon::parse($responseArray['Date'][$i]),
                );
            }
        } else {
            // Regular format
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
}
