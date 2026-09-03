<?php

namespace MarketDataApp\Tests\Integration\UniversalParameters;

use MarketDataApp\Client;
use MarketDataApp\Tests\Integration\TestCase;

/**
 * Base test case for Universal Parameters integration tests.
 *
 * Provides shared setup for integration tests that verify universal parameters
 * work correctly with the actual API.
 */
abstract class UniversalParametersTestCase extends TestCase
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
