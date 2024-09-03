<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;

/**
 * Class Earning
 *
 * Represents earnings data for a stock, including fiscal information, report details, and EPS data.
 */
class Earning
{

    /**
     * Constructs a new Earning object with detailed earnings information.
     *
     * @param string     $symbol           The symbol of the stock.
     * @param int        $fiscal_year      The fiscal year of the earnings report. This may not always align with the
     *                                     calendar year.
     * @param int        $fiscal_quarter   The fiscal quarter of the earnings report. This may not always align with
     *                                     the calendar quarter.
     * @param Carbon     $date             The last calendar day that corresponds to this earnings report.
     * @param Carbon     $report_date      The date the earnings report was released or is projected to be released.
     * @param string     $report_time      The value will be either before market open, after market close, or during
     *                                     market hours.
     * @param string     $currency         The currency of the earnings report.
     * @param float|null $reported_eps     The earnings per share reported by the company. Earnings reported are
     *                                     typically non-GAAP unless the company does not report non-GAAP earnings.
     * @param float|null $estimated_eps    The average consensus estimate by Wall Street analysts.
     * @param float|null $surprise_eps     The difference (in earnings per share) between the estimated earnings per
     *                                     share and the reported earnings per share.
     * @param float|null $surprise_eps_pct The difference in percentage terms between the estimated EPS and the
     *                                     reported EPS.
     * @param Carbon     $updated          The date/time the earnings data for this ticker was last updated.
     */
    public function __construct(
        public string $symbol,
        public int $fiscal_year,
        public int $fiscal_quarter,
        public Carbon $date,
        public Carbon $report_date,
        public string $report_time,
        public string $currency,
        public float|null $reported_eps,
        public float|null $estimated_eps,
        public float|null $surprise_eps,
        public float|null $surprise_eps_pct,
        public Carbon $updated
    ) {
    }
}
