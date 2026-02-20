<?php

namespace MarketDataApp\Tests\Integration\Options;

use MarketDataApp\Client;
use PHPUnit\Framework\TestCase;

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
        // Use the same robust token detection as Settings class
        $token = getenv('MARKETDATA_TOKEN');
        if ($token === false || $token === '') {
            $token = $_ENV['MARKETDATA_TOKEN'] ?? $_SERVER['MARKETDATA_TOKEN'] ?? null;
        }
        if ($token === null || $token === '') {
            $this->markTestSkipped('MARKETDATA_TOKEN environment variable not set');
        }
        $client = new Client($token);
        $this->client = $client;
    }
}
