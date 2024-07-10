<?php

namespace MarketDataApp\Endpoints\Responses;

use Carbon\Carbon;

class StocksQuotes
{
    /** @var StocksQuote[] $quotes */
    public array $quotes;

    public function __construct(array $quotes)
    {
        foreach ($quotes as $quote) {
            $this->quotes[] = new StocksQuote($quote);
        }
    }
}
