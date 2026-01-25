<?php

namespace MarketDataApp\Tests\Unit\Logging;

use MarketDataApp\Logging\DefaultLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * Unit tests for DefaultLogger class.
 */
class DefaultLoggerTest extends TestCase
{
    /**
     * @var resource Memory stream to capture logger output
     */
    private $outputStream;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a memory stream to capture logger output
        $this->outputStream = fopen('php://memory', 'r+');
    }

    protected function tearDown(): void
    {
        if ($this->outputStream) {
            fclose($this->outputStream);
        }
        parent::tearDown();
    }

    /**
     * Create a logger that outputs to our capture stream.
     */
    private function createLogger(string $level): DefaultLogger
    {
        return new DefaultLogger($level, $this->outputStream);
    }

    /**
     * Get captured output from the stream.
     */
    private function getCapturedOutput(): string
    {
        rewind($this->outputStream);
        return stream_get_contents($this->outputStream);
    }

    public function testLogAtInfoLevel_withInfoMessage_logsMessage(): void
    {
        $logger = $this->createLogger(LogLevel::INFO);

        $logger->info('Test message');

        $output = $this->getCapturedOutput();
        $this->assertStringContainsString('Test message', $output);
    }

    public function testLogAtDebugLevel_withInfoMinLevel_skipsMessage(): void
    {
        $logger = $this->createLogger(LogLevel::INFO);

        // Debug message should be silently skipped when min level is INFO
        $logger->debug('Debug message');

        $output = $this->getCapturedOutput();
        $this->assertEmpty($output);
    }

    public function testLogAtErrorLevel_withInfoMinLevel_logsMessage(): void
    {
        $logger = $this->createLogger(LogLevel::INFO);

        $logger->error('Error message');

        $output = $this->getCapturedOutput();
        $this->assertStringContainsString('Error message', $output);
    }

    public function testLogWithContext_interpolatesPlaceholders(): void
    {
        $logger = $this->createLogger(LogLevel::DEBUG);

        $logger->info('User {name} logged in', ['name' => 'John']);

        $output = $this->getCapturedOutput();
        $this->assertStringContainsString('User John logged in', $output);
    }

    public function testLogWithNumericContext_interpolatesCorrectly(): void
    {
        $logger = $this->createLogger(LogLevel::DEBUG);

        $logger->info('Count: {count}', ['count' => 42]);

        $output = $this->getCapturedOutput();
        $this->assertStringContainsString('Count: 42', $output);
    }

    public function testLogWithObjectContext_usesToString(): void
    {
        $logger = $this->createLogger(LogLevel::DEBUG);

        $obj = new class {
            public function __toString(): string
            {
                return 'StringableObject';
            }
        };

        $logger->info('Object: {obj}', ['obj' => $obj]);

        $output = $this->getCapturedOutput();
        $this->assertStringContainsString('Object: StringableObject', $output);
    }

    public function testLogWithNonStringableContext_skipsInterpolation(): void
    {
        $logger = $this->createLogger(LogLevel::DEBUG);

        // Non-stringable objects should be skipped (placeholder remains)
        $logger->info('Array: {arr}', ['arr' => ['a', 'b', 'c']]);

        $output = $this->getCapturedOutput();
        $this->assertStringContainsString('Array: {arr}', $output);
    }

    public function testAllLogLevels_areSupported(): void
    {
        $logger = $this->createLogger(LogLevel::DEBUG);

        $logger->debug('Debug');
        $logger->info('Info');
        $logger->notice('Notice');
        $logger->warning('Warning');
        $logger->error('Error');
        $logger->critical('Critical');
        $logger->alert('Alert');
        $logger->emergency('Emergency');

        $output = $this->getCapturedOutput();
        $this->assertStringContainsString('DEBUG: Debug', $output);
        $this->assertStringContainsString('INFO: Info', $output);
        $this->assertStringContainsString('NOTICE: Notice', $output);
        $this->assertStringContainsString('WARNING: Warning', $output);
        $this->assertStringContainsString('ERROR: Error', $output);
        $this->assertStringContainsString('CRITICAL: Critical', $output);
        $this->assertStringContainsString('ALERT: Alert', $output);
        $this->assertStringContainsString('EMERGENCY: Emergency', $output);
    }

    public function testConstructor_withUppercaseLevel_normalizesToLowercase(): void
    {
        $logger = new DefaultLogger('INFO', $this->outputStream);

        $logger->info('Test');

        $output = $this->getCapturedOutput();
        $this->assertStringContainsString('Test', $output);
    }

    public function testLog_withInvalidLevel_skipsMessage(): void
    {
        $logger = $this->createLogger(LogLevel::INFO);

        // Invalid level should be silently ignored
        $logger->log('invalid_level', 'Test message');

        $output = $this->getCapturedOutput();
        $this->assertEmpty($output);
    }

    public function testLog_withInvalidMinLevel_skipsAllMessages(): void
    {
        $logger = new DefaultLogger('invalid', $this->outputStream);

        // With invalid min level, all messages should be skipped
        $logger->info('Test');

        $output = $this->getCapturedOutput();
        $this->assertEmpty($output);
    }

    public function testLevelFiltering_debugBelowInfo(): void
    {
        $logger = $this->createLogger(LogLevel::INFO);

        $reflection = new \ReflectionClass($logger);
        $levelsProperty = $reflection->getProperty('levels');
        $levels = $levelsProperty->getValue($logger);

        $this->assertLessThan($levels[LogLevel::INFO], $levels[LogLevel::DEBUG]);
    }

    public function testLevelFiltering_warningAboveInfo(): void
    {
        $logger = $this->createLogger(LogLevel::INFO);

        $reflection = new \ReflectionClass($logger);
        $levelsProperty = $reflection->getProperty('levels');
        $levels = $levelsProperty->getValue($logger);

        $this->assertGreaterThan($levels[LogLevel::INFO], $levels[LogLevel::WARNING]);
    }

    public function testLevelFiltering_emergencyHighestPriority(): void
    {
        $logger = $this->createLogger(LogLevel::DEBUG);

        $reflection = new \ReflectionClass($logger);
        $levelsProperty = $reflection->getProperty('levels');
        $levels = $levelsProperty->getValue($logger);

        $this->assertEquals(7, $levels[LogLevel::EMERGENCY]);
    }
}
