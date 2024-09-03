<?php

namespace MarketDataApp\Endpoints\Responses\Indices;

/**
 * Represents a collection of Quote objects.
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
     * Constructs a new Quotes instance from an array of quote data.
     *
     * @param array $quotes An array of quote data to be converted into Quote objects.
     */
    public function __construct(array $quotes)
    {
        foreach ($quotes as $quote) {
            $this->quotes[] = new Quote($quote);
        }
    }
}
