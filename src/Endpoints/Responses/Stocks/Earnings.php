<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Class Earnings
 *
 * Represents a collection of earnings data for stocks and handles the response parsing.
 */
class Earnings extends ResponseBase
{

    /**
     * The status of the response. Will always be "ok" when there is data for the symbol requested.
     *
     * @var string
     */
    public string $status;

    /**
     * Array of Earning objects representing individual stock earnings data.
     *
     * @var Earning[]
     */
    public array $earnings;

    /**
     * Constructs a new Earnings object and parses the response data.
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
            $this->status = 'ok';
            
            $count = count($responseArray['Symbol']);
            for ($i = 0; $i < $count; $i++) {
                $this->earnings[] = new Earning(
                    symbol: $responseArray['Symbol'][$i],
                    fiscal_year: $responseArray['Fiscal Year'][$i],
                    fiscal_quarter: $responseArray['Fiscal Quarter'][$i],
                    date: Carbon::parse($responseArray['Date'][$i]),
                    report_date: Carbon::parse($responseArray['Report Date'][$i]),
                    report_time: $responseArray['Report Time'][$i],
                    currency: $responseArray['Currency'][$i] ?? null,
                    reported_eps: $responseArray['Reported EPS'][$i],
                    estimated_eps: $responseArray['Estimated EPS'][$i],
                    surprise_eps: $responseArray['Surprise EPS'][$i],
                    surprise_eps_pct: $responseArray['Surprise EPS %'][$i],
                    updated: Carbon::parse($responseArray['Updated'][$i]),
                );
            }
        } else {
            // Regular format
            $this->status = $response->s;

            if ($this->status === 'ok') {
                for ($i = 0; $i < count($response->symbol); $i++) {
                    $this->earnings[] = new Earning(
                        symbol: $response->symbol[$i],
                        fiscal_year: $response->fiscalYear[$i],
                        fiscal_quarter: $response->fiscalQuarter[$i],
                        date: Carbon::parse($response->date[$i]),
                        report_date: Carbon::parse($response->reportDate[$i]),
                        report_time: $response->reportTime[$i],
                        currency: $response->currency[$i] ?? null,
                        reported_eps: $response->reportedEPS[$i],
                        estimated_eps: $response->estimatedEPS[$i],
                        surprise_eps: $response->surpriseEPS[$i],
                        surprise_eps_pct: $response->surpriseEPSpct[$i],
                        updated: Carbon::parse($response->updated[$i]),
                    );
                }
            }
        }
    }

    /**
     * Returns a string representation of the earnings collection.
     *
     * @return string Human-readable earnings summary.
     */
    public function __toString(): string
    {
        if (!$this->isJson()) {
            return "Earnings - Non-JSON format, use getCsv() or getHtml()";
        }

        $count = count($this->earnings ?? []);
        $lines = [sprintf("Earnings: %d record%s (status: %s)", $count, $count === 1 ? '' : 's', $this->status)];

        $displayCount = min(3, $count);
        for ($i = 0; $i < $displayCount; $i++) {
            $lines[] = "  " . (string) $this->earnings[$i];
        }

        if ($count > 3) {
            $lines[] = sprintf("  ... and %d more", $count - 3);
        }

        return implode("\n", $lines);
    }
}
