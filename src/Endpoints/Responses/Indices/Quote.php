<?php

namespace MarketDataApp\Endpoints\Responses\Indices;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Represents a financial quote for an index.
 */
class Quote extends ResponseBase
{

    /**
     * Status of the quote request. Will always be ok when there is data for the symbol requested.
     */
    public string $status;

    /**
     * The symbol of the index.
     */
    public string $symbol;

    /**
     * The last price of the index.
     */
    public float $last;

    /**
     * The difference in price in dollars (or the index's native currency if different from dollars) compared to the
     * closing price of the previous day.
     */
    public float|null $change;

    /**
     * The difference in price in percent compared to the closing price of the previous day.
     */
    public float|null $change_percent;

    /**
     * The 52-week high for the index.
     */
    public float|null $fifty_two_week_high = null;

    /**
     * The 52-week low for the index.
     */
    public float|null $fifty_two_week_low = null;

    /**
     * The date/time of the quote.
     */
    public Carbon $updated;

    /**
     * Constructs a new Quote instance.
     *
     * @param object $response The response object to be processed.
     */
    public function __construct(object $response)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }

        $this->status = $response->s;
        if ($this->status === "ok") {
            $this->symbol = $response->symbol[0];
            $this->last = $response->last[0];
            $this->change = $response->change[0];
            $this->change_percent = $response->changepct[0];
            if (isset($response->{'52weekHigh'})) {
                $this->fifty_two_week_high = $response->{'52weekHigh'}[0];
            }
            if (isset($response->{'52weekLow'})) {
                $this->fifty_two_week_low = $response->{'52weekLow'}[0];
            }
            $this->updated = Carbon::parse($response->updated[0]);
        }
    }
}
