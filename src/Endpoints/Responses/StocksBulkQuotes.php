<?php

namespace MarketDataApp\Endpoints\Responses;

use Carbon\Carbon;

class StocksBulkQuotes
{
    // Will always be ok when there is data for the symbol requested.
    public string $status;

    /** @var StocksBulkQuote[] $quotes */
    public array $quotes;

    public function __construct(object $response)
    {
        // Convert the response to this object.
        $this->status = $response->s;

        for($i = 0; $i < count($response->symbol); $i++) {
            $this->quotes[] = new StocksBulkQuote(
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
