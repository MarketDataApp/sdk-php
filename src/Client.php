<?php

namespace MarketDataApp;

use MarketDataApp\Endpoints\Indices;
use MarketDataApp\Endpoints\Options;
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

    /**
     * The Market Data API provides a comprehensive suite of options endpoints, designed to cater to various needs
     * around options data. These endpoints are designed to be flexible and robust, supporting both real-time
     * and historical data queries. They accommodate a wide range of optional parameters for detailed data
     * retrieval, making the Market Data API a versatile tool for options traders and financial analysts.
     */
    public Options $options;

    public function __construct($token)
    {
        parent::__construct($token);

        $this->indices = new Indices($this);
        $this->stocks = new Stocks($this);
        $this->options = new Options($this);
    }
}
