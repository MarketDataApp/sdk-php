<?php

namespace MarketDataApp\Tests\Unit;

use InvalidArgumentException;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the ValidatesInputs trait.
 * 
 * Uses a test class that uses the trait to test validation methods.
 */
class ValidatesInputsTest extends TestCase
{
    use MockResponses;

    /**
     * Test class instance that uses the ValidatesInputs trait.
     */
    private object $testClass;

    protected function setUp(): void
    {
        $this->testClass = new class {
            use \MarketDataApp\Traits\ValidatesInputs;
        };
    }

    /**
     * Test canParseAsDate with ISO 8601 format.
     */
    public function testCanParseAsDate_iso8601_returnsTrue(): void
    {
        $this->assertTrue($this->invokeMethod('canParseAsDate', ['2024-01-01']));
        $this->assertTrue($this->invokeMethod('canParseAsDate', ['2024-01-01 16:00:00']));
    }

    /**
     * Test canParseAsDate with American format.
     */
    public function testCanParseAsDate_americanFormat_returnsTrue(): void
    {
        $this->assertTrue($this->invokeMethod('canParseAsDate', ['12/30/2020']));
        $this->assertTrue($this->invokeMethod('canParseAsDate', ['12/30/2020 4:00 PM']));
    }

    /**
     * Test canParseAsDate with numeric (unix/spreadsheet).
     */
    public function testCanParseAsDate_numeric_returnsTrue(): void
    {
        $this->assertTrue($this->invokeMethod('canParseAsDate', ['1704067200']));
        $this->assertTrue($this->invokeMethod('canParseAsDate', ['45292.66667']));
    }

    /**
     * Test canParseAsDate with relative dates.
     * Note: Some relative dates like "-5 days" contain "-" so they return true,
     * but that's okay - the date range validation will handle them correctly.
     */
    public function testCanParseAsDate_relativeDates_mixedResults(): void
    {
        // Relative dates without "-" or "/" return false
        $this->assertFalse($this->invokeMethod('canParseAsDate', ['today']));
        $this->assertFalse($this->invokeMethod('canParseAsDate', ['yesterday']));
        $this->assertFalse($this->invokeMethod('canParseAsDate', ['2 weeks ago']));
        $this->assertFalse($this->invokeMethod('canParseAsDate', ['last session']));
        
        // Relative dates with "-" return true (they can be parsed by strtotime)
        // This is expected behavior - strtotime can handle "-5 days"
        $this->assertTrue($this->invokeMethod('canParseAsDate', ['-5 days']));
    }

    /**
     * Test canParseAsDate with option expiration dates (should return false - not parseable).
     */
    public function testCanParseAsDate_optionExpirationDates_returnsFalse(): void
    {
        $this->assertFalse($this->invokeMethod('canParseAsDate', ['December expiration']));
        $this->assertFalse($this->invokeMethod('canParseAsDate', ["this month's expiration"]));
    }

    /**
     * Test canParseAsDate with null.
     */
    public function testCanParseAsDate_null_returnsFalse(): void
    {
        $this->assertFalse($this->invokeMethod('canParseAsDate', [null]));
    }

    /**
     * Test parseDateToTimestamp with null input.
     */
    public function testParseDateToTimestamp_null_returnsNull(): void
    {
        $result = $this->invokeMethod('parseDateToTimestamp', [null]);
        $this->assertNull($result);
    }

    /**
     * Test parseDateToTimestamp with spreadsheet format dates (< 100000).
     */
    public function testParseDateToTimestamp_spreadsheetFormat_returnsTimestamp(): void
    {
        // Test with spreadsheet date 45292 (approximately 2024-01-01)
        $result = $this->invokeMethod('parseDateToTimestamp', ['45292']);
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
        
        // Test with decimal spreadsheet date
        $result2 = $this->invokeMethod('parseDateToTimestamp', ['45292.5']);
        $this->assertIsInt($result2);
        $this->assertGreaterThan(0, $result2);
    }

    /**
     * Test parseDateToTimestamp with unix timestamp format (>= 100000).
     * Uses timestamps that strtotime() cannot parse, so they go through the numeric path.
     */
    public function testParseDateToTimestamp_unixTimestamp_returnsTimestamp(): void
    {
        // Test with unix timestamp that strtotime() cannot parse
        $result = $this->invokeMethod('parseDateToTimestamp', ['1704067200']);
        $this->assertEquals(1704067200, $result);

        // Test with another unix timestamp that strtotime() cannot parse (>= 100000)
        $result2 = $this->invokeMethod('parseDateToTimestamp', ['1000000000']);
        $this->assertEquals(1000000000, $result2);
    }

    /**
     * Test parseDateToTimestamp handles timestamps that strtotime() would misinterpret.
     * Bug 009: strtotime("1234567890") was incorrectly parsed before checking is_numeric().
     */
    public function testParseDateToTimestamp_ambiguousTimestamp_returnsCorrectValue(): void
    {
        // 1234567890 is Fri Feb 13 2009 23:31:30 UTC
        // strtotime("1234567890") could misinterpret this as a date format
        $result = $this->invokeMethod('parseDateToTimestamp', ['1234567890']);
        $this->assertEquals(1234567890, $result);
    }

