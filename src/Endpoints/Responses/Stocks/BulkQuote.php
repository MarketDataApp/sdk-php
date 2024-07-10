<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;

class BulkQuote
{

    // The symbol of the stock.
    public string $symbol;

    // The ask price of the stock.
    public float $ask;

    // The number of shares offered at the ask price.
    public int $ask_size;

    // The bid price.
    public float $bid;

    // The number of shares that may be sold at the bid price.
    public int $bid_size;

    // The midpoint price between the ask and the bid.
    public float $mid;

    // The last price the stock traded at.
    public float $last;

    // The difference in price in dollars (or the security's currency if different from dollars) compared to the closing
    // price of the previous day.
    public float $change;

    // The difference in price in percent, expressed as a decimal, compared to the closing price of the previous day.
    // For example, a 30% change will be represented as 0.30.
    public float $change_percent;

    // The 52-week high for the stock. This parameter is omitted unless the optional 52week request parameter is set to
    // true.
    public float|null $fifty_two_week_high = null;

    // The 52-week low for the stock. This parameter is omitted unless the optional 52week request parameter is set to
    // true.
    public float|null $fifty_two_week_low = null;

    // The number of shares traded during the current session.
    public int $volume;

    // The date/time of the current stock quote.
    public Carbon $updated;

    public function __construct(
        string $symbol,
        float $ask,
        int $ask_size,
        float $bid,
        int $bid_size,
        float $mid,
        float $last,
        float $change,
        float $change_percent,
        float|null $fifty_two_week_high,
        float|null $fifty_two_week_low,
        int $volume,
        Carbon $updated
    ) {
        $this->symbol = $symbol;
        $this->ask = $ask;
        $this->ask_size = $ask_size;
        $this->bid = $bid;
        $this->bid_size = $bid_size;
        $this->mid = $mid;
        $this->last = $last;
        $this->change = $change;
        $this->change_percent = $change_percent;
        if(!is_null($fifty_two_week_high)) {
            $this->fifty_two_week_high = $fifty_two_week_high;
        }
        if(!is_null($fifty_two_week_low)) {
            $this->fifty_two_week_low = $fifty_two_week_low;
        }
        $this->volume = $volume;
        $this->updated = $updated;
    }
}
