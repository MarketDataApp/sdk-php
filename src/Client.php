<?php

namespace MarketDataApp;

use MarketDataApp\Endpoints\Indices;
use MarketDataApp\Endpoints\Markets;
use MarketDataApp\Endpoints\MutualFunds;
use MarketDataApp\Endpoints\Options;
use MarketDataApp\Endpoints\Stocks;
use MarketDataApp\Endpoints\Utilities;

/**
 * Client class for the Market Data API.
 *
 * This class provides access to various endpoints of the Market Data API,
 * including indices, stocks, options, markets, mutual funds, and utilities.
 */
class Client extends ClientBase
{

    /**
     * The index endpoints provided by the Market Data API offer access to both real-time and historical data related to
     * financial indices. These endpoints are designed to cater to a wide range of financial data needs.
     *
     * @var Indices
     */
    public Indices $indices;

    /**
     * Stock endpoints include numerous fundamental, technical, and pricing data.
     *
     * @var Stocks
     */
    public Stocks $stocks;

    /**
     * The Market Data API provides a comprehensive suite of options endpoints, designed to cater to various needs
     * around options data. These endpoints are designed to be flexible and robust, supporting both real-time
     * and historical data queries. They accommodate a wide range of optional parameters for detailed data
     * retrieval, making the Market Data API a versatile tool for options traders and financial analysts.
     *
     * @var Options
     */
    public Options $options;

    /**
     * The Markets endpoints provide reference and status data about the markets covered by Market Data.
     *
     * @var Markets
     */
    public Markets $markets;

    /**
     * The mutual funds endpoints offer access to historical pricing data for mutual funds.
     *
     * @var MutualFunds
     */
    public MutualFunds $mutual_funds;

    /**
     * These endpoints are designed to assist with API-related service issues, including checking the online status and
     * uptime.
     *
     * @var Utilities
     */
    public Utilities $utilities;

    /**
     * Constructor for the Client class.
     *
     * Initializes all endpoint classes with the provided API token.
     *
     * @param string $token The API token for authentication.
     */
    public function __construct($token)
    {
        parent::__construct($token);

        $this->indices = new Indices($this);
        $this->stocks = new Stocks($this);
        $this->options = new Options($this);
        $this->markets = new Markets($this);
        $this->mutual_funds = new MutualFunds($this);
        $this->utilities = new Utilities($this);
    }
}
