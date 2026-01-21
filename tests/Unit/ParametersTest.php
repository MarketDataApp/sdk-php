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

    /**
     * Test that filename can be used with CSV format.
     *
     * @return void
     */
    public function testParameters_filename_withCsv_success(): void
    {
        // Create a temporary directory for testing
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_' . uniqid() . '.csv';

        $params = new Parameters(format: Format::CSV, filename: $testFile);
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertEquals($testFile, $params->filename);
    }

    /**
     * Test that filename can be used with HTML format.
     *
     * @return void
     */
    public function testParameters_filename_withHtml_success(): void
    {
        // Create a temporary directory for testing
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_' . uniqid() . '.html';

        $params = new Parameters(format: Format::HTML, filename: $testFile);
        $this->assertEquals(Format::HTML, $params->format);
        $this->assertEquals($testFile, $params->filename);
    }

    /**
     * Test that filename with JSON format throws InvalidArgumentException.
     *
     * @return void
     */
    public function testParameters_filename_withJson_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filename parameter can only be used with CSV or HTML format');

        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_' . uniqid() . '.csv';

        new Parameters(format: Format::JSON, filename: $testFile);
    }

    /**
     * Test that filename with invalid extension throws InvalidArgumentException.
     *
     * @return void
     */
    public function testParameters_filename_invalidExtension_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filename must end with .csv');

        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_' . uniqid() . '.txt';

        new Parameters(format: Format::CSV, filename: $testFile);
    }

    /**
     * Test that filename with HTML format requires .html extension.
     *
     * @return void
     */
    public function testParameters_filename_htmlFormatRequiresHtmlExtension_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filename must end with .html');

        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_' . uniqid() . '.csv';

        new Parameters(format: Format::HTML, filename: $testFile);
    }

    /**
     * Test that filename with non-existent directory throws InvalidArgumentException.
     * Note: The validation walks up the directory tree to find any existing parent.
     * For absolute paths, root (/) always exists, so they're allowed.
     * For relative paths, if current directory exists, single-level subdirectories are allowed.
     * This test verifies that a relative path with no existing parent in the chain fails.
     *
     * @return void
     */
    public function testParameters_filename_nonExistentDirectory_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No existing parent directory found');

        // Use a relative path where we change to a temp directory first
        // Then use a path that doesn't have an existing parent in the relative chain
        $tempDir = sys_get_temp_dir() . '/test_' . uniqid();
        mkdir($tempDir, 0755, true);
        $originalCwd = getcwd();
        chdir($tempDir);

        try {
            // This path has no existing parent in the relative chain
            // (the directory itself doesn't exist, and we're testing the validation)
            $nonExistentDir = 'nonexistent_' . uniqid();
            $testFile = $nonExistentDir . '/subdir/test.csv';
            
            new Parameters(format: Format::CSV, filename: $testFile);
        } finally {
            chdir($originalCwd);
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        }
    }

    /**
     * Test that filename with existing file throws InvalidArgumentException.
     *
     * @return void
     */
    public function testParameters_filename_existingFile_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File already exists');

        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_' . uniqid() . '.csv';
        
        // Create the file first
        file_put_contents($testFile, 'test content');
        
        try {
            new Parameters(format: Format::CSV, filename: $testFile);
        } finally {
            // Clean up
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    /**
     * Test that filename with relative path works.
     *
     * @return void
     */
    public function testParameters_filename_relativePath_success(): void
    {
        // Create a temporary directory and change to it
        $tempDir = sys_get_temp_dir() . '/test_' . uniqid();
        mkdir($tempDir, 0755, true);
        $originalCwd = getcwd();
        chdir($tempDir);

        try {
            $testFile = 'test.csv';
            $params = new Parameters(format: Format::CSV, filename: $testFile);
            $this->assertEquals($testFile, $params->filename);
        } finally {
            chdir($originalCwd);
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        }
    }

    /**
     * Test that filename with absolute path works.
     *
     * @return void
     */
    public function testParameters_filename_absolutePath_success(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_' . uniqid() . '.csv';

        $params = new Parameters(format: Format::CSV, filename: $testFile);
        $this->assertEquals($testFile, $params->filename);
    }

    /**
     * Test that filename with nested directory path works.
     *
     * @return void
     */
    public function testParameters_filename_nestedDirectory_success(): void
    {
        $tempDir = sys_get_temp_dir();
        $nestedDir = $tempDir . '/nested_' . uniqid();
        mkdir($nestedDir, 0755, true);
        $testFile = $nestedDir . '/test.csv';

        try {
            $params = new Parameters(format: Format::CSV, filename: $testFile);
            $this->assertEquals($testFile, $params->filename);
        } finally {
            if (is_dir($nestedDir)) {
                rmdir($nestedDir);
            }
        }
    }

    /**
     * Test that null filename with CSV is valid (backward compatibility).
     *
     * @return void
     */
    public function testParameters_filename_null_withCsv_success(): void
    {
        $params = new Parameters(format: Format::CSV, filename: null);
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertNull($params->filename);
    }

    /**
     * Test that null filename with HTML is valid (backward compatibility).
     *
     * @return void
     */
    public function testParameters_filename_null_withHtml_success(): void
    {
        $params = new Parameters(format: Format::HTML, filename: null);
        $this->assertEquals(Format::HTML, $params->format);
        $this->assertNull($params->filename);
    }

    /**
     * Test that null filename with JSON is valid (backward compatibility).
     *
     * @return void
     */
    public function testParameters_filename_null_withJson_success(): void
    {
        $params = new Parameters(format: Format::JSON, filename: null);
        $this->assertEquals(Format::JSON, $params->format);
        $this->assertNull($params->filename);
    }

    /**
     * Test that filename combined with other parameters works.
     *
     * @return void
     */
    public function testParameters_filename_withOtherParameters_success(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_' . uniqid() . '.csv';

        $params = new Parameters(
            format: Format::CSV,
            use_human_readable: true,
            mode: Mode::LIVE,
            date_format: DateFormat::UNIX,
            columns: ['symbol', 'ask', 'bid'],
            add_headers: true,
            filename: $testFile
        );

        $this->assertEquals(Format::CSV, $params->format);
        $this->assertTrue($params->use_human_readable);
        $this->assertEquals(Mode::LIVE, $params->mode);
        $this->assertEquals(DateFormat::UNIX, $params->date_format);
        $this->assertEquals(['symbol', 'ask', 'bid'], $params->columns);
        $this->assertTrue($params->add_headers);
        $this->assertEquals($testFile, $params->filename);
    }

    /**
     * Test that execute_in_parallel with filename parameter throws exception.
     * Note: This test is moved to integration tests since execute_in_parallel is protected
     * and can only be tested through actual endpoint methods like quotes().
     */
}
