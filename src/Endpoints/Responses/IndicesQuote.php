<?php

namespace MarketDataApp\Endpoints\Responses;

use Carbon\Carbon;

class IndicesQuote
{

    // Will always be ok when there is data for the symbol requested.
    public string $status;

    // The symbol of the index.
    public string $symbol;

    // The last price of the index.
    public float $last;

    // The difference in price in dollars (or the index's native currency if different from dollars) compared to the
    // closing price of the previous day.
    public float $change;

    // The difference in price in percent compared to the closing price of the previous day.
    public float $change_percent;

    // The 52-week high for the index.
    public float $fifty_two_week_high;

    // The 52-week low for the index.
    public float $fifty_two_week_low;

    // The date/time of the quote.
    public Carbon $updated;

    public function __construct(object $response)
    {
        // Convert the response to this object.
        $this->status = $response->s;
        $this->symbol = $response->symbol[0];
        $this->last = $response->last[0];
        $this->change = $response->change[0];
        $this->change_percent = $response->changepct[0];
        $this->fifty_two_week_high = $response->{'52weekHigh'}[0];
        $this->fifty_two_week_low = $response->{'52weekLow'}[0];
        $this->updated = Carbon::parse($response->updated);
    }
}
