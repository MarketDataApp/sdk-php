<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;
use MarketDataApp\Traits\FormatsForDisplay;

/**
 * Class Prices
 *
 * Represents stock prices and handles the response parsing for stock prices data.
 * This endpoint returns real-time midpoint prices for one or more stocks using the SmartMid model.
 */
class Prices extends ResponseBase
{
    use FormatsForDisplay;

    /**
     * The status of the response. Will be "ok" when there is data, "no_data" when no prices can be found,
     * or "error" if the request produces an error response.
     *
     * @var string
     */
    public string $status = 'no_data';

    /**
     * Array of Price objects containing individual stock price data.
     *
     * @var Price[]
     */
    public array $prices = [];

    /**
     * Constructs a new Prices object and parses the response data.
     *
     * @param object $response The raw response object to be parsed.
     */
    public function __construct(object $response)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }

        // Convert to array for easier access to keys with spaces (human-readable format)
        $responseArray = (array) $response;

        // Determine if this is human-readable format (has "Symbol" key) or regular format (has "s" status)
        $isHumanReadable = isset($responseArray['Symbol']);

        if ($isHumanReadable) {
            // Human-readable format - no "s" status field
            $symbols = $responseArray['Symbol'] ?? [];
            $mids = $responseArray['Mid'] ?? [];
            $changes = $responseArray['Change $'] ?? [];
            $changepcts = $responseArray['Change %'] ?? [];
            $dates = $responseArray['Date'] ?? [];

            if (empty($symbols)) {
                return;
            }

            $this->status = 'ok';

            // Create Price objects for each item
            $count = count($symbols);
            for ($i = 0; $i < $count; $i++) {
                $this->prices[] = new Price(
                    symbol: $symbols[$i],
                    mid: $mids[$i] ?? 0.0,
                    change: $changes[$i] ?? 0.0,
                    changepct: $changepcts[$i] ?? 0.0,
                    updated: Carbon::parse($dates[$i] ?? 0)
                );
            }
        } else {
            // Regular format
            $this->status = $response->s ?? 'no_data';

            if ($this->status !== 'ok') {
                return;
            }

            $symbols = $response->symbol ?? [];
            $mids = $response->mid ?? [];
            $changes = $response->change ?? [];
            $changepcts = $response->changepct ?? [];
            $updatedTimestamps = $response->updated ?? [];

            // Create Price objects for each item
            $count = count($symbols);
            for ($i = 0; $i < $count; $i++) {
                $this->prices[] = new Price(
                    symbol: $symbols[$i],
                    mid: $mids[$i] ?? 0.0,
                    change: $changes[$i] ?? 0.0,
                    changepct: $changepcts[$i] ?? 0.0,
                    updated: Carbon::parse($updatedTimestamps[$i] ?? 0)
                );
            }
        }
    }

    /**
     * Returns a string representation of the prices collection.
     *
     * @return string Human-readable prices summary.
     */
    public function __toString(): string
    {
        if (!$this->isJson()) {
            return "Prices - Non-JSON format, use getCsv() or getHtml()";
        }

        $count = count($this->prices);
        $lines = [sprintf("Prices: %d symbol%s (status: %s)", $count, $count === 1 ? '' : 's', $this->status)];

        $displayCount = min(3, $count);
        for ($i = 0; $i < $displayCount; $i++) {
            $price = $this->prices[$i];
            $lines[] = sprintf(
                "  %s: %s (%s) Change: %s  Updated: %s",
                $price->symbol,
                $this->formatCurrency($price->mid),
                $this->formatPercent($price->changepct),
                $this->formatChange($price->change),
                $this->formatDateTime($price->updated)
            );
        }

        if ($count > 3) {
            $lines[] = sprintf("  ... and %d more", $count - 3);
        }

        return implode("\n", $lines);
    }
}
