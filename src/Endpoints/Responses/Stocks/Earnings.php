<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;

class Earnings
{

    // Will always be ok when there is data for the symbol requested.
    public string $status;

    /** @var Earning[] $earnings */
    public array $earnings;

    public function __construct(object $response)
    {
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
