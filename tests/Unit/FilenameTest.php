<?php

namespace MarketDataApp\Tests\Unit;

use InvalidArgumentException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;
use MarketDataApp\Settings;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Filename SDK feature.
 *
 * Tests parameter validation, merging, and restrictions for the filename parameter.
 * Note: filename is an SDK feature that allows saving CSV/HTML output to files.
 * It is NOT an API universal parameter.
 */
class FilenameTest extends TestCase
{
    use MockResponses;

    /**
     * Original environment variable values to restore after tests.
     */
    protected array $originalEnv = [];

    /**
     * Temporary directories created during tests.
     */
    protected array $tempDirs = [];

    /**
     * Original working directory.
     */
    protected string $originalCwd;

    /**
     * Client instance for testing.
     */
    protected ?Client $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = getcwd();
        $this->saveEnvironmentState();
        $this->clearMarketDataToken();
    }

    protected function tearDown(): void
    {
        if (isset($this->originalCwd) && is_dir($this->originalCwd)) {
            chdir($this->originalCwd);
        }
        $this->restoreEnvironmentState();
        $this->cleanupTempDirs();
        $this->client = null;
        parent::tearDown();
    }

    protected function saveEnvironmentState(): void
    {
        $this->originalEnv['MARKETDATA_TOKEN'] = [
            'getenv' => getenv('MARKETDATA_TOKEN'),
            '_ENV' => $_ENV['MARKETDATA_TOKEN'] ?? null,
        ];
    }

    protected function restoreEnvironmentState(): void
    {
        $values = $this->originalEnv['MARKETDATA_TOKEN'];
        if ($values['getenv'] !== false) {
            putenv("MARKETDATA_TOKEN={$values['getenv']}");
        } else {
            putenv('MARKETDATA_TOKEN');
        }
        if ($values['_ENV'] !== null) {
            $_ENV['MARKETDATA_TOKEN'] = $values['_ENV'];
        } else {
            unset($_ENV['MARKETDATA_TOKEN']);
        }
    }

    protected function clearMarketDataToken(): void
    {
        putenv('MARKETDATA_TOKEN');
        unset($_ENV['MARKETDATA_TOKEN']);
    }

    protected function createTempDir(): string
    {
        $tempDir = sys_get_temp_dir() . '/marketdata_sdk_test_' . uniqid();
        mkdir($tempDir, 0755, true);
        $this->tempDirs[] = $tempDir;
        return $tempDir;
    }

    protected function cleanupTempDirs(): void
    {
        foreach (array_reverse($this->tempDirs) as $dir) {
            if (is_dir($dir)) {
                $files = array_diff(scandir($dir), ['.', '..']);
                foreach ($files as $file) {
                    $filePath = $dir . '/' . $file;
                    if (is_file($filePath)) {
                        @unlink($filePath);
                    } elseif (is_dir($filePath)) {
                        @rmdir($filePath);
                    }
                }
                @rmdir($dir);
            }
        }
        $this->tempDirs = [];
    }

    protected function resetDotenvLoadedFlag(): void
    {
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);
    }

    protected function callMergeParameters(\MarketDataApp\Endpoints\Stocks $stocks, ?Parameters $methodParams): Parameters
    {
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('mergeParameters');
        return $method->invoke($stocks, $methodParams);
    }

    // ============================================================================
    // Constructor Validation Tests
    // ============================================================================

    public function testParameters_filename_withCsv_success(): void
    {
        $tempDir = $this->createTempDir();
        $testFile = $tempDir . '/test_' . uniqid() . '.csv';

        $params = new Parameters(format: Format::CSV, filename: $testFile);
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertEquals($testFile, $params->filename);
    }

    public function testParameters_filename_withHtml_success(): void
    {
        $tempDir = $this->createTempDir();
        $testFile = $tempDir . '/test_' . uniqid() . '.html';

        $params = new Parameters(format: Format::HTML, filename: $testFile);
        $this->assertEquals(Format::HTML, $params->format);
        $this->assertEquals($testFile, $params->filename);
    }

    public function testParameters_filename_withJson_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filename parameter can only be used with CSV or HTML format');

        $tempDir = $this->createTempDir();
        $testFile = $tempDir . '/test_' . uniqid() . '.csv';

        new Parameters(format: Format::JSON, filename: $testFile);
    }

    public function testParameters_filename_invalidExtension_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filename must end with .csv');

        $tempDir = $this->createTempDir();
        $testFile = $tempDir . '/test_' . uniqid() . '.txt';

        new Parameters(format: Format::CSV, filename: $testFile);
    }

    public function testParameters_filename_htmlFormatRequiresHtmlExtension_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filename must end with .html');

        $tempDir = $this->createTempDir();
        $testFile = $tempDir . '/test_' . uniqid() . '.csv';

        new Parameters(format: Format::HTML, filename: $testFile);
    }

    public function testParameters_filename_nonExistentDirectory_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Directory does not exist');

        $tempDir = $this->createTempDir();
        chdir($tempDir);

        $nonExistentDir = 'nonexistent_' . uniqid();
        $testFile = $nonExistentDir . '/test.csv';

        new Parameters(format: Format::CSV, filename: $testFile);
    }

    public function testParameters_filename_existingFile_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File already exists');

        $tempDir = $this->createTempDir();
        $testFile = $tempDir . '/test_' . uniqid() . '.csv';

        file_put_contents($testFile, 'test content');

        try {
            new Parameters(format: Format::CSV, filename: $testFile);
        } finally {
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    public function testParameters_filename_relativePath_success(): void
    {
        $tempDir = $this->createTempDir();
        chdir($tempDir);

        $testFile = 'test.csv';
        $params = new Parameters(format: Format::CSV, filename: $testFile);
        $this->assertEquals($testFile, $params->filename);
    }

    public function testParameters_filename_absolutePath_success(): void
    {
        $tempDir = $this->createTempDir();
        $testFile = $tempDir . '/test_' . uniqid() . '.csv';

        $params = new Parameters(format: Format::CSV, filename: $testFile);
        $this->assertEquals($testFile, $params->filename);
    }

    public function testParameters_filename_nestedDirectory_success(): void
    {
        $tempDir = $this->createTempDir();
        $nestedDir = $tempDir . '/nested_' . uniqid();
        mkdir($nestedDir, 0755, true);
        $this->tempDirs[] = $nestedDir;
        $testFile = $nestedDir . '/test.csv';

        $params = new Parameters(format: Format::CSV, filename: $testFile);
        $this->assertEquals($testFile, $params->filename);
    }

    public function testParameters_filename_null_withCsv_success(): void
    {
        $params = new Parameters(format: Format::CSV, filename: null);
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertNull($params->filename);
    }

    public function testParameters_filename_null_withHtml_success(): void
    {
        $params = new Parameters(format: Format::HTML, filename: null);
        $this->assertEquals(Format::HTML, $params->format);
        $this->assertNull($params->filename);
    }

    public function testParameters_filename_null_withJson_success(): void
    {
        $params = new Parameters(format: Format::JSON, filename: null);
        $this->assertEquals(Format::JSON, $params->format);
        $this->assertNull($params->filename);
    }

    public function testParameters_filename_withOtherParameters_success(): void
    {
        $tempDir = $this->createTempDir();
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

    // ============================================================================
    // Parameter Merging Tests
    // ============================================================================

    public function testMergeParameters_filename_methodParamOverridesClientDefault(): void
    {
        $tempDir = $this->createTempDir();
        $defaultFile = $tempDir . '/default.csv';
        $testFile = $tempDir . '/test.csv';

        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->filename = $defaultFile;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV, filename: $testFile));
        $this->assertEquals($testFile, $merged->filename);
    }

    public function testMergeParameters_filename_nullMethodParamUsesClientDefault(): void
    {
        $tempDir = $this->createTempDir();
        $defaultFile = $tempDir . '/default.csv';

        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->filename = $defaultFile;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV));
        $this->assertEquals($defaultFile, $merged->filename);
    }

    // ============================================================================
    // Format Restriction Tests
    // ============================================================================

    public function testIntegration_filename_invalidWithJsonFormat(): void
    {
        $tempDir = $this->createTempDir();
        $filename = $tempDir . '/test.csv';

        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;
        $this->client->default_params->filename = $filename;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filename parameter can only be used with CSV or HTML format');

        $this->client->stocks->quote('AAPL', parameters: new Parameters(format: Format::JSON));
    }

    public function testIntegration_multiSymbol_filenameIsAllowed(): void
    {
        $tempDir = $this->createTempDir();
        $filename = $tempDir . '/test.csv';

        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;
        $this->client->default_params->filename = $filename;

        // Mock CSV response for multi-symbol request (single API call)
        $csvContent = "symbol,ask\nAAPL,150.0\nMSFT,300.0";
        $this->setMockResponses([
            new \GuzzleHttp\Psr7\Response(200, [], $csvContent)
        ]);

        // Multi-symbol quotes now uses a single API call, so filename works
        $response = $this->client->stocks->quotes(['AAPL', 'MSFT'], parameters: null);

        $this->assertIsObject($response);
        $this->assertCount(1, $response->quotes);
        $this->assertTrue($response->quotes[0]->isCsv());
        $this->assertFileExists($filename);
        $this->assertStringContainsString('AAPL', file_get_contents($filename));
    }

    // ============================================================================
    // BUG-002 Regression Test: _filename must not leak into query parameters
    // ============================================================================

    /**
     * Test that _filename is not sent as a query parameter to the API.
     *
     * This is a regression test for BUG-002 where _filename was being sent
     * in the query string despite being intended for internal SDK use only.
     *
     * Mock response: NOT from real API output (uses synthetic/test data)
     *
     * @return void
     */
    public function testFilename_notSentAsQueryParameter(): void
    {
        $tempDir = $this->createTempDir();
        $filename = $tempDir . '/test.csv';

        $this->client = new Client('');

        // Set up mock with history tracking to capture the request
        $history = [];
        $csvContent = "symbol,ask\nAAPL,150.0";
        $this->setMockResponsesWithHistory([
            new \GuzzleHttp\Psr7\Response(200, [], $csvContent)
        ], $history);

        // Make request with filename parameter
        $params = new Parameters(format: Format::CSV, filename: $filename);
        $this->client->stocks->quote('AAPL', parameters: $params);

        // Verify _filename was NOT sent in query parameters
        $this->assertCount(1, $history, 'Expected exactly one request');
        $request = $history[0]['request'];
        $queryString = $request->getUri()->getQuery();
        parse_str($queryString, $queryParams);

        $this->assertArrayNotHasKey('_filename', $queryParams, '_filename should not be sent to API');

        // Verify the file was still created (feature works)
        $this->assertFileExists($filename);
    }
}
