<?php

namespace MarketDataApp\Tests\Integration\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Exceptions\UnauthorizedException;

/**
 * Integration tests for the Stocks Quote endpoint (single symbol).
 */
class QuoteTest extends StocksTestCase
{
    /**
     * Test successful retrieval of a stock quote.
     */
    public function testQuote_success()
    {
        $response = $this->client->stocks->quote('AAPL');

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('string', gettype($response->status));
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('integer', gettype($response->ask_size));
        $this->assertEquals('double', gettype($response->bid));
        $this->assertEquals('integer', gettype($response->bid_size));
        $this->assertEquals('double', gettype($response->mid));
        $this->assertEquals('double', gettype($response->last));
        $this->assertTrue(in_array(gettype($response->change), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->change_percent), ['double', 'NULL']));
        $this->assertNull($response->fifty_two_week_high);
        $this->assertNull($response->fifty_two_week_low);
        $this->assertEquals('integer', gettype($response->volume));
        $this->assertInstanceOf(Carbon::class, $response->updated);
    }

    /**
     * Test successful retrieval of a stock quote in CSV format.
     */
    public function testQuote_csv_success()
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test quote endpoint with CSV format and columns parameter (single column).
     * Verifies that the CSV response contains only the requested column.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_columns_singleColumn_returnsFilteredCsv(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(
                format: Format::CSV,
                columns: ['symbol']
            )
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        $lines = explode("\n", trim($csv));
        $headerRow = str_getcsv($lines[0], ',', '"', '\\');

        // Verify only requested columns are present
        $this->assertEquals(['symbol'], $headerRow);

        // Verify data row exists and has correct number of columns
        if (count($lines) > 1) {
            $dataRow = str_getcsv($lines[1], ',', '"', '\\');
            $this->assertCount(1, $dataRow);
            $this->assertEquals('AAPL', $dataRow[0]);
        }
    }

    /**
     * Test quote endpoint with CSV format and columns parameter (multiple columns).
     * Verifies that the CSV response contains only the requested columns in the correct order.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_columns_multipleColumns_returnsFilteredCsv(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(
                format: Format::CSV,
                columns: ['symbol', 'ask', 'bid', 'last']
            )
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        $lines = explode("\n", trim($csv));
        $headerRow = str_getcsv($lines[0], ',', '"', '\\');

        // Verify only requested columns are present in the correct order
        $this->assertEquals(['symbol', 'ask', 'bid', 'last'], $headerRow);

        // Verify data row exists and has correct number of columns
        if (count($lines) > 1) {
            $dataRow = str_getcsv($lines[1], ',', '"', '\\');
            $this->assertCount(4, $dataRow);
            $this->assertEquals('AAPL', $dataRow[0]);
        }
    }

    /**
     * Test quote endpoint with CSV format and columns parameter verifies column order.
     * Verifies that columns appear in the order specified in the request.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_columns_verifiesColumnOrder(): void
    {
        // Test with different column order
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(
                format: Format::CSV,
                columns: ['bid', 'ask', 'symbol']
            )
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        $lines = explode("\n", trim($csv));
        $headerRow = str_getcsv($lines[0], ',', '"', '\\');

        // Verify columns appear in the exact order specified
        $this->assertEquals(['bid', 'ask', 'symbol'], $headerRow);
    }

    /**
     * Test SPY quote with no token throws UnauthorizedException.
     *
     * @return void
     */
    public function testQuote_noToken_throwsUnauthorizedException()
    {
        $client = new Client('');

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionCode(401);

        try {
            $client->stocks->quote('SPY');
        } catch (UnauthorizedException $e) {
            $this->assertEquals(401, $e->getCode());
            $this->assertNotNull($e->getResponse());
            $this->assertEquals(401, $e->getResponse()->getStatusCode());
            throw $e;
        }
    }

    /**
     * Test stocks quote with human-readable format.
     * Verifies that the API returns human-readable JSON keys with spaces.
     */
    public function testQuote_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('integer', gettype($response->ask_size));
        $this->assertEquals('double', gettype($response->bid));
        $this->assertEquals('integer', gettype($response->bid_size));
        $this->assertEquals('double', gettype($response->mid));
        $this->assertEquals('double', gettype($response->last));
        $this->assertTrue(in_array(gettype($response->change), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->change_percent), ['double', 'NULL']));
        $this->assertEquals('integer', gettype($response->volume));
        $this->assertInstanceOf(Carbon::class, $response->updated);
    }

    /**
     * Test stocks quote with human_readable=false.
     * Verifies that the API returns regular JSON keys.
     */
    public function testQuote_humanReadableFalse_returnsRegularKeys()
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(use_human_readable: false)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('integer', gettype($response->ask_size));
    }

    /**
     * Test stocks quote with mode=LIVE.
     * Verifies that the API accepts and processes the mode parameter with LIVE value.
     */
    public function testQuote_modeLive_success()
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(mode: Mode::LIVE)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('integer', gettype($response->ask_size));
        $this->assertEquals('double', gettype($response->bid));
        $this->assertEquals('integer', gettype($response->bid_size));
        $this->assertEquals('double', gettype($response->mid));
        $this->assertEquals('double', gettype($response->last));
    }

    /**
     * Test stocks quote with mode=CACHED.
     * Verifies that the API accepts and processes the mode parameter with CACHED value.
     */
    public function testQuote_modeCached_success()
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(mode: Mode::CACHED)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('integer', gettype($response->ask_size));
        $this->assertEquals('double', gettype($response->bid));
        $this->assertEquals('integer', gettype($response->bid_size));
        $this->assertEquals('double', gettype($response->mid));
        $this->assertEquals('double', gettype($response->last));
    }

    /**
     * Test stocks quote with mode=DELAYED.
     * Verifies that the API accepts and processes the mode parameter with DELAYED value.
     */
    public function testQuote_modeDelayed_success()
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(mode: Mode::DELAYED)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('integer', gettype($response->ask_size));
        $this->assertEquals('double', gettype($response->bid));
        $this->assertEquals('integer', gettype($response->bid_size));
        $this->assertEquals('double', gettype($response->mid));
        $this->assertEquals('double', gettype($response->last));
    }

    /**
     * Test quote endpoint with CSV format and dateformat=unix.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_dateFormat_unix_returnsCsv(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);
    }

    /**
     * Test quote endpoint with CSV format and add_headers=true.
     * Verifies that the CSV response includes header row.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_addHeadersTrue_includesHeaders(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV, add_headers: true)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        $lines = explode("\n", trim($csv));
        $this->assertGreaterThanOrEqual(2, count($lines), 'CSV should have at least header row and one data row');

        // First line should be headers
        $headerRow = str_getcsv($lines[0], ',', '"', '\\');
        $this->assertNotEmpty($headerRow);
        $this->assertContains('symbol', $headerRow, 'Header row should contain "symbol" column');
    }

    /**
     * Test quote endpoint with CSV format and add_headers=false.
     * Verifies that the CSV response does NOT include header row.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_addHeadersFalse_excludesHeaders(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV, add_headers: false)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        $lines = explode("\n", trim($csv));
        $this->assertGreaterThanOrEqual(1, count($lines), 'CSV should have at least one data row');

        // First line should be data, not headers
        $firstRow = str_getcsv($lines[0], ',', '"', '\\');
        $this->assertNotEmpty($firstRow);

        // If first row contains "symbol" as a value (not header), it's likely data
        // If it contains column names like "symbol", "ask", "bid" as headers, that's wrong
        // We check that the first value is not "symbol" (which would indicate it's a header)
        // Actually, let's check if the first row looks like data (contains AAPL) vs headers
        if (count($lines) > 0) {
            $firstValue = $firstRow[0] ?? '';
            // If headers are present, first row would start with column names
            // If no headers, first row should start with actual data (like "AAPL")
            // We verify that the first row does NOT look like a header row
            // by checking if it contains the symbol value or numeric values
            $hasNumericValues = false;
            foreach ($firstRow as $value) {
                if (is_numeric($value)) {
                    $hasNumericValues = true;
                    break;
                }
            }
            // If we have numeric values in the first row, it's likely data, not headers
            $this->assertTrue(
                $firstValue === 'AAPL' || $hasNumericValues,
                'First row should be data (contain symbol or numeric values), not headers'
            );
        }
    }

    /**
     * Test quote endpoint with CSV format and filename parameter.
     * Verifies that the CSV file is created and contains correct data.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_withFilename_createsFile(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_quote_' . uniqid() . '.csv';

        try {
            $response = $this->client->stocks->quote(
                symbol: 'AAPL',
                parameters: new Parameters(format: Format::CSV, filename: $testFile)
            );

            $this->assertInstanceOf(Quote::class, $response);
            $this->assertTrue($response->isCsv());

            // Verify file was created
            $this->assertFileExists($testFile, 'CSV file should be created');

            // Verify file contains data
            $fileContent = file_get_contents($testFile);
            $this->assertNotEmpty($fileContent, 'CSV file should contain data');
            $this->assertStringContainsString('AAPL', $fileContent, 'CSV file should contain symbol');

            // Verify getCsv() still works
            $csvString = $response->getCsv();
            $this->assertNotEmpty($csvString);
            $this->assertEquals($fileContent, $csvString, 'getCsv() should return same content as file');

            // Verify _saved_filename property exists
            $this->assertObjectHasProperty('_saved_filename', $response);
            $this->assertEquals($testFile, $response->_saved_filename);
        } finally {
            // Clean up
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    /**
     * Test quote endpoint with CSV format without filename parameter.
     * Verifies backward compatibility - object is returned without file creation.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_withoutFilename_returnsObject(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csvString = $response->getCsv();
        $this->assertNotEmpty($csvString);
        $this->assertStringContainsString('AAPL', $csvString);

        // Verify no file was created (_saved_filename should be null)
        $this->assertNull($response->_saved_filename ?? null, '_saved_filename should be null when no filename parameter is provided');
    }

    /**
     * Test quote endpoint with CSV format and nested directory path.
     * Verifies that file is created in the nested directory.
     *
     * Note: SDK does not create directories - user must create them first.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_nestedDirectory_createsFile(): void
    {
        $tempDir = sys_get_temp_dir();
        $nestedDir = $tempDir . '/test_nested_' . uniqid();
        $subdir = $nestedDir . '/subdir';
        // SDK does not create directories - we must create the full path first
        mkdir($subdir, 0755, true);
        $testFile = $subdir . '/test.csv';

        try {
            $response = $this->client->stocks->quote(
                symbol: 'AAPL',
                parameters: new Parameters(format: Format::CSV, filename: $testFile)
            );

            $this->assertInstanceOf(Quote::class, $response);
            $this->assertTrue($response->isCsv());

            // Verify file was created
            $this->assertFileExists($testFile, 'CSV file should be created in nested directory');

            // Verify file contains data
            $fileContent = file_get_contents($testFile);
            $this->assertNotEmpty($fileContent);
        } finally {
            // Clean up
            if (file_exists($testFile)) {
                unlink($testFile);
            }
            if (is_dir($subdir)) {
                rmdir($subdir);
            }
            if (is_dir($nestedDir)) {
                rmdir($nestedDir);
            }
        }
    }

    /**
     * Test quote endpoint with CSV format and existing file.
     * Verifies that exception is thrown to prevent overwriting.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_existingFile_throwsException(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_existing_' . uniqid() . '.csv';

        // Create the file first
        file_put_contents($testFile, 'existing content');

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('File already exists');

            $this->client->stocks->quote(
                symbol: 'AAPL',
                parameters: new Parameters(format: Format::CSV, filename: $testFile)
            );
        } finally {
            // Clean up
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    /**
     * Test quote endpoint with CSV format and invalid extension.
     * Verifies that exception is thrown for invalid extension.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_invalidExtension_throwsException(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_invalid_' . uniqid() . '.txt';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('filename must end with .csv');

        $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV, filename: $testFile)
        );
    }

    /**
     * Test saveToFile() method on response object.
     * Verifies that saveToFile() works correctly.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_saveToFile_works(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_savetofile_' . uniqid() . '.csv';

        try {
            // Get response without filename
            $response = $this->client->stocks->quote(
                symbol: 'AAPL',
                parameters: new Parameters(format: Format::CSV)
            );

            $this->assertInstanceOf(Quote::class, $response);
            $this->assertTrue($response->isCsv());

            // Use saveToFile() method
            $savedPath = $response->saveToFile($testFile);

            // Verify file was created
            $this->assertFileExists($testFile, 'File should be created by saveToFile()');
            $this->assertFileExists($savedPath, 'Returned path should exist');

            // Verify file contains data
            $fileContent = file_get_contents($testFile);
            $this->assertNotEmpty($fileContent);
            $this->assertStringContainsString('AAPL', $fileContent);

            // Verify content matches getCsv()
            $this->assertEquals($response->getCsv(), $fileContent);
        } finally {
            // Clean up
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }
}
