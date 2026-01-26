<?php

namespace MarketDataApp\Endpoints\Responses\Markets;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Represents a collection of market statuses for different dates.
 */
class Statuses extends ResponseBase
{

    /**
     * The status of the response. Will always be ok when there is data for the dates requested.
     *
     * @var string
     */
    public string $status = 'no_data';

    /**
     * Array of Status objects representing market statuses for different dates.
     *
     * @var Status[]
     */
    public array $statuses = [];

    /**
     * Constructs a new Statuses instance from the given response object.
     *
     * @param object $response The response object containing market status data.
     */
    public function __construct(object $response)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }
        // Convert to array for easier access to keys with spaces (human-readable format)
        $responseArray = (array) $response;

        // Determine if this is human-readable format (has "Status" key) or regular format (has "s" status)
        $isHumanReadable = isset($responseArray['Status']);

        if ($isHumanReadable) {
            // Human-readable format - no "s" status field, single status object
            $this->status = 'ok';
            // Handle Date field - ensure it's a string (may be array when object is cast to array)
            $dateValue = $responseArray['Date'];
            if (is_array($dateValue)) {
                $dateValue = !empty($dateValue) ? $dateValue[0] : '';
            }
            // Parse date - handle both Unix timestamps and date strings
            $date = is_numeric($dateValue) 
                ? Carbon::createFromTimestamp((int) $dateValue)
                : Carbon::parse($dateValue);
            $this->statuses[] = new Status(
                $date,
                is_array($responseArray['Status']) ? ($responseArray['Status'][0] ?? null) : ($responseArray['Status'] ?? null),
            );
        } else {
            // Regular format
            $this->status = $response->s;

            if ($this->status === 'ok') {
                for ($i = 0; $i < count($response->date); $i++) {
                    $this->statuses[] = new Status(
                        Carbon::parse($response->date[$i]),
                        $response->status[$i] ?? null,
                    );
                }
            }
        }
    }

    /**
     * Returns a string representation of the market statuses collection.
     *
     * @return string Human-readable market statuses summary.
     */
    public function __toString(): string
    {
        if (!$this->isJson()) {
            return "Market Statuses - Non-JSON format, use getCsv() or getHtml()";
        }

        $count = count($this->statuses);
        $lines = [sprintf("Market Statuses: %d date%s (status: %s)", $count, $count === 1 ? '' : 's', $this->status)];

        $displayCount = min(3, $count);
        for ($i = 0; $i < $displayCount; $i++) {
            $lines[] = "  " . (string) $this->statuses[$i];
        }

        if ($count > 3) {
            $lines[] = sprintf("  ... and %d more", $count - 3);
        }

        return implode("\n", $lines);
    }
}
