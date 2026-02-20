<?php

namespace MarketDataApp;

use MarketDataApp\Endpoints\Markets;
use MarketDataApp\Endpoints\MutualFunds;
use MarketDataApp\Endpoints\Options;
use MarketDataApp\Endpoints\Stocks;
use MarketDataApp\Endpoints\Utilities;
use MarketDataApp\Logging\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * Client class for the Market Data API.
 *
 * This class provides access to various endpoints of the Market Data API,
 * including stocks, options, markets, mutual funds, and utilities.
 */
class Client extends ClientBase
{

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
     * @param string|null          $token  The API token for authentication. If not provided, the token will be
     *                                     automatically resolved from MARKETDATA_TOKEN environment variable or .env file.
     *                                     An empty string is allowed for accessing free symbols like AAPL.
     *                                     A valid token is required for authenticated endpoints. An invalid token will throw
     *                                     UnauthorizedException during construction.
     * @param LoggerInterface|null $logger Optional PSR-3 logger instance. If not provided, uses the default logger
     *                                     configured via MARKETDATA_LOGGING_LEVEL environment variable.
     *
     * @throws \MarketDataApp\Exceptions\UnauthorizedException If the token is invalid (non-empty but returns 401 from /user endpoint)
     */
    public function __construct(?string $token = null, ?LoggerInterface $logger = null)
    {
        // Initialize logger first so it's available for ClientBase
        $this->logger = $logger ?? LoggerFactory::getLogger();

        // Log initialization
        $this->logger->info('MarketDataClient initialized');

        // Log obfuscated token at DEBUG level
        $resolvedToken = Settings::getToken($token);
        $this->logger->debug('Token: {token}', ['token' => self::obfuscateToken($resolvedToken)]);

        parent::__construct($token, $this->logger);

        $this->stocks = new Stocks($this);
        $this->options = new Options($this);
        $this->markets = new Markets($this);
        $this->mutual_funds = new MutualFunds($this);
        $this->utilities = new Utilities($this);
    }

    /**
     * Obfuscate token for logging - show full length with asterisks, last 4 chars visible.
     *
     * Example: "abc123xyz789" becomes "********z789"
     *
     * @param string $token The token to obfuscate.
     *
     * @return string The obfuscated token.
     */
    private static function obfuscateToken(string $token): string
    {
        if (strlen($token) <= 4) {
            return str_repeat('*', strlen($token));
        }
        return str_repeat('*', strlen($token) - 4) . substr($token, -4);
    }
}
