<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;

class BulkQuote
{

    public function __construct(
        // The symbol of the stock.
        public string $symbol,

        // The ask price of the stock.
        public float $ask,

        // The number of shares offered at the ask price.
        public int $ask_size,

        // The bid price.
        public float $bid,

        // The number of shares that may be sold at the bid price.
        public int $bid_size,

        // The midpoint price between the ask and the bid.
        public float $mid,

        // The last price the stock traded at.
        public float $last,

        // The difference in price in dollars (or the security's currency if different from dollars) compared to the closing
        // price of the previous day.
        public float|null $change,

        // The difference in price in percent, expressed as a decimal, compared to the closing price of the previous day.
        // For example, a 30% change will be represented as 0.30.
        public float|null $change_percent,

        // The 52-week high for the stock. This parameter is omitted unless the optional 52week request parameter is set to
        // true.
        public float|null $fifty_two_week_high,

        // The 52-week low for the stock. This parameter is omitted unless the optional 52week request parameter is set to
        // true.
        public float|null $fifty_two_week_low,

        // The number of shares traded during the current session.
        public int $volume,

        // The date/time of the current stock quote.
        public Carbon $updated
    ) {
    }
}
