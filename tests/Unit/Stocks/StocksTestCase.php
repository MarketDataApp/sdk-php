<?php

namespace MarketDataApp\Tests\Unit\Stocks;

use MarketDataApp\Client;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for Stocks endpoints.
 *
 * Provides shared setup and properties for all Stocks test classes.
 */
abstract class StocksTestCase extends TestCase
{
    use MockResponses;

    /**
     * The client instance used for testing.
     *
     * @var Client
     */
    protected Client $client;

    /**
     * Mocked response data for AAPL stock.
     * Mock response: FROM real API output (captured on 2026-01-22)
     *
     * @var array
     */
    protected array $aapl_mocked_response = [
        's'         => 'ok',
        'symbol'    => ['AAPL'],
        'ask'       => [248.8],
        'askSize'   => [200],
        'bid'       => [248.7],
        'bidSize'   => [600],
        'mid'       => [248.75],
        'last'      => [247.65],
        'change'    => [0.95],
        'changepct' => [0.0039],
        'volume'    => [54933217],
        'updated'   => [1769043595]
    ];

    /**
     * Set up the test environment.
     *
     * This method is called before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        // Save original token state before clearing
        $this->saveMarketDataTokenState();
        
        // Clear MARKETDATA_TOKEN environment variable to ensure empty token is used.
        // This prevents real API calls during Client construction by ensuring
        // _setup_rate_limits() skips the /user/ endpoint validation call.
        $this->clearMarketDataToken();
        
        // Use empty token for unit tests to skip validation (tests use mocks anyway)
        $token = "";
        $client = new Client($token);
        $this->client = $client;
    }

    /**
     * Restore original environment variable state after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->restoreMarketDataTokenState();
        parent::tearDown();
    }
}
