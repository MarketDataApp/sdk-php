<?php

namespace MarketDataApp\Endpoints\Responses\MutualFunds;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Represents a collection of financial candles for mutual funds.
 */
class Candles extends ResponseBase
{

    /**
     * Status of the candles request. Will always be ok when there is data for the candles requested.
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
     * Array of Candle objects representing financial data for mutual funds.
     *
     * @var Candle[]
     */
    public array $candles = [];

    /**
     * Constructs a new Candles instance from the given response object.
     *
     * @param object $response The response object containing candle data.
     */
    public function __construct(object $response)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }

        // Convert the response to this object.
        $this->status = $response->s;

        switch ($this->status) {
            case 'ok':
                for ($i = 0; $i < count($response->o); $i++) {
                    $this->candles[] = new Candle(
                        $response->o[$i],
                        $response->h[$i],
                        $response->l[$i],
                        $response->c[$i],
                        Carbon::parse($response->t[$i]),
                    );
                }
                break;

            case 'no_data' && isset($response->nextTime):
                $this->next_time = $response->nextTime;
                break;
        }
    }

    /**
     * Returns a string representation of the mutual funds candles collection.
     *
     * @return string Human-readable candles summary.
     */
    public function __toString(): string
    {
        if (!$this->isJson()) {
            return "MutualFunds Candles - Non-JSON format, use getCsv() or getHtml()";
        }

        $count = count($this->candles);
        $lines = [sprintf("MutualFunds Candles: %d candle%s (status: %s)", $count, $count === 1 ? '' : 's', $this->status)];

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
