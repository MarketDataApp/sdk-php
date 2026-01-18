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

        // Convert to array for easier access to keys with spaces (human-readable format)
        $responseArray = (array) $response;

        // Determine if this is human-readable format (has "Open" key) or regular format (has "s" status)
        $isHumanReadable = isset($responseArray['Open']);

        if ($isHumanReadable) {
            // Human-readable format - no "s" status field
            $this->status = 'ok';
            
            $count = count($responseArray['Open']);
            for ($i = 0; $i < $count; $i++) {
                $this->candles[] = new Candle(
                    $responseArray['Open'][$i],
                    $responseArray['High'][$i],
                    $responseArray['Low'][$i],
                    $responseArray['Close'][$i],
                    $responseArray['Volume'][$i],
                    Carbon::parse($responseArray['Date'][$i]),
                );
            }
        } else {
            // Regular format
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
}
