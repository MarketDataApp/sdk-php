<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Class Candles
 *
 * Represents a collection of stock candles data and handles the response parsing.
 */
class Candles extends ResponseBase
{

    /**
     * The status of the response. Will always be "ok" when there is data for the candles requested.
     *
     * @var string
     */
    public string $status = 'no_data';

    /**
     * Unix time of the next quote if there is no data in the requested period, but there is data in a subsequent
     * period.
     *
     * @var int|null
     */
    public ?int $next_time = null;

    /**
     * Array of Candle objects representing individual candle data.
     *
     * @var Candle[]
     */
    public array $candles = [];

    /**
     * Constructs a new Candles object and parses the response data.
     *
     * @param object      $response The raw response object to be parsed.
     * @param string|null $symbol   Optional symbol to associate with each candle. Used when the caller
     *                              knows the symbol (e.g., single-symbol candles() requests).
     */
    public function __construct(object $response, ?string $symbol = null)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }

        // Check for merged response flag (used by createMerged())
        if (isset($response->_merged) && $response->_merged === true) {
            // Skip parsing - createMerged will populate fields directly
            $this->status = $response->s ?? 'no_data';
            return;
        }

        // Convert to array for easier access to keys with spaces (human-readable format)
        $responseArray = (array) $response;

        // Determine if this is human-readable format (has "Open" key) or regular format (has "s" status)
        $isHumanReadable = isset($responseArray['Open']);

        if ($isHumanReadable) {
            // Human-readable format - no "s" status field
            $this->status = 'ok';

            $count = count($responseArray['Open']);
            for ($i = 0; $i < $count; $i++) {
                $this->candles[] = new Candle(
                    $responseArray['Open'][$i],
                    $responseArray['High'][$i],
                    $responseArray['Low'][$i],
                    $responseArray['Close'][$i],
                    $responseArray['Volume'][$i],
                    Carbon::parse($responseArray['Date'][$i]),
                    $symbol,
                );
            }
        } else {
            // Regular format
            $this->status = $response->s;

            switch ($this->status) {
                case 'ok':
                    for ($i = 0; $i < count($response->o); $i++) {
                        $this->candles[] = new Candle(
                            $response->o[$i],
                            $response->h[$i],
                            $response->l[$i],
                            $response->c[$i],
                            $response->v[$i],
                            Carbon::parse($response->t[$i]),
                            $symbol,
                        );
                    }
                    break;

                case 'no_data':
                    if (isset($response->nextTime)) {
                        $this->next_time = $response->nextTime;
                    }
                    break;
            }
        }
    }

    /**
     * Create a Candles object from pre-merged data.
     *
     * This static factory method is used by the automatic concurrent request
     * feature to create a Candles object from multiple merged responses.
     *
     * @param string      $status    The overall status ('ok' or 'no_data').
     * @param Candle[]    $candles   Array of Candle objects.
     * @param int|null    $nextTime  Unix timestamp of next available data (for no_data status).
     *
     * @return self A new Candles instance with the merged data.
     */
    public static function createMerged(string $status, array $candles, ?int $nextTime = null): self
    {
        // Create a minimal response object to satisfy the parent constructor
        $response = (object) ['s' => $status, '_merged' => true];

        $instance = new self($response);
        $instance->status = $status;
        $instance->candles = $candles;

        if ($nextTime !== null) {
            $instance->next_time = $nextTime;
        }

        return $instance;
    }

    /**
     * Returns a string representation of the candles collection.
     *
     * @return string Human-readable candles summary.
     */
    public function __toString(): string
    {
        if (!$this->isJson()) {
            return "Candles - Non-JSON format, use getCsv() or getHtml()";
        }

        $count = count($this->candles);
        $lines = [sprintf("Candles: %d candle%s (status: %s)", $count, $count === 1 ? '' : 's', $this->status)];

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
