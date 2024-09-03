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

        // Convert the response to this object.
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
                    currency: $response->currency[$i],
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
