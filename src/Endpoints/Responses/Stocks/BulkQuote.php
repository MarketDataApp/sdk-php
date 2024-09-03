<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;

/**
 * Represents a bulk quote for a stock with various price and volume information.
 */
class BulkQuote
{

    /**
     * Constructs a new BulkQuote instance.
     *
     * @param string     $symbol              The symbol of the stock.
     * @param float      $ask                 The ask price of the stock.
     * @param int        $ask_size            The number of shares offered at the ask price.
     * @param float      $bid                 The bid price.
     * @param int        $bid_size            The number of shares that may be sold at the bid price.
     * @param float      $mid                 The midpoint price between the ask and the bid.
     * @param float      $last                The last price the stock traded at.
     * @param float|null $change              The difference in price in dollars (or the security's currency if
     *                                        different from dollars) compared to the closing price of the previous
     *                                        day.
     * @param float|null $change_percent      The difference in price in percent, expressed as a decimal, compared to
     *                                        the closing price of the previous day. For example, a 30% change will be
     *                                        represented as 0.30.
     * @param float|null $fifty_two_week_high The 52-week high for the stock. This parameter is omitted unless the
     *                                        optional 52week request parameter is set to true.
     * @param float|null $fifty_two_week_low  The 52-week low for the stock. This parameter is omitted unless the
     *                                        optional 52week request parameter is set to true.
     * @param int        $volume              The number of shares traded during the current session.
     * @param Carbon     $updated             The date/time of the current stock quote.
     */
    public function __construct(
        public string $symbol,
        public float $ask,
        public int $ask_size,
        public float $bid,
        public int $bid_size,
        public float $mid,
        public float $last,
        public float|null $change,
        public float|null $change_percent,
        public float|null $fifty_two_week_high,
        public float|null $fifty_two_week_low,
        public int $volume,
        public Carbon $updated
    ) {
    }
}
