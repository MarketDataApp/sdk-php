<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Represents a collection of bulk stock quotes.
 */
class BulkQuotes extends ResponseBase
{

    /**
     * Status of the bulk quotes request. Will always be ok when there is data for the symbol requested.
     *
     * @var string
     */
    public string $status;

    /**
     * Array of BulkQuote objects representing individual stock quotes.
     *
     * @var BulkQuote[]
     */
    public array $quotes;

    /**
     * Constructs a new BulkQuotes instance from the given response object.
     *
     * @param object $response The response object containing bulk quotes data.
     */
    public function __construct(object $response)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }

        // Convert the response to this object.
        $this->status = $response->s;

        if ($this->status === "ok") {
            for ($i = 0; $i < count($response->symbol); $i++) {
                $this->quotes[] = new BulkQuote(
                    symbol: $response->symbol[$i],
                    ask: $response->ask[$i],
                    ask_size: $response->askSize[$i],
                    bid: $response->bid[$i],
                    bid_size: $response->bidSize[$i],
                    mid: $response->mid[$i],
                    last: $response->last[$i],
                    change: $response->change[$i],
                    change_percent: $response->changepct[$i],
                    fifty_two_week_high: isset($response->{'52weekHigh'}) ? $response->{'52weekHigh'}[$i] : null,
                    fifty_two_week_low: isset($response->{'52weekLow'}) ? $response->{'52weekLow'}[$i] : null,
                    volume: $response->volume[$i],
                    updated: Carbon::parse($response->updated[$i]),
                );
            }
        }
    }
}
