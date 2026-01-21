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
 * This class tests parameter validation, especially the date_format CSV and HTML restriction.
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
     * Test that date_format can be used with HTML format.
     *
     * @return void
     */
    public function testParameters_dateFormat_withHtml_success(): void
    {
        // Test all DateFormat enum values with HTML
        $params1 = new Parameters(format: Format::HTML, date_format: DateFormat::TIMESTAMP);
        $this->assertEquals(Format::HTML, $params1->format);
        $this->assertEquals(DateFormat::TIMESTAMP, $params1->date_format);

        $params2 = new Parameters(format: Format::HTML, date_format: DateFormat::UNIX);
        $this->assertEquals(Format::HTML, $params2->format);
        $this->assertEquals(DateFormat::UNIX, $params2->date_format);

        $params3 = new Parameters(format: Format::HTML, date_format: DateFormat::SPREADSHEET);
        $this->assertEquals(Format::HTML, $params3->format);
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
        $this->expectExceptionMessage('date_format parameter can only be used with CSV or HTML format');

        new Parameters(format: Format::JSON, date_format: DateFormat::TIMESTAMP);
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
     * Test that null date_format with HTML is valid (backward compatibility).
     *
     * @return void
     */
    public function testParameters_dateFormat_null_withHtml_success(): void
    {
        $params = new Parameters(format: Format::HTML, date_format: null);
        $this->assertEquals(Format::HTML, $params->format);
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

    /**
     * Test that columns can be used with CSV format.
     *
     * @return void
     */
    public function testParameters_columns_withCsv_success(): void
    {
        $params1 = new Parameters(format: Format::CSV, columns: ['symbol']);
        $this->assertEquals(Format::CSV, $params1->format);
        $this->assertEquals(['symbol'], $params1->columns);

        $params2 = new Parameters(format: Format::CSV, columns: ['symbol', 'ask', 'bid']);
        $this->assertEquals(Format::CSV, $params2->format);
        $this->assertEquals(['symbol', 'ask', 'bid'], $params2->columns);
    }

    /**
     * Test that columns can be used with HTML format.
     *
     * @return void
     */
    public function testParameters_columns_withHtml_success(): void
    {
        $params1 = new Parameters(format: Format::HTML, columns: ['symbol']);
        $this->assertEquals(Format::HTML, $params1->format);
        $this->assertEquals(['symbol'], $params1->columns);

        $params2 = new Parameters(format: Format::HTML, columns: ['symbol', 'ask', 'bid']);
        $this->assertEquals(Format::HTML, $params2->format);
        $this->assertEquals(['symbol', 'ask', 'bid'], $params2->columns);
    }

    /**
     * Test that columns with JSON format throws InvalidArgumentException.
     *
     * @return void
     */
    public function testParameters_columns_withJson_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('columns parameter can only be used with CSV or HTML format');

        new Parameters(format: Format::JSON, columns: ['symbol']);
    }

    /**
     * Test that null columns with CSV is valid (backward compatibility).
     *
     * @return void
     */
    public function testParameters_columns_null_withCsv_success(): void
    {
        $params = new Parameters(format: Format::CSV, columns: null);
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertNull($params->columns);
    }

    /**
     * Test that null columns with HTML is valid (backward compatibility).
     *
     * @return void
     */
    public function testParameters_columns_null_withHtml_success(): void
    {
        $params = new Parameters(format: Format::HTML, columns: null);
        $this->assertEquals(Format::HTML, $params->format);
        $this->assertNull($params->columns);
    }

    /**
     * Test that null columns with JSON is valid (backward compatibility).
     *
     * @return void
     */
    public function testParameters_columns_null_withJson_success(): void
    {
        $params = new Parameters(format: Format::JSON, columns: null);
        $this->assertEquals(Format::JSON, $params->format);
        $this->assertNull($params->columns);
    }

    /**
     * Test that empty array columns with CSV is valid (should not be passed to API).
     *
     * @return void
     */
    public function testParameters_columns_emptyArray_withCsv_success(): void
    {
        $params = new Parameters(format: Format::CSV, columns: []);
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertEquals([], $params->columns);
    }

    /**
     * Test that columns with non-string array throws InvalidArgumentException.
     *
     * @return void
     */
    public function testParameters_columns_nonStringArray_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('columns parameter must contain only strings');

        new Parameters(format: Format::CSV, columns: ['symbol', 123]);
    }

    /**
     * Test that columns with mixed types throws InvalidArgumentException.
     *
     * @return void
     */
    public function testParameters_columns_mixedTypes_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('columns parameter must contain only strings');

        new Parameters(format: Format::CSV, columns: ['symbol', null]);
    }

    /**
     * Test that single column array works.
     *
     * @return void
     */
    public function testParameters_columns_singleColumn_success(): void
    {
        $params = new Parameters(format: Format::CSV, columns: ['symbol']);
        $this->assertEquals(['symbol'], $params->columns);
    }

    /**
     * Test that multiple columns array works.
     *
     * @return void
     */
    public function testParameters_columns_multipleColumns_success(): void
    {
        $params = new Parameters(format: Format::CSV, columns: ['symbol', 'ask', 'bid', 'last']);
        $this->assertEquals(['symbol', 'ask', 'bid', 'last'], $params->columns);
    }

    /**
     * Test that columns combined with other parameters works.
     *
     * @return void
     */
    public function testParameters_columns_withOtherParameters_success(): void
    {
        $params = new Parameters(
            format: Format::CSV,
            use_human_readable: true,
            mode: Mode::LIVE,
            date_format: DateFormat::UNIX,
            columns: ['symbol', 'ask', 'bid']
        );

        $this->assertEquals(Format::CSV, $params->format);
        $this->assertTrue($params->use_human_readable);
        $this->assertEquals(Mode::LIVE, $params->mode);
        $this->assertEquals(DateFormat::UNIX, $params->date_format);
        $this->assertEquals(['symbol', 'ask', 'bid'], $params->columns);
    }

    /**
     * Test that add_headers can be used with CSV format.
     *
     * @return void
     */
    public function testParameters_addHeaders_withCsv_success(): void
    {
        $params1 = new Parameters(format: Format::CSV, add_headers: true);
        $this->assertEquals(Format::CSV, $params1->format);
        $this->assertTrue($params1->add_headers);

        $params2 = new Parameters(format: Format::CSV, add_headers: false);
        $this->assertEquals(Format::CSV, $params2->format);
        $this->assertFalse($params2->add_headers);
    }

    /**
     * Test that add_headers can be used with HTML format.
     *
     * @return void
     */
    public function testParameters_addHeaders_withHtml_success(): void
    {
        $params1 = new Parameters(format: Format::HTML, add_headers: true);
        $this->assertEquals(Format::HTML, $params1->format);
        $this->assertTrue($params1->add_headers);

        $params2 = new Parameters(format: Format::HTML, add_headers: false);
        $this->assertEquals(Format::HTML, $params2->format);
        $this->assertFalse($params2->add_headers);
    }

    /**
     * Test that add_headers with JSON format throws InvalidArgumentException.
     *
     * @return void
     */
    public function testParameters_addHeaders_withJson_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('add_headers parameter can only be used with CSV or HTML format');

        new Parameters(format: Format::JSON, add_headers: true);
    }

    /**
     * Test that null add_headers with CSV is valid (backward compatibility).
     *
     * @return void
     */
    public function testParameters_addHeaders_null_withCsv_success(): void
    {
        $params = new Parameters(format: Format::CSV, add_headers: null);
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertNull($params->add_headers);
    }

    /**
     * Test that null add_headers with HTML is valid (backward compatibility).
     *
     * @return void
     */
    public function testParameters_addHeaders_null_withHtml_success(): void
    {
        $params = new Parameters(format: Format::HTML, add_headers: null);
        $this->assertEquals(Format::HTML, $params->format);
        $this->assertNull($params->add_headers);
    }

    /**
     * Test that null add_headers with JSON is valid (backward compatibility).
     *
     * @return void
     */
    public function testParameters_addHeaders_null_withJson_success(): void
    {
        $params = new Parameters(format: Format::JSON, add_headers: null);
        $this->assertEquals(Format::JSON, $params->format);
        $this->assertNull($params->add_headers);
    }

    /**
     * Test that add_headers combined with other parameters works.
     *
     * @return void
     */
    public function testParameters_addHeaders_withOtherParameters_success(): void
    {
        $params = new Parameters(
            format: Format::CSV,
            use_human_readable: true,
            mode: Mode::LIVE,
            date_format: DateFormat::UNIX,
            columns: ['symbol', 'ask', 'bid'],
            add_headers: true
        );

        $this->assertEquals(Format::CSV, $params->format);
        $this->assertTrue($params->use_human_readable);
        $this->assertEquals(Mode::LIVE, $params->mode);
        $this->assertEquals(DateFormat::UNIX, $params->date_format);
        $this->assertEquals(['symbol', 'ask', 'bid'], $params->columns);
        $this->assertTrue($params->add_headers);
    }
}
