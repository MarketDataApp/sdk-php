<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Represents a collection of stock candles data in bulk format.
 */
class BulkCandles extends ResponseBase
{

    /**
     * Status of the bulk candles request. Will always be ok when there is data for the candles requested.
     *
     * @var string
     */
    public string $status;

    /**
     * Array of Candle objects representing individual stock candles.
     *
     * @var Candle[]
     */
    public array $candles = [];

    /**
     * Constructs a new BulkCandles instance from the given response object.
     *
     * @param object $response The response object containing bulk candles data.
     */
    public function __construct(object $response)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }

        // Convert the response to this object.
        $this->status = $response->s;

        if ($this->status === 'ok') {
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
