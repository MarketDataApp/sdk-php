<?php

namespace MarketDataApp\Tests\Unit;

use MarketDataApp\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Settings class.
 *
 * Tests token resolution logic with various scenarios.
 */
class SettingsTest extends TestCase
{
    /**
     * Test that explicit token takes highest precedence.
     *
     * @return void
     */
    public function testGetToken_explicitToken_takesPrecedence()
    {
        // Set up environment variable
        putenv('MARKETDATA_TOKEN=env_token_value');
        $_ENV['MARKETDATA_TOKEN'] = 'env_token_value';

        // Explicit token should be used even if env var is set
        $token = Settings::getToken('explicit_token');
        $this->assertEquals('explicit_token', $token);

        // Clean up
        putenv('MARKETDATA_TOKEN');
        unset($_ENV['MARKETDATA_TOKEN']);
    }

    /**
     * Test that environment variable is used when no explicit token.
     *
     * @return void
     */
    public function testGetToken_envVar_usedWhenNoExplicit()
    {
        // Set environment variable
        $testToken = 'test_env_token_123';
        putenv('MARKETDATA_TOKEN=' . $testToken);
        $_ENV['MARKETDATA_TOKEN'] = $testToken;
        $_SERVER['MARKETDATA_TOKEN'] = $testToken;

        try {
            // No explicit token, should use env var
            $token = Settings::getToken(null);
            $this->assertEquals($testToken, $token);
        } finally {
            // Clean up
            putenv('MARKETDATA_TOKEN');
            unset($_ENV['MARKETDATA_TOKEN']);
            unset($_SERVER['MARKETDATA_TOKEN']);
        }
    }

    /**
     * Test that empty string is returned when no token sources available.
     *
     * @return void
     */
    public function testGetToken_noSources_returnsEmptyString()
    {
        // Save original values
        $originalEnv = getenv('MARKETDATA_TOKEN');
        $originalEnvVar = $_ENV['MARKETDATA_TOKEN'] ?? null;
        $originalServer = $_SERVER['MARKETDATA_TOKEN'] ?? null;

        try {
            // Clear all token sources
            putenv('MARKETDATA_TOKEN');
            unset($_ENV['MARKETDATA_TOKEN']);
            unset($_SERVER['MARKETDATA_TOKEN']);

            // Should return empty string as fallback
            $token = Settings::getToken(null);
            $this->assertEquals('', $token);
        } finally {
            // Restore original values
            if ($originalEnv !== false) {
                putenv('MARKETDATA_TOKEN=' . $originalEnv);
            }
            if ($originalEnvVar !== null) {
                $_ENV['MARKETDATA_TOKEN'] = $originalEnvVar;
            }
            if ($originalServer !== null) {
                $_SERVER['MARKETDATA_TOKEN'] = $originalServer;
            }
        }
    }

    /**
     * Test that explicit empty string is preserved.
     *
     * @return void
     */
    public function testGetToken_explicitEmptyString_preserved()
    {
        // Set environment variable
        putenv('MARKETDATA_TOKEN=env_token_value');
        $_ENV['MARKETDATA_TOKEN'] = 'env_token_value';

        try {
            // Explicit empty string should be used (not env var)
            $token = Settings::getToken('');
            $this->assertEquals('', $token);
        } finally {
            // Clean up
            putenv('MARKETDATA_TOKEN');
            unset($_ENV['MARKETDATA_TOKEN']);
        }
    }

    /**
     * Test that getenv() is checked first for environment variables.
     *
     * @return void
     */
    public function testGetToken_getenvCheckedFirst()
    {
        $testToken = 'getenv_token_value';
        putenv('MARKETDATA_TOKEN=' . $testToken);

        try {
            $token = Settings::getToken(null);
            $this->assertEquals($testToken, $token);
        } finally {
            putenv('MARKETDATA_TOKEN');
        }
    }

    /**
     * Test that $_ENV is checked as fallback.
     *
     * @return void
     */
    public function testGetToken_envVarFallback()
    {
        // Save original values
        $originalEnv = getenv('MARKETDATA_TOKEN');
        $originalEnvVar = $_ENV['MARKETDATA_TOKEN'] ?? null;
        $originalServer = $_SERVER['MARKETDATA_TOKEN'] ?? null;

        try {
            // Clear getenv() first to test $_ENV fallback
            if ($originalEnv !== false) {
                putenv('MARKETDATA_TOKEN');
            }
            
            // Note: This test may not work if variables_order doesn't include 'E'
            // But it's good to test the fallback logic
            $testToken = 'env_var_token_value';
            $_ENV['MARKETDATA_TOKEN'] = $testToken;
            $_SERVER['MARKETDATA_TOKEN'] = $testToken;

            $token = Settings::getToken(null);
            $this->assertEquals($testToken, $token);
        } finally {
            // Restore original values
            if ($originalEnv !== false) {
                putenv('MARKETDATA_TOKEN=' . $originalEnv);
            }
            if ($originalEnvVar !== null) {
                $_ENV['MARKETDATA_TOKEN'] = $originalEnvVar;
            } else {
                unset($_ENV['MARKETDATA_TOKEN']);
            }
            if ($originalServer !== null) {
                $_SERVER['MARKETDATA_TOKEN'] = $originalServer;
            } else {
                unset($_SERVER['MARKETDATA_TOKEN']);
            }
        }
    }
}
