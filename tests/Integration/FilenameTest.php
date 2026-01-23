<?php

namespace MarketDataApp\Tests\Integration;

use InvalidArgumentException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Enums\Format;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Filename SDK feature.
 *
 * Tests that the filename parameter works correctly with the actual API
 * to save CSV/HTML output to files.
 *
 * Note: filename is an SDK feature, NOT an API universal parameter.
 */
class FilenameTest extends TestCase
{
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client();
    }

    public function testFilename_createsFile(): void
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
            $this->assertFileExists($testFile, 'CSV file should be created');

            $fileContent = file_get_contents($testFile);
            $this->assertNotEmpty($fileContent, 'CSV file should contain data');
            $this->assertStringContainsString('AAPL', $fileContent, 'CSV file should contain symbol');
            $this->assertEquals($fileContent, $response->getCsv(), 'getCsv() should return same content as file');
            $this->assertEquals($testFile, $response->_saved_filename);
        } finally {
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    public function testFilename_withoutFilename_returnsObject(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());
        $this->assertNotEmpty($response->getCsv());
        $this->assertNull($response->_saved_filename ?? null);
    }

    public function testFilename_nestedDirectory_createsDirectoryAndFile(): void
    {
        $tempDir = sys_get_temp_dir();
        $nestedDir = $tempDir . '/test_nested_' . uniqid();
        mkdir($nestedDir, 0755, true);
        $testFile = $nestedDir . '/subdir/test.csv';

        try {
            $response = $this->client->stocks->quote(
                symbol: 'AAPL',
                parameters: new Parameters(format: Format::CSV, filename: $testFile)
            );

            $this->assertInstanceOf(Quote::class, $response);
            $this->assertDirectoryExists(dirname($testFile), 'Nested directory should be created');
            $this->assertFileExists($testFile, 'CSV file should be created in nested directory');
        } finally {
            if (file_exists($testFile)) {
                unlink($testFile);
            }
            $subdir = dirname($testFile);
            if (is_dir($subdir)) {
                rmdir($subdir);
            }
            if (is_dir($nestedDir)) {
                rmdir($nestedDir);
            }
        }
    }

    public function testFilename_existingFile_throwsException(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_existing_' . uniqid() . '.csv';
        file_put_contents($testFile, 'existing content');

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('File already exists');

            $this->client->stocks->quote(
                symbol: 'AAPL',
                parameters: new Parameters(format: Format::CSV, filename: $testFile)
            );
        } finally {
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    public function testFilename_invalidExtension_throwsException(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_invalid_' . uniqid() . '.txt';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filename must end with .csv');

        $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV, filename: $testFile)
        );
    }

    public function testFilename_saveToFile_works(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_savetofile_' . uniqid() . '.csv';

        try {
            $response = $this->client->stocks->quote(
                symbol: 'AAPL',
                parameters: new Parameters(format: Format::CSV)
            );

            $this->assertInstanceOf(Quote::class, $response);
            $this->assertTrue($response->isCsv());

            $savedPath = $response->saveToFile($testFile);

            $this->assertFileExists($testFile, 'File should be created by saveToFile()');
            $this->assertFileExists($savedPath, 'Returned path should exist');

            $fileContent = file_get_contents($testFile);
            $this->assertNotEmpty($fileContent);
            $this->assertStringContainsString('AAPL', $fileContent);
            $this->assertEquals($response->getCsv(), $fileContent);
        } finally {
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    public function testFilename_multiSymbol_savesToFile(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_multi_symbol_' . uniqid() . '.csv';

        try {
            $response = $this->client->stocks->quotes(
                symbols: ['AAPL', 'MSFT'],
                parameters: new Parameters(format: Format::CSV, filename: $testFile)
            );

            $this->assertFileExists($testFile, 'CSV file should be created for multi-symbol request');

            $fileContent = file_get_contents($testFile);
            $this->assertNotEmpty($fileContent, 'CSV file should contain data');
            $this->assertStringContainsString('AAPL', $fileContent, 'CSV file should contain AAPL');
            $this->assertStringContainsString('MSFT', $fileContent, 'CSV file should contain MSFT');
        } finally {
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }
}
