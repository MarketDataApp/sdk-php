<?php

namespace MarketDataApp\Tests\Unit\Logging;

use MarketDataApp\Logging\LoggingUtilities;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LoggingUtilities class.
 */
class LoggingUtilitiesTest extends TestCase
{
    /**
     * Test millisecond formatting for small values.
     */
    public function testFormatDuration_zeroMs_returnsSpacePaddedMs(): void
    {
        $result = LoggingUtilities::formatDuration(0);

        $this->assertEquals('  0ms', $result);
        $this->assertEquals(5, strlen($result));
    }

    public function testFormatDuration_smallMs_returnsSpacePaddedMs(): void
    {
        $result = LoggingUtilities::formatDuration(45);

        $this->assertEquals(' 45ms', $result);
        $this->assertEquals(5, strlen($result));
    }

    public function testFormatDuration_mediumMs_returnsMs(): void
    {
        $result = LoggingUtilities::formatDuration(333);

        $this->assertEquals('333ms', $result);
        $this->assertEquals(5, strlen($result));
    }

    public function testFormatDuration_nearThreshold_returnsMs(): void
    {
        $result = LoggingUtilities::formatDuration(999);

        $this->assertEquals('999ms', $result);
        $this->assertEquals(5, strlen($result));
    }

    /**
     * Test second formatting for values 1-9.99 seconds.
     */
    public function testFormatDuration_oneSecond_returnsDecimalSeconds(): void
    {
        $result = LoggingUtilities::formatDuration(1000);

        $this->assertEquals('1.00s', $result);
        $this->assertEquals(5, strlen($result));
    }

    public function testFormatDuration_1_23Seconds_returnsDecimalSeconds(): void
    {
        $result = LoggingUtilities::formatDuration(1230);

        $this->assertEquals('1.23s', $result);
        $this->assertEquals(5, strlen($result));
    }

    public function testFormatDuration_9_87Seconds_returnsDecimalSeconds(): void
    {
        $result = LoggingUtilities::formatDuration(9870);

        $this->assertEquals('9.87s', $result);
        $this->assertEquals(5, strlen($result));
    }

    /**
     * Test second formatting for values 10-99.9 seconds.
     */
    public function testFormatDuration_10Seconds_returnsOneDecimalSeconds(): void
    {
        $result = LoggingUtilities::formatDuration(10000);

        $this->assertEquals('10.0s', $result);
        $this->assertEquals(5, strlen($result));
    }

    public function testFormatDuration_12_3Seconds_returnsOneDecimalSeconds(): void
    {
        $result = LoggingUtilities::formatDuration(12300);

        $this->assertEquals('12.3s', $result);
        $this->assertEquals(5, strlen($result));
    }

    public function testFormatDuration_99_9Seconds_returnsOneDecimalSeconds(): void
    {
        $result = LoggingUtilities::formatDuration(99900);

        $this->assertEquals('99.9s', $result);
        $this->assertEquals(5, strlen($result));
    }

    /**
     * Test second formatting for values 100-999 seconds.
     */
    public function testFormatDuration_100Seconds_returnsSpacePaddedSeconds(): void
    {
        $result = LoggingUtilities::formatDuration(100000);

        $this->assertEquals(' 100s', $result);
        $this->assertEquals(5, strlen($result));
    }

    public function testFormatDuration_500Seconds_returnsSpacePaddedSeconds(): void
    {
        $result = LoggingUtilities::formatDuration(500000);

        $this->assertEquals(' 500s', $result);
        $this->assertEquals(5, strlen($result));
    }

    public function testFormatDuration_999Seconds_returnsSpacePaddedSeconds(): void
    {
        $result = LoggingUtilities::formatDuration(999000);

        $this->assertEquals(' 999s', $result);
        $this->assertEquals(5, strlen($result));
    }

    /**
     * Test second formatting for values 1000-9999 seconds.
     */
    public function testFormatDuration_1000Seconds_returnsFourDigitSeconds(): void
    {
        $result = LoggingUtilities::formatDuration(1000000);

        $this->assertEquals('1000s', $result);
        $this->assertEquals(5, strlen($result));
    }

    public function testFormatDuration_5000Seconds_returnsFourDigitSeconds(): void
    {
        $result = LoggingUtilities::formatDuration(5000000);

        $this->assertEquals('5000s', $result);
        $this->assertEquals(5, strlen($result));
    }

    public function testFormatDuration_9999Seconds_returnsFourDigitSeconds(): void
    {
        $result = LoggingUtilities::formatDuration(9999000);

        $this->assertEquals('9999s', $result);
        $this->assertEquals(5, strlen($result));
    }

    /**
     * Test clamping for values >= 10000 seconds.
     */
    public function testFormatDuration_10000Seconds_clampedTo9999(): void
    {
        $result = LoggingUtilities::formatDuration(10000000);

        $this->assertEquals('9999s', $result);
        $this->assertEquals(5, strlen($result));
    }

    public function testFormatDuration_100000Seconds_clampedTo9999(): void
    {
        $result = LoggingUtilities::formatDuration(100000000);

        $this->assertEquals('9999s', $result);
        $this->assertEquals(5, strlen($result));
    }

    /**
     * Test edge cases.
     */
    public function testFormatDuration_fractionalMs_truncatesToInteger(): void
    {
        $result = LoggingUtilities::formatDuration(45.6);

        $this->assertEquals(' 45ms', $result);
    }

    public function testFormatDuration_justBelowOneSecond_returnsMs(): void
    {
        $result = LoggingUtilities::formatDuration(999.9);

        $this->assertEquals('999ms', $result);
    }

    public function testFormatDuration_justAboveOneSecond_returnsSeconds(): void
    {
        $result = LoggingUtilities::formatDuration(1001);

        // 1001ms = 1.001s
        $this->assertStringEndsWith('s', $result);
        $this->assertEquals(5, strlen($result));
    }

    /**
     * Verify all outputs are exactly 5 characters for alignment.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('durationProvider')]
    public function testFormatDuration_allValues_returnExactly5Characters(float $duration): void
    {
        $result = LoggingUtilities::formatDuration($duration);

        $this->assertEquals(5, strlen($result), "Duration {$duration}ms formatted as '{$result}' is not 5 characters");
    }

    public static function durationProvider(): array
    {
        return [
            'zero' => [0],
            '1ms' => [1],
            '10ms' => [10],
            '100ms' => [100],
            '500ms' => [500],
            '999ms' => [999],
            '1s' => [1000],
            '1.5s' => [1500],
            '5s' => [5000],
            '9.99s' => [9990],
            '10s' => [10000],
            '50s' => [50000],
            '99.9s' => [99900],
            '100s' => [100000],
            '500s' => [500000],
            '999s' => [999000],
            '1000s' => [1000000],
            '5000s' => [5000000],
            '9999s' => [9999000],
            '10000s (clamped)' => [10000000],
        ];
    }
}
