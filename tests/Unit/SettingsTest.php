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
     * Original environment variable values to restore after tests.
     */
    private $originalToken = false;
    private $originalEnvToken = null;
    private $originalServerToken = null;

    /**
     * Save original environment variable state before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Save original values
        $this->originalToken = getenv('MARKETDATA_TOKEN');
        $this->originalEnvToken = $_ENV['MARKETDATA_TOKEN'] ?? null;
        $this->originalServerToken = $_SERVER['MARKETDATA_TOKEN'] ?? null;
    }

    /**
     * Restore original environment variable state after each test.
     */
    protected function tearDown(): void
    {
        // Restore original environment variable state
        if ($this->originalToken !== false) {
            putenv('MARKETDATA_TOKEN=' . $this->originalToken);
        } else {
            putenv('MARKETDATA_TOKEN');
        }
        
        if ($this->originalEnvToken !== null) {
            $_ENV['MARKETDATA_TOKEN'] = $this->originalEnvToken;
        } else {
            unset($_ENV['MARKETDATA_TOKEN']);
        }
        
        if ($this->originalServerToken !== null) {
            $_SERVER['MARKETDATA_TOKEN'] = $this->originalServerToken;
        } else {
            unset($_SERVER['MARKETDATA_TOKEN']);
        }
        
        parent::tearDown();
    }

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

        // No explicit token, should use env var
        $token = Settings::getToken(null);
        $this->assertEquals($testToken, $token);
    }

    /**
     * Test that empty string is returned when no token sources available.
     *
     * @return void
     */
    public function testGetToken_noSources_returnsEmptyString()
    {
        // Clear all token sources
        putenv('MARKETDATA_TOKEN');
        unset($_ENV['MARKETDATA_TOKEN']);
        unset($_SERVER['MARKETDATA_TOKEN']);

        // Should return empty string as fallback
        $token = Settings::getToken(null);
        $this->assertEquals('', $token);
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

        // Explicit empty string should be used (not env var)
        $token = Settings::getToken('');
        $this->assertEquals('', $token);
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

        $token = Settings::getToken(null);
        $this->assertEquals($testToken, $token);
    }

    /**
     * Test that $_ENV is checked as fallback.
     *
     * @return void
     */
    public function testGetToken_envVarFallback()
    {
        // Clear getenv() first to test $_ENV fallback
        putenv('MARKETDATA_TOKEN');
        
        // Note: This test may not work if variables_order doesn't include 'E'
        // But it's good to test the fallback logic
        $testToken = 'env_var_token_value';
        $_ENV['MARKETDATA_TOKEN'] = $testToken;
        $_SERVER['MARKETDATA_TOKEN'] = $testToken;

        $token = Settings::getToken(null);
        $this->assertEquals($testToken, $token);
    }
}
