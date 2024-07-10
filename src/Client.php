<?php

namespace MarketDataApp;

use MarketDataApp\Endpoints\Indices;
use MarketDataApp\Endpoints\Stocks;

class Client extends ClientBase
{

    /**
     * The index endpoints provided by the Market Data API offer access to both real-time and historical data related to
     * financial indices. These endpoints are designed to cater to a wide range of financial data needs.
     */
    public Indices $indices;

    /**
     * Stock endpoints include numerous fundamental, technical, and pricing data.
     */
    public Stocks $stocks;

    public function __construct($token)
    {
        parent::__construct($token);

        $this->indices = new Indices($this);
        $this->stocks = new Stocks($this);
    }
}
