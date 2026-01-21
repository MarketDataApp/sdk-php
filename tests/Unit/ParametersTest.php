<?php

namespace MarketDataApp\Tests\Unit;

use InvalidArgumentException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the Parameters class.
 *
 * This class tests parameter validation, especially the date_format CSV-only restriction.
 */
class ParametersTest extends TestCase
{

    /**
     * Test that date_format can be used with CSV format.
     *
     * @return void
     */
    public function testParameters_dateFormat_withCsv_success(): void
    {
        // Test all DateFormat enum values with CSV
        $params1 = new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP);
        $this->assertEquals(Format::CSV, $params1->format);
        $this->assertEquals(DateFormat::TIMESTAMP, $params1->date_format);

        $params2 = new Parameters(format: Format::CSV, date_format: DateFormat::UNIX);
        $this->assertEquals(Format::CSV, $params2->format);
        $this->assertEquals(DateFormat::UNIX, $params2->date_format);

        $params3 = new Parameters(format: Format::CSV, date_format: DateFormat::SPREADSHEET);
        $this->assertEquals(Format::CSV, $params3->format);
        $this->assertEquals(DateFormat::SPREADSHEET, $params3->date_format);
    }

    /**
     * Test that date_format with JSON format throws InvalidArgumentException.
     *
     * @return void
     */
    public function testParameters_dateFormat_withJson_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('date_format parameter can only be used with CSV format');

        new Parameters(format: Format::JSON, date_format: DateFormat::TIMESTAMP);
    }

    /**
     * Test that date_format with HTML format throws InvalidArgumentException.
     *
     * @return void
     */
    public function testParameters_dateFormat_withHtml_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('date_format parameter can only be used with CSV format');

        new Parameters(format: Format::HTML, date_format: DateFormat::TIMESTAMP);
    }

    /**
     * Test that null date_format with CSV is valid (backward compatibility).
     *
     * @return void
     */
    public function testParameters_dateFormat_null_withCsv_success(): void
    {
        $params = new Parameters(format: Format::CSV, date_format: null);
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertNull($params->date_format);
    }

    /**
     * Test that null date_format with JSON is valid (backward compatibility).
     *
     * @return void
     */
    public function testParameters_dateFormat_null_withJson_success(): void
    {
        $params = new Parameters(format: Format::JSON, date_format: null);
        $this->assertEquals(Format::JSON, $params->format);
        $this->assertNull($params->date_format);
    }

    /**
     * Test that default Parameters (no date_format) works (backward compatibility).
     *
     * @return void
     */
    public function testParameters_default_backwardCompatible(): void
    {
        $params = new Parameters();
        $this->assertEquals(Format::JSON, $params->format);
        $this->assertNull($params->date_format);
        $this->assertNull($params->use_human_readable);
        $this->assertNull($params->mode);
    }

    /**
     * Test that all DateFormat enum values are accessible.
     *
     * @return void
     */
    public function testDateFormat_enumValues(): void
    {
        $this->assertEquals('timestamp', DateFormat::TIMESTAMP->value);
        $this->assertEquals('unix', DateFormat::UNIX->value);
        $this->assertEquals('spreadsheet', DateFormat::SPREADSHEET->value);
    }

    /**
     * Test that Parameters with all optional parameters works.
     *
     * @return void
     */
    public function testParameters_allParameters_withCsv(): void
    {
        $params = new Parameters(
            format: Format::CSV,
            use_human_readable: true,
            mode: Mode::LIVE,
            date_format: DateFormat::UNIX
        );

        $this->assertEquals(Format::CSV, $params->format);
        $this->assertTrue($params->use_human_readable);
        $this->assertEquals(Mode::LIVE, $params->mode);
        $this->assertEquals(DateFormat::UNIX, $params->date_format);
    }
}
