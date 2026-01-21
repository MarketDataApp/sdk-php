<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Class Prices
 *
 * Represents stock prices and handles the response parsing for stock prices data.
 * This endpoint returns real-time midpoint prices for one or more stocks using the SmartMid model.
 */
class Prices extends ResponseBase
{

    /**
     * The status of the response. Will be "ok" when there is data, "no_data" when no prices can be found,
     * or "error" if the request produces an error response.
     *
     * @var string
     */
    public string $status;

    /**
     * Array of ticker symbols that were requested.
     *
     * @var array
     */
    public array $symbols;

    /**
     * Array of midpoint prices, as calculated by the SmartMid model.
     *
     * @var array
     */
    public array $mid;

    /**
     * Array of price changes in currency units compared to the closing price of the previous primary trading session.
     *
     * @var array
     */
    public array $change;

    /**
     * Array of price changes in percent, expressed as a decimal, compared to the closing price of the previous day.
     * For example, a 3% change will be represented as 0.03.
     *
     * @var array
     */
    public array $changepct;

    /**
     * Array of date/times for each stock price.
     *
     * @var array
     */
    public array $updated;

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

        // Convert the response to this object.
        // Check for human-readable keys first (with spaces), then fall back to regular keys
        if ($isHumanReadable) {
            // Human-readable format - no "s" status field
            $this->status = 'ok'; // Human-readable format always returns data when successful
            $this->symbols = $responseArray['Symbol'] ?? [];
            $this->mid = $responseArray['Mid'] ?? [];
            $this->change = $responseArray['Change $'] ?? [];
            $this->changepct = $responseArray['Change %'] ?? [];
            
            // Convert updated timestamps to Carbon objects
            $this->updated = [];
            if (isset($responseArray['Date']) && is_array($responseArray['Date'])) {
                foreach ($responseArray['Date'] as $timestamp) {
                    $this->updated[] = Carbon::parse($timestamp);
                }
            }
        } else {
            // Regular format
            $this->status = $response->s ?? 'no_data';
            $this->symbols = $response->symbol ?? [];
            $this->mid = $response->mid ?? [];
            $this->change = $response->change ?? [];
            $this->changepct = $response->changepct ?? [];
            
            // Convert updated timestamps to Carbon objects
            $this->updated = [];
            if (isset($response->updated) && is_array($response->updated)) {
                foreach ($response->updated as $timestamp) {
                    $this->updated[] = Carbon::parse($timestamp);
                }
            }
        }
    }
}
