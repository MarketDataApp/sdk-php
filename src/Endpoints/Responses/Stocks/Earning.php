<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;

class Earning
{

    public function __construct(
        // The symbol of the stock.
        public string $symbol,

        // The fiscal year of the earnings report. This may not always align with the calendar year.
        public int $fiscal_year,

        // The fiscal quarter of the earnings report. This may not always align with the calendar quarter.
        public int $fiscal_quarter,

        // The last calendar day that corresponds to this earnings report.
        public Carbon $date,

        // The date the earnings report was released or is projected to be released.
        public Carbon $report_date,

        // The value will be either before market open, after market close, or during market hours.
        public string $report_time,

        // The currency of the earnings report.
        public string $currency,

        /**
         * The earnings per share reported by the company. Earnings reported are typically non-GAAP unless the company does
         * not report non-GAAP earnings.
         *
         * TIP: GAAP (Generally Accepted Accounting Principles) earnings per share (EPS) count all financial activities
         * except for discontinued operations and major changes in accounting methods. Non-GAAP EPS, on the other hand,
         * typically doesn't include losses or devaluation of assets, and often leaves out irregular expenses like
         * significant restructuring costs, large tax or legal charges, especially for companies not in the
         * financial sector.
         */
        public float|null $reported_eps,

        // The average consensus estimate by Wall Street analysts.
        public float|null $estimated_eps,

        // The difference (in earnings per share) between the estimated  earnings per share and the reported earnings per
        // share.
        public float|null $surprise_eps,

        // The difference in percentage terms between the estimated EPS and the reported EPS.
        public float|null $surprise_eps_pct,

        // The date/time the earnings data for this ticker was last updated.
        public Carbon $updated
    ) {
    }
}
