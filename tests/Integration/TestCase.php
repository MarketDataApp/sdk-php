<?php

namespace MarketDataApp\Tests\Integration;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Base class for tests that exercise the live Market Data API.
 */
abstract class TestCase extends PhpUnitTestCase
{
    /**
     * Return the live API token or fail the integration suite explicitly.
     */
    protected function requireMarketDataToken(): string
    {
        $token = getenv('MARKETDATA_TOKEN');
        if ($token === false || $token === '') {
            $token = $_ENV['MARKETDATA_TOKEN'] ?? $_SERVER['MARKETDATA_TOKEN'] ?? null;
        }

        if (!is_string($token) || trim($token) === '') {
            self::fail('MARKETDATA_TOKEN must be set to run integration tests');
        }

        return $token;
    }
}
