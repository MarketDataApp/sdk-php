<?php

namespace MarketDataApp\Tests\Integration\Options;

use MarketDataApp\Client;
use MarketDataApp\Tests\Integration\TestCase;

/**
 * Base test case for Options integration tests.
 *
 * Provides shared setup and properties for all Options integration test classes.
 */
abstract class OptionsTestCase extends TestCase
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
        $this->client = new Client($this->requireMarketDataToken());
    }
}
