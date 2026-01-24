<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Traits\FormatsForDisplay;

/**
 * Class Earning
 *
 * Represents earnings data for a stock, including fiscal information, report details, and EPS data.
 */
class Earning
{
    use FormatsForDisplay;

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
     * @param string|null $currency        The currency of the earnings report. May be null for future/estimated earnings reports.
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
        public string|null $currency,
        public float|null $reported_eps,
        public float|null $estimated_eps,
        public float|null $surprise_eps,
        public float|null $surprise_eps_pct,
        public Carbon $updated
    ) {
    }

    /**
     * Returns a string representation of the earnings data.
     *
     * @return string Human-readable earnings summary.
     */
    public function __toString(): string
    {
        $reported = $this->reported_eps !== null ? sprintf('$%.2f', $this->reported_eps) : 'N/A';
        $estimated = $this->estimated_eps !== null ? sprintf('$%.2f', $this->estimated_eps) : 'N/A';
        $surprise = '';

        if ($this->surprise_eps !== null && $this->surprise_eps_pct !== null) {
            $sign = $this->surprise_eps >= 0 ? '+' : '';
            $surprise = sprintf(' Surprise: %s$%.2f (%s)', $sign, abs($this->surprise_eps), $this->formatPercent($this->surprise_eps_pct));
        }

        $currency = $this->currency ?? 'N/A';

        $lines = [];
        $lines[] = sprintf(
            "%s Q%d %d: EPS %s vs Est %s%s",
            $this->symbol,
            $this->fiscal_quarter,
            $this->fiscal_year,
            $reported,
            $estimated,
            $surprise
        );
        $lines[] = sprintf(
            "  Period End: %s  Report: %s (%s)",
            $this->formatDate($this->date),
            $this->formatDate($this->report_date),
            $this->report_time
        );
        $lines[] = sprintf(
            "  Currency: %s  Updated: %s",
            $currency,
            $this->formatDateTime($this->updated)
        );

        return implode("\n", $lines);
    }
}
