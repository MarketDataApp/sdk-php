<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Represents a collection of stock quotes.
 */
class Quotes extends ResponseBase
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
     * Parses a multi-symbol quote response where each field contains an array of values,
     * one per symbol. Creates individual Quote objects for each symbol.
     *
     * @param object $response The raw API response object containing arrays for each field.
     */
    public function __construct(object $response)
    {
        parent::__construct($response);
        $this->quotes = [];

        // For non-JSON formats (CSV, HTML), create a single Quote object with the raw response
        if (!$this->isJson()) {
            $this->quotes[] = new Quote($response);
            return;
        }

        // Convert to array for easier access to keys with spaces (human-readable format)
        $responseArray = (array) $response;

        // Determine if this is human-readable format (has "Symbol" key) or regular format
        $isHumanReadable = isset($responseArray['Symbol']);

        // Get the symbols array to determine how many quotes we have
        $symbols = $isHumanReadable
            ? ($responseArray['Symbol'] ?? [])
            : ($response->symbol ?? []);

        // Create a Quote object for each symbol by extracting data at each index
        foreach ($symbols as $index => $symbol) {
            $quoteData = $this->extractQuoteAtIndex($response, $index, $isHumanReadable);
            $this->quotes[] = new Quote($quoteData);
        }
    }

    /**
     * Extract quote data at a specific index from the multi-symbol response.
     *
     * Creates a response object that looks like a single-symbol response
     * by extracting values at the given index from each array field.
     *
     * @param object $response        The full multi-symbol response.
     * @param int    $index           The index of the symbol to extract.
     * @param bool   $isHumanReadable Whether the response uses human-readable keys.
     *
     * @return object A response object formatted for a single symbol.
     */
    private function extractQuoteAtIndex(object $response, int $index, bool $isHumanReadable): object
    {
        $responseArray = (array) $response;

        if ($isHumanReadable) {
            // Human-readable format
            return (object) [
                'Symbol'       => [$responseArray['Symbol'][$index] ?? null],
                'Ask'          => [$responseArray['Ask'][$index] ?? null],
                'Ask Size'     => [$responseArray['Ask Size'][$index] ?? null],
                'Bid'          => [$responseArray['Bid'][$index] ?? null],
                'Bid Size'     => [$responseArray['Bid Size'][$index] ?? null],
                'Mid'          => [$responseArray['Mid'][$index] ?? null],
                'Last'         => [$responseArray['Last'][$index] ?? null],
                'Change $'     => [$responseArray['Change $'][$index] ?? null],
                'Change %'     => [$responseArray['Change %'][$index] ?? null],
                'Volume'       => [$responseArray['Volume'][$index] ?? null],
                'Date'         => [$responseArray['Date'][$index] ?? null],
                '52 Week High' => [$responseArray['52 Week High'][$index] ?? null],
                '52 Week Low'  => [$responseArray['52 Week Low'][$index] ?? null],
            ];
        } else {
            // Regular format
            return (object) [
                's'          => $response->s ?? 'ok',
                'symbol'     => [$response->symbol[$index] ?? null],
                'ask'        => [$response->ask[$index] ?? null],
                'askSize'    => [$response->askSize[$index] ?? null],
                'bid'        => [$response->bid[$index] ?? null],
                'bidSize'    => [$response->bidSize[$index] ?? null],
                'mid'        => [$response->mid[$index] ?? null],
                'last'       => [$response->last[$index] ?? null],
                'change'     => [$response->change[$index] ?? null],
                'changepct'  => [$response->changepct[$index] ?? null],
                'volume'     => [$response->volume[$index] ?? null],
                'updated'    => [$response->updated[$index] ?? null],
                '52weekHigh' => [$response->{'52weekHigh'}[$index] ?? null],
                '52weekLow'  => [$response->{'52weekLow'}[$index] ?? null],
            ];
        }
    }

    /**
     * Returns a string representation of the quotes collection.
     *
     * @return string Human-readable quotes summary.
     */
    public function __toString(): string
    {
        if (!$this->isJson()) {
            return "Quotes - Non-JSON format, use getCsv() or getHtml()";
        }

        $count = count($this->quotes);
        $lines = [sprintf("Quotes: %d symbol%s", $count, $count === 1 ? '' : 's')];

        $displayCount = min(3, $count);
        for ($i = 0; $i < $displayCount; $i++) {
            $lines[] = "  " . (string) $this->quotes[$i];
        }

        if ($count > 3) {
            $lines[] = sprintf("  ... and %d more", $count - 3);
        }

        return implode("\n", $lines);
    }
}
