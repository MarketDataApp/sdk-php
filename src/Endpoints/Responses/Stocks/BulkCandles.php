<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Represents a collection of stock candles data in bulk format.
 */
class BulkCandles extends ResponseBase
{

    /**
     * Status of the bulk candles request. Will always be ok when there is data for the candles requested.
     *
     * @var string
     */
    public string $status;

    /**
     * Array of Candle objects representing individual stock candles.
     *
     * @var Candle[]
     */
    public array $candles = [];

    /**
     * Constructs a new BulkCandles instance from the given response object.
     *
     * @param object $response The response object containing bulk candles data.
     */
    public function __construct(object $response)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }

        // Convert to array for easier access to keys with spaces (human-readable format)
        $responseArray = (array) $response;

        // Determine if this is human-readable format (has "Open" key) or regular format (has "s" status)
        $isHumanReadable = isset($responseArray['Open']);

        if ($isHumanReadable) {
            // Human-readable format - no "s" status field
            // Note: Human-readable format does not include symbol data from the API
            $this->status = 'ok';
            $symbols = $responseArray['Symbol'] ?? null;

            $count = count($responseArray['Open']);
            for ($i = 0; $i < $count; $i++) {
                $this->candles[] = new Candle(
                    $responseArray['Open'][$i],
                    $responseArray['High'][$i],
                    $responseArray['Low'][$i],
                    $responseArray['Close'][$i],
                    $responseArray['Volume'][$i],
                    Carbon::parse($responseArray['Date'][$i]),
                    $symbols[$i] ?? null,
                );
            }
        } else {
            // Regular format
            $this->status = $response->s;

            if ($this->status === 'ok') {
                $symbols = $response->symbol ?? null;
                for ($i = 0; $i < count($response->o); $i++) {
                    $this->candles[] = new Candle(
                        $response->o[$i],
                        $response->h[$i],
                        $response->l[$i],
                        $response->c[$i],
                        $response->v[$i],
                        Carbon::parse($response->t[$i]),
                        $symbols[$i] ?? null,
                    );
                }
            }
        }
    }

    /**
     * Returns a string representation of the bulk candles collection.
     *
     * @return string Human-readable bulk candles summary.
     */
    public function __toString(): string
    {
        if (!$this->isJson()) {
            return "BulkCandles - Non-JSON format, use getCsv() or getHtml()";
        }

        $count = count($this->candles);
        $lines = [sprintf("BulkCandles: %d candle%s (status: %s)", $count, $count === 1 ? '' : 's', $this->status)];

        $displayCount = min(3, $count);
        for ($i = 0; $i < $displayCount; $i++) {
            $lines[] = "  " . (string) $this->candles[$i];
        }

        if ($count > 3) {
            $lines[] = sprintf("  ... and %d more", $count - 3);
        }

        return implode("\n", $lines);
    }
}
