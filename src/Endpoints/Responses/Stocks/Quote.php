<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Class Quote
 *
 * Represents a stock quote and handles the response parsing for stock quote data.
 */
class Quote extends ResponseBase
{

    /**
     * The status of the response. Will always be "ok" when there is data for the symbol requested.
     *
     * @var string
     */
    public string $status;

    /**
     * The symbol of the stock.
     *
     * @var string
     */
    public string $symbol;

    /**
     * The ask price of the stock.
     *
     * @var float
     */
    public float $ask;

    /**
     * The number of shares offered at the ask price.
     *
     * @var int
     */
    public int $ask_size;

    /**
     * The bid price.
     *
     * @var float
     */
    public float $bid;

    /**
     * The number of shares that may be sold at the bid price.
     *
     * @var int
     */
    public int $bid_size;

    /**
     * The midpoint price between the ask and the bid.
     *
     * @var float
     */
    public float $mid;

    /**
     * The last price the stock traded at.
     *
     * @var float
     */
    public float $last;

    /**
     * The difference in price in dollars (or the security's currency if different from dollars) compared to the closing
     * price of the previous day.
     *
     * @var float|null
     */
    public float|null $change;

    /**
     * The difference in price in percent, expressed as a decimal, compared to the closing price of the previous day.
     * For example, a 30% change will be represented as 0.30.
     *
     * @var float|null
     */
    public float|null $change_percent;

    /**
     * The 52-week high for the stock. This parameter is omitted unless the optional 52week request parameter is set to
     * true.
     *
     * @var float|null
     */
    public float|null $fifty_two_week_high = null;

    /**
     * The 52-week low for the stock. This parameter is omitted unless the optional 52week request parameter is set to
     * true.
     *
     * @var float|null
     */
    public float|null $fifty_two_week_low = null;

    /**
     * The number of shares traded during the current session.
     *
     * @var int
     */
    public int $volume;

    /**
     * The date/time of the current stock quote.
     *
     * @var Carbon
     */
    public Carbon $updated;

    /**
     * Constructs a new Quote object and parses the response data.
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
        $this->symbol = $response->symbol[0];
        $this->ask = $response->ask[0];
        $this->ask_size = $response->askSize[0];
        $this->bid = $response->bid[0];
        $this->bid_size = $response->bidSize[0];
        $this->mid = $response->mid[0];
        $this->last = $response->last[0];
        $this->change = $response->change[0];
        $this->change_percent = $response->changepct[0];
        if (isset($response->{'52weekHigh'}[0])) {
            $this->fifty_two_week_high = $response->{'52weekHigh'}[0];
        }
        if (isset($response->{'52weekLow'}[0])) {
            $this->fifty_two_week_low = $response->{'52weekLow'}[0];
        }
        $this->volume = $response->volume[0];
        $this->updated = Carbon::parse($response->updated[0]);
    }
}
