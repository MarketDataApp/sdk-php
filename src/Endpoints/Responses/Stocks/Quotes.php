<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

class Quotes
{
    /** @var Quote[] $quotes */
    public array $quotes;

    public function __construct(array $quotes)
    {
        foreach ($quotes as $quote) {
            $this->quotes[] = new Quote($quote);
        }
    }
}