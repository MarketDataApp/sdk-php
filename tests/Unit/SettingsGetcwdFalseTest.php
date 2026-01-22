<?php

/**
 * This test file tests the edge case where getcwd() returns false in Settings::loadDotenv().
 *
 * On Unix systems, getcwd() returns false when the current working directory has been
 * deleted while the process is still "inside" it. This test recreates that scenario.
 */

namespace MarketDataApp\Tests\Unit;

use MarketDataApp\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Settings class getcwd() failure edge case.
 *
 * Tests specifically targeting line 124 (early return when getcwd() returns false)
 * in the Settings::loadDotenv() method.
 *
 * This test simulates the rare scenario where getcwd() returns false by:
 * 1. Creating a temporary directory
 * 2. Changing into it
 * 3. Deleting it from another path reference
 * 4. Now getcwd() returns false because the directory no longer exists
 */
class SettingsGetcwdFalseTest extends TestCase
{
    /**
     * Original environment variable values to restore after tests.
     */
    private $originalToken = false;
    private $originalEnvToken = null;
    private $originalServerToken = null;

    /**
     * Original working directory to restore after tests.
     */
    private ?string $originalCwd = null;

    /**
     * Save original environment state before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Save original working directory
        $cwd = getcwd();
        $this->originalCwd = $cwd !== false ? $cwd : null;

        // Save original values
        $this->originalToken = getenv('MARKETDATA_TOKEN');
        $this->originalEnvToken = $_ENV['MARKETDATA_TOKEN'] ?? null;
        $this->originalServerToken = $_SERVER['MARKETDATA_TOKEN'] ?? null;

        // Reset Settings dotenv loaded flag
        $this->resetDotenvLoaded();
    }

    /**
     * Restore original environment state after each test.
     */
    protected function tearDown(): void
    {
        // Restore original working directory FIRST
        if ($this->originalCwd !== null && is_dir($this->originalCwd)) {
            @chdir($this->originalCwd);
        }

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
     * Test that loadDotenv() handles getcwd() returning false gracefully.
     *
     * This test covers line 124 (early return) in Settings::loadDotenv().
     * It creates a directory, changes into it, then deletes it, causing
     * getcwd() to return false.
     *
     * @return void
     */
    public function testLoadDotenv_getcwdReturnsFalse_returnsEarly()
    {
        // Skip on Windows as this technique doesn't work there
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('This test requires Unix-like filesystem behavior');
        }

        // Create a temporary directory
        $tempDir = sys_get_temp_dir() . '/sdk_getcwd_test_' . uniqid();
        if (!@mkdir($tempDir, 0755, true)) {
            $this->markTestSkipped('Unable to create temp directory');
        }

        // Change into the temp directory
        if (!@chdir($tempDir)) {
            @rmdir($tempDir);
            $this->markTestSkipped('Unable to change to temp directory');
        }

        // Delete the directory while we're still "inside" it
        // This causes getcwd() to return false
        if (!@rmdir($tempDir)) {
            // If we can't delete it (maybe it has files), try cleaning first
            @chdir($this->originalCwd);
            @rmdir($tempDir);
            $this->markTestSkipped('Unable to delete temp directory while inside it');
        }

        // Verify getcwd() now returns false
        $cwd = getcwd();
        if ($cwd !== false) {
            // Some systems may handle this differently
            @chdir($this->originalCwd);
            $this->markTestSkipped('getcwd() did not return false after directory deletion');
        }

        // Clear all environment variables to force .env file lookup
        putenv('MARKETDATA_TOKEN');
        unset($_ENV['MARKETDATA_TOKEN']);
        unset($_SERVER['MARKETDATA_TOKEN']);

        // Reset dotenv loaded flag to force a fresh loadDotenv() call
        $this->resetDotenvLoaded();

        // Call getToken which will trigger loadDotenv()
        // Since getcwd() returns false, loadDotenv() should return early at line 124
        $token = Settings::getToken(null);

        // Restore to original directory before assertions
        if ($this->originalCwd !== null) {
            @chdir($this->originalCwd);
        }

        // Should return empty string since:
        // 1. No explicit token
        // 2. No environment variables
        // 3. loadDotenv() returned early due to getcwd() === false
        $this->assertEquals('', $token);
    }

    /**
     * Test that getDefaultParameters works correctly when getcwd() returns false.
     *
     * This verifies the graceful degradation - even when we can't read the
     * current directory, the system should still function with defaults.
     *
     * @return void
     */
    public function testGetDefaultParameters_getcwdReturnsFalse_usesDefaults()
    {
        // Skip on Windows as this technique doesn't work there
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('This test requires Unix-like filesystem behavior');
        }

        // Create a temporary directory
        $tempDir = sys_get_temp_dir() . '/sdk_getcwd_params_' . uniqid();
        if (!@mkdir($tempDir, 0755, true)) {
            $this->markTestSkipped('Unable to create temp directory');
        }

        // Change into the temp directory
        if (!@chdir($tempDir)) {
            @rmdir($tempDir);
            $this->markTestSkipped('Unable to change to temp directory');
        }

        // Delete the directory while we're still "inside" it
        if (!@rmdir($tempDir)) {
            @chdir($this->originalCwd);
            @rmdir($tempDir);
            $this->markTestSkipped('Unable to delete temp directory while inside it');
        }

        // Verify getcwd() now returns false
        if (getcwd() !== false) {
            @chdir($this->originalCwd);
            $this->markTestSkipped('getcwd() did not return false after directory deletion');
        }

        // Clear all universal parameter environment variables
        $envVars = [
            'MARKETDATA_OUTPUT_FORMAT',
            'MARKETDATA_DATE_FORMAT',
            'MARKETDATA_COLUMNS',
            'MARKETDATA_ADD_HEADERS',
            'MARKETDATA_USE_HUMAN_READABLE',
            'MARKETDATA_MODE',
        ];

        foreach ($envVars as $var) {
            putenv($var);
            unset($_ENV[$var]);
            unset($_SERVER[$var]);
        }

        // Reset dotenv loaded flag
        $this->resetDotenvLoaded();

        // Call getDefaultParameters which uses getEnvValue() internally
        $params = Settings::getDefaultParameters();

        // Restore to original directory before assertions
        if ($this->originalCwd !== null) {
            @chdir($this->originalCwd);
        }

        // Should return default values since .env couldn't be loaded
        $this->assertEquals(\MarketDataApp\Enums\Format::JSON, $params->format);
        $this->assertNull($params->date_format);
        $this->assertNull($params->columns);
        $this->assertNull($params->add_headers);
        $this->assertNull($params->use_human_readable);
        $this->assertNull($params->mode);
    }

    /**
     * Reset Settings::$dotenvLoaded static property.
     *
     * @return void
     */
    private function resetDotenvLoaded(): void
    {
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);
    }
}