    /**
     * Test validateDateRange with valid Unix timestamp range.
     * Bug 009: from=1234567890 (2009) and to=1700000000 (2023) was incorrectly rejected.
     */
    public function testValidateDateRange_unixTimestampRange_noException(): void
    {
        $this->expectNotToPerformAssertions();
        // Unix timestamps: 2009-02-13 (1234567890) to 2023-11-14 (1700000000)
        $this->invokeMethod('validateDateRange', ['1234567890', '1700000000', null]);
    }

    /**
     * Test parseDateToTimestamp with unparseable non-numeric values.
     */
    public function testParseDateToTimestamp_unparseable_returnsNull(): void
    {
        // Values that fail strtotime() and are not numeric
        $result = $this->invokeMethod('parseDateToTimestamp', ['invalid date']);
        $this->assertNull($result);
        
        $result2 = $this->invokeMethod('parseDateToTimestamp', ['not a date']);
        $this->assertNull($result2);
    }

    /**
     * Test validateDateRange with valid range.
     */
    public function testValidateDateRange_validRange_noException(): void
    {
        $this->expectNotToPerformAssertions();
        $this->invokeMethod('validateDateRange', ['2024-01-01', '2024-01-31', null]);
    }

    /**
     * Test validateDateRange with invalid range (from > to).
     */
    public function testValidateDateRange_invalidRange_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`from` date must be before `to` date');
        $this->invokeMethod('validateDateRange', ['2024-01-31', '2024-01-01', null]);
    }

    /**
     * Test validateDateRange with relative dates (should not validate range).
     */
    public function testValidateDateRange_relativeDates_noException(): void
    {
        $this->expectNotToPerformAssertions();
        // Relative dates should pass through without validation
        $this->invokeMethod('validateDateRange', ['today', 'yesterday', null]);
        $this->invokeMethod('validateDateRange', ['-5 days', '2 weeks ago', null]);
    }

    /**
     * Test validateDateRange with option expiration dates (should not validate range).
     */
    public function testValidateDateRange_optionExpirationDates_noException(): void
    {
        $this->expectNotToPerformAssertions();
        // Option expiration dates should pass through without validation
        $this->invokeMethod('validateDateRange', ['December expiration', 'January expiration', null]);
    }

    /**
     * Test validateDateRange with mixed parseable and relative dates.
     */
    public function testValidateDateRange_mixedDates_noException(): void
    {
        $this->expectNotToPerformAssertions();
        // When one is parseable and one is relative, should not validate range
        $this->invokeMethod('validateDateRange', ['2024-01-01', 'today', null]);
        $this->invokeMethod('validateDateRange', ['yesterday', '2024-01-31', null]);
    }

    /**
     * Test validateDateRange with countback validation.
     */
    public function testValidateDateRange_countbackPositive_noException(): void
    {
        $this->expectNotToPerformAssertions();
        $this->invokeMethod('validateDateRange', [null, null, 10]);
    }

    /**
     * Test validateDateRange with invalid countback (zero).
     */
    public function testValidateDateRange_countbackZero_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`countback` must be a positive integer');
        $this->invokeMethod('validateDateRange', [null, null, 0]);
    }

    /**
     * Test validateDateRange with invalid countback (negative).
     */
    public function testValidateDateRange_countbackNegative_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`countback` must be a positive integer');
        $this->invokeMethod('validateDateRange', [null, null, -5]);
    }

    /**
     * Test validatePositiveInteger with valid value.
     */
    public function testValidatePositiveInteger_valid_noException(): void
    {
        $this->expectNotToPerformAssertions();
        $this->invokeMethod('validatePositiveInteger', [10, 'testField']);
    }

    /**
     * Test validatePositiveInteger with null.
     */
    public function testValidatePositiveInteger_null_noException(): void
    {
        $this->expectNotToPerformAssertions();
        $this->invokeMethod('validatePositiveInteger', [null, 'testField']);
    }

    /**
     * Test validatePositiveInteger with zero.
     */
    public function testValidatePositiveInteger_zero_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a positive integer');
        $this->invokeMethod('validatePositiveInteger', [0, 'testField']);
    }

    /**
     * Test validatePositiveInteger with negative.
     */
    public function testValidatePositiveInteger_negative_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a positive integer');
        $this->invokeMethod('validatePositiveInteger', [-5, 'testField']);
    }

    /**
     * Test validateNumericRange with valid range.
     */
    public function testValidateNumericRange_validRange_noException(): void
    {
        $this->expectNotToPerformAssertions();
        $this->invokeMethod('validateNumericRange', [10.0, 20.0, 'minField', 'maxField']);
    }

    /**
     * Test validateNumericRange with invalid range (min >= max).
     */
    public function testValidateNumericRange_invalidRange_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be less than');
        $this->invokeMethod('validateNumericRange', [20.0, 10.0, 'minField', 'maxField']);
    }

    /**
     * Test validateNumericRange with equal values.
     */
    public function testValidateNumericRange_equalValues_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be less than');
        $this->invokeMethod('validateNumericRange', [10.0, 10.0, 'minField', 'maxField']);
    }

    /**
     * Test validateNumericRange with null values.
     */
    public function testValidateNumericRange_nullValues_noException(): void
    {
        $this->expectNotToPerformAssertions();
        $this->invokeMethod('validateNumericRange', [null, null, 'minField', 'maxField']);
        $this->invokeMethod('validateNumericRange', [10.0, null, 'minField', 'maxField']);
        $this->invokeMethod('validateNumericRange', [null, 20.0, 'minField', 'maxField']);
    }

    /**
     * Test validateNonEmptyString with valid string.
     */
    public function testValidateNonEmptyString_valid_noException(): void
    {
        $this->expectNotToPerformAssertions();
        $this->invokeMethod('validateNonEmptyString', ['test', 'testField']);
    }

    /**
     * Test validateNonEmptyString with empty string.
     */
    public function testValidateNonEmptyString_empty_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-empty string');
        $this->invokeMethod('validateNonEmptyString', ['', 'testField']);
    }

    /**
     * Test validateNonEmptyString with whitespace only.
     */
    public function testValidateNonEmptyString_whitespace_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-empty string');
        $this->invokeMethod('validateNonEmptyString', ['   ', 'testField']);
    }

    /**
     * Test validateNonEmptyArray with valid array.
     */
    public function testValidateNonEmptyArray_valid_noException(): void
    {
        $this->expectNotToPerformAssertions();
        $this->invokeMethod('validateNonEmptyArray', [['test'], 'testField']);
    }

    /**
     * Test validateNonEmptyArray with empty array.
     */
    public function testValidateNonEmptyArray_empty_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-empty array');
        $this->invokeMethod('validateNonEmptyArray', [[], 'testField']);
    }

    /**
     * Test validateSymbols with valid symbols.
     */
    public function testValidateSymbols_valid_noException(): void
    {
        $this->expectNotToPerformAssertions();
        $this->invokeMethod('validateSymbols', [['AAPL', 'MSFT']]);
    }

    /**
     * Test validateSymbols with empty array.
     */
    public function testValidateSymbols_empty_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-empty array');
        $this->invokeMethod('validateSymbols', [[]]);
    }

    /**
     * Test validateSymbols with empty string in array.
     */
    public function testValidateSymbols_emptyString_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be non-empty strings');
        $this->invokeMethod('validateSymbols', [['AAPL', '']]);
    }

    /**
     * Test validateSymbols with non-string in array.
     */
    public function testValidateSymbols_nonString_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be non-empty strings');
        $this->invokeMethod('validateSymbols', [['AAPL', 123]]);
    }

    /**
     * Test validateResolution with valid resolutions.
     */
    public function testValidateResolution_valid_noException(): void
    {
        $this->expectNotToPerformAssertions();
        $this->invokeMethod('validateResolution', ['D']);
        $this->invokeMethod('validateResolution', ['1D']);
        $this->invokeMethod('validateResolution', ['daily']);
        $this->invokeMethod('validateResolution', ['1']);
        $this->invokeMethod('validateResolution', ['15']);
        $this->invokeMethod('validateResolution', ['H']);
        $this->invokeMethod('validateResolution', ['1H']);
        $this->invokeMethod('validateResolution', ['minutely']);
        $this->invokeMethod('validateResolution', ['hourly']);
    }

    /**
     * Test validateResolution with invalid resolution.
     */
    public function testValidateResolution_invalid_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid resolution format');
        $this->invokeMethod('validateResolution', ['invalid']);
    }

    /**
     * Test validateResolution with empty string.
     */
    public function testValidateResolution_empty_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-empty string');
        $this->invokeMethod('validateResolution', ['']);
    }

    /**
     * Test validateCountryCode with valid codes.
     */
    public function testValidateCountryCode_valid_noException(): void
    {
        $this->expectNotToPerformAssertions();
        $this->invokeMethod('validateCountryCode', ['US']);
        $this->invokeMethod('validateCountryCode', ['GB']);
        $this->invokeMethod('validateCountryCode', ['CA']);
    }

    /**
     * Test validateCountryCode with lowercase (invalid).
     */
    public function testValidateCountryCode_lowercase_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid country code');
        $this->invokeMethod('validateCountryCode', ['us']);
    }

    /**
     * Test validateCountryCode with wrong length.
     */
    public function testValidateCountryCode_wrongLength_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid country code');
        $this->invokeMethod('validateCountryCode', ['USA']);
    }

    /**
     * Test validateCountryCode with empty string.
     */
    public function testValidateCountryCode_empty_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-empty string');
        $this->invokeMethod('validateCountryCode', ['']);
    }

    /**
     * Helper method to invoke protected methods for testing.
     */
    private function invokeMethod(string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass($this->testClass);
        $method = $reflection->getMethod($methodName);
        return $method->invokeArgs($this->testClass, $parameters);
    }
}
