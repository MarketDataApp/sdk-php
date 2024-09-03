<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

/**
 * Represents a collection of stock quotes.
 */
class Quotes
{

    /**
     * Array of Quote objects.
     *
     * @var Quote[]
     */
    public array $quotes;

    /**
     * Quotes constructor.
     *
     * @param array $quotes Array of raw quote data.
     */
    public function __construct(array $quotes)
    {
        foreach ($quotes as $quote) {
            $this->quotes[] = new Quote($quote);
        }
    }
}
