<?php

namespace MarketDataApp\Tests\Integration\Stocks;

use MarketDataApp\Client;
use MarketDataApp\Tests\Integration\TestCase;

/**
 * Base test case for Stocks integration tests.
 *
 * Provides shared setup and properties for all Stocks integration test classes.
 */
abstract class StocksTestCase extends TestCase
{
    /**
     * The client instance used for testing.
     *
     * @var Client
     */
    protected Client $client;

    /**
     * Set up the test environment.
     * Initializes a new Client instance with the API token.
     */
    protected function setUp(): void
    {
        error_reporting(E_ALL);
        $this->client = new Client($this->requireMarketDataToken());
    }
}
