<?php

namespace MarketDataApp\Tests\Unit\Logging;

use MarketDataApp\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Settings::getLogLevel() method.
 */
class SettingsLogLevelTest extends TestCase
{
    private ?string $originalLogLevel = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Save and clear the logging level env var
        $this->originalLogLevel = getenv('MARKETDATA_LOGGING_LEVEL') ?: null;
        putenv('MARKETDATA_LOGGING_LEVEL');
        unset($_ENV['MARKETDATA_LOGGING_LEVEL']);
        unset($_SERVER['MARKETDATA_LOGGING_LEVEL']);

        // Reset Settings dotenvLoaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);
    }

    protected function tearDown(): void
    {
        // Restore the original log level
        if ($this->originalLogLevel !== null) {
            putenv("MARKETDATA_LOGGING_LEVEL={$this->originalLogLevel}");
            $_ENV['MARKETDATA_LOGGING_LEVEL'] = $this->originalLogLevel;
        } else {
            putenv('MARKETDATA_LOGGING_LEVEL');
            unset($_ENV['MARKETDATA_LOGGING_LEVEL']);
        }

        parent::tearDown();
    }

    public function testGetLogLevel_withNoEnvVar_returnsInfo(): void
    {
        $level = Settings::getLogLevel();

        $this->assertEquals('INFO', $level);
    }

    public function testGetLogLevel_withDebugEnvVar_returnsDebug(): void
    {
        putenv('MARKETDATA_LOGGING_LEVEL=DEBUG');
        $_ENV['MARKETDATA_LOGGING_LEVEL'] = 'DEBUG';

        $level = Settings::getLogLevel();

        $this->assertEquals('DEBUG', $level);
    }

    public function testGetLogLevel_withWarningEnvVar_returnsWarning(): void
    {
        putenv('MARKETDATA_LOGGING_LEVEL=WARNING');
        $_ENV['MARKETDATA_LOGGING_LEVEL'] = 'WARNING';

        $level = Settings::getLogLevel();

        $this->assertEquals('WARNING', $level);
    }

    public function testGetLogLevel_withErrorEnvVar_returnsError(): void
    {
        putenv('MARKETDATA_LOGGING_LEVEL=ERROR');
        $_ENV['MARKETDATA_LOGGING_LEVEL'] = 'ERROR';

        $level = Settings::getLogLevel();

        $this->assertEquals('ERROR', $level);
    }

    public function testGetLogLevel_withNoneEnvVar_returnsNone(): void
    {
        putenv('MARKETDATA_LOGGING_LEVEL=NONE');
        $_ENV['MARKETDATA_LOGGING_LEVEL'] = 'NONE';

        $level = Settings::getLogLevel();

        $this->assertEquals('NONE', $level);
    }

    public function testGetLogLevel_withLowercaseEnvVar_preservesCase(): void
    {
        putenv('MARKETDATA_LOGGING_LEVEL=debug');
        $_ENV['MARKETDATA_LOGGING_LEVEL'] = 'debug';

        $level = Settings::getLogLevel();

        // The method returns the raw value, case preserved
        $this->assertEquals('debug', $level);
    }

    public function testGetLogLevel_withEnvSuperGlobal_returnsValue(): void
    {
        // Only set in $_ENV, not via putenv
        $_ENV['MARKETDATA_LOGGING_LEVEL'] = 'CRITICAL';

        $level = Settings::getLogLevel();

        $this->assertEquals('CRITICAL', $level);
    }

    public function testGetLogLevel_withServerSuperGlobal_returnsValue(): void
    {
        // Only set in $_SERVER, not via putenv or $_ENV
        $_SERVER['MARKETDATA_LOGGING_LEVEL'] = 'ALERT';

        $level = Settings::getLogLevel();

        $this->assertEquals('ALERT', $level);

        // Cleanup
        unset($_SERVER['MARKETDATA_LOGGING_LEVEL']);
    }

    public function testGetLogLevel_putenvTakesPrecedenceOverEnv(): void
    {
        putenv('MARKETDATA_LOGGING_LEVEL=DEBUG');
        $_ENV['MARKETDATA_LOGGING_LEVEL'] = 'ERROR';

        $level = Settings::getLogLevel();

        // putenv should take precedence
        $this->assertEquals('DEBUG', $level);
    }
}
