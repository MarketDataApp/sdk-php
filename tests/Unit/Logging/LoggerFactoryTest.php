<?php

namespace MarketDataApp\Tests\Unit\Logging;

use MarketDataApp\Logging\DefaultLogger;
use MarketDataApp\Logging\LoggerFactory;
use MarketDataApp\Settings;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Unit tests for LoggerFactory class.
 */
class LoggerFactoryTest extends TestCase
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

        // Reset the singleton
        LoggerFactory::resetLogger();

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

        // Reset the singleton
        LoggerFactory::resetLogger();

        parent::tearDown();
    }

    public function testGetLogger_withDefaultConfig_returnsDefaultLogger(): void
    {
        $logger = LoggerFactory::getLogger();

        $this->assertInstanceOf(LoggerInterface::class, $logger);
        $this->assertInstanceOf(DefaultLogger::class, $logger);
    }

    public function testGetLogger_returnsSameInstance(): void
    {
        $logger1 = LoggerFactory::getLogger();
        $logger2 = LoggerFactory::getLogger();

        $this->assertSame($logger1, $logger2);
    }

    public function testSetLogger_withCustomLogger_usesCustomLogger(): void
    {
        $customLogger = new NullLogger();

        LoggerFactory::setLogger($customLogger);

        $this->assertSame($customLogger, LoggerFactory::getLogger());
    }

    public function testResetLogger_clearsInstance(): void
    {
        $logger1 = LoggerFactory::getLogger();

        LoggerFactory::resetLogger();

        $logger2 = LoggerFactory::getLogger();

        $this->assertNotSame($logger1, $logger2);
    }

    public function testGetLogger_withNoneLevel_returnsNullLogger(): void
    {
        putenv('MARKETDATA_LOGGING_LEVEL=NONE');
        $_ENV['MARKETDATA_LOGGING_LEVEL'] = 'NONE';

        LoggerFactory::resetLogger();
        $logger = LoggerFactory::getLogger();

        $this->assertInstanceOf(NullLogger::class, $logger);
    }

    public function testGetLogger_withOffLevel_returnsNullLogger(): void
    {
        putenv('MARKETDATA_LOGGING_LEVEL=off');
        $_ENV['MARKETDATA_LOGGING_LEVEL'] = 'off';

        LoggerFactory::resetLogger();
        $logger = LoggerFactory::getLogger();

        $this->assertInstanceOf(NullLogger::class, $logger);
    }

    public function testGetLogger_withDisabledLevel_returnsNullLogger(): void
    {
        putenv('MARKETDATA_LOGGING_LEVEL=disabled');
        $_ENV['MARKETDATA_LOGGING_LEVEL'] = 'disabled';

        LoggerFactory::resetLogger();
        $logger = LoggerFactory::getLogger();

        $this->assertInstanceOf(NullLogger::class, $logger);
    }

    public function testGetLogger_withDebugLevel_returnsDefaultLogger(): void
    {
        putenv('MARKETDATA_LOGGING_LEVEL=DEBUG');
        $_ENV['MARKETDATA_LOGGING_LEVEL'] = 'DEBUG';

        LoggerFactory::resetLogger();
        $logger = LoggerFactory::getLogger();

        $this->assertInstanceOf(DefaultLogger::class, $logger);
    }

    public function testGetLogger_withWarningLevel_returnsDefaultLogger(): void
    {
        putenv('MARKETDATA_LOGGING_LEVEL=WARNING');
        $_ENV['MARKETDATA_LOGGING_LEVEL'] = 'WARNING';

        LoggerFactory::resetLogger();
        $logger = LoggerFactory::getLogger();

        $this->assertInstanceOf(DefaultLogger::class, $logger);
    }
}
