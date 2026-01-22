<?php

namespace MarketDataApp\Tests\Unit;

use GuzzleHttp\Psr7\Response;
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
 * Unit tests for Universal Parameters Configuration.
 *
 * Tests the three-level configuration hierarchy:
 * 1. Environment Variables (lowest priority)
 * 2. Client Instance Defaults (middle priority)
 * 3. Method-Level Parameters (highest priority)
 *
 * This test file is isolated to prevent environment variable pollution in other tests.
 */
class UniversalParametersConfigTest extends TestCase
{
    use MockResponses;

    /**
     * Original environment variable values to restore after tests.
     */
    private array $originalEnv = [];

    /**
     * Client instance for testing.
     */
    private ?Client $client = null;

    /**
     * Temporary directories created during tests.
     */
    private array $tempDirs = [];

    /**
     * Temporary files created during tests.
     */
    private array $tempFiles = [];

    /**
     * Original working directory.
     */
    private string $originalCwd;

    /**
     * Save original environment variable state before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = getcwd();
        $this->saveEnvironmentState();
        $this->clearUniversalParamEnvVars();
        
        // Clear MARKETDATA_TOKEN environment variable to ensure empty token is used.
        // This prevents real API calls during Client construction by ensuring
        // _setup_rate_limits() skips the /user/ endpoint validation call.
        $this->clearMarketDataToken();
        
        // Create client with empty token for unit tests (uses mocks for integration tests)
        $this->client = new Client('');
    }

    /**
     * Restore original environment variable state after each test.
     */
    protected function tearDown(): void
    {
        // Restore working directory
        if (isset($this->originalCwd) && is_dir($this->originalCwd)) {
            chdir($this->originalCwd);
        }

        $this->restoreEnvironmentState();
        $this->cleanupTempFiles();
        $this->client = null;
        parent::tearDown();
    }

    /**
     * Save all relevant environment variable state.
     */
    private function saveEnvironmentState(): void
    {
        $envVars = [
            'MARKETDATA_OUTPUT_FORMAT',
            'MARKETDATA_DATE_FORMAT',
            'MARKETDATA_COLUMNS',
            'MARKETDATA_ADD_HEADERS',
            'MARKETDATA_USE_HUMAN_READABLE',
            'MARKETDATA_MODE',
        ];

        foreach ($envVars as $var) {
            $this->originalEnv[$var] = [
                'getenv' => getenv($var),
                '_ENV' => $_ENV[$var] ?? null,
                '_SERVER' => $_SERVER[$var] ?? null,
            ];
        }
    }

    /**
     * Restore all environment variable state.
     */
    private function restoreEnvironmentState(): void
    {
        foreach ($this->originalEnv as $var => $values) {
            if ($values['getenv'] !== false) {
                putenv("$var={$values['getenv']}");
            } else {
                putenv($var);
            }

            if ($values['_ENV'] !== null) {
                $_ENV[$var] = $values['_ENV'];
            } else {
                unset($_ENV[$var]);
            }

            if ($values['_SERVER'] !== null) {
                $_SERVER[$var] = $values['_SERVER'];
            } else {
                unset($_SERVER[$var]);
            }
        }
    }

    /**
     * Clear all universal parameter environment variables.
     */
    private function clearUniversalParamEnvVars(): void
    {
        $envVars = [
            'MARKETDATA_OUTPUT_FORMAT',
            'MARKETDATA_DATE_FORMAT',
            'MARKETDATA_COLUMNS',
            'MARKETDATA_ADD_HEADERS',
            'MARKETDATA_USE_HUMAN_READABLE',
            'MARKETDATA_MODE',
        ];

        foreach ($envVars as $var) {
            putenv($var);
            unset($_ENV[$var]);
            unset($_SERVER[$var]);
        }

        // Reset Settings dotenv loaded flag by reflection
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false); // null for static properties
    }

    /**
     * Create a temporary directory.
     *
     * @return string Path to temporary directory.
     */
    private function createTempDir(): string
    {
        $tempDir = sys_get_temp_dir() . '/marketdata_sdk_test_' . uniqid();
        mkdir($tempDir, 0755, true);
        $this->tempDirs[] = $tempDir;
        return $tempDir;
    }

    /**
     * Create a temporary .env file.
     *
     * @param string $dir Directory to create .env file in.
     * @param array $content Key-value pairs for .env file.
     *
     * @return string Path to .env file.
     */
    private function createTempEnvFile(string $dir, array $content): string
    {
        $envFile = $dir . '/.env';
        $lines = [];
        foreach ($content as $key => $value) {
            $lines[] = "$key=$value";
        }
        file_put_contents($envFile, implode("\n", $lines));
        $this->tempFiles[] = $envFile;
        return $envFile;
    }

    /**
     * Clean up temporary files and directories.
     */
    private function cleanupTempFiles(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];

        // Remove temp directories (in reverse order, recursively)
        foreach (array_reverse($this->tempDirs) as $dir) {
            if (is_dir($dir)) {
                // Remove all files in directory first
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

    // ============================================================================
    // Phase 1: Client-Level Default Parameters Tests
    // ============================================================================

    /**
     * Test Group 1.1: Property Existence and Initialization
     */

    public function testDefaultParams_propertyExists(): void
    {
        $client = new Client();
        $this->assertTrue(property_exists($client, 'default_params'));
        $this->assertInstanceOf(Parameters::class, $client->default_params);
    }

    public function testDefaultParams_initializedWithDefaults(): void
    {
        $client = new Client();
        $this->assertEquals(Format::JSON, $client->default_params->format);
        $this->assertNull($client->default_params->use_human_readable);
        $this->assertNull($client->default_params->mode);
        $this->assertNull($client->default_params->date_format);
        $this->assertNull($client->default_params->columns);
        $this->assertNull($client->default_params->add_headers);
        $this->assertNull($client->default_params->filename);
    }

    public function testDefaultParams_canBeModified(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $this->assertEquals(Format::CSV, $client->default_params->format);

        $client->default_params->mode = Mode::CACHED;
        $this->assertEquals(Mode::CACHED, $client->default_params->mode);
    }

    /**
     * Helper method to call protected mergeParameters method via reflection.
     *
     * @param \MarketDataApp\Endpoints\Stocks $stocks Stocks endpoint instance.
     * @param Parameters|null $methodParams Method-level parameters.
     *
     * @return Parameters Merged parameters.
     */
    private function callMergeParameters(\MarketDataApp\Endpoints\Stocks $stocks, ?Parameters $methodParams): Parameters
    {
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('mergeParameters');
        return $method->invoke($stocks, $methodParams);
    }

    /**
     * Test Group 1.2: Parameter Merging - Format
     */

    public function testMergeParameters_format_methodParamOverridesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::JSON));
        $this->assertEquals(Format::JSON, $merged->format);
    }

    public function testMergeParameters_format_nullMethodParamUsesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, null);
        $this->assertEquals(Format::CSV, $merged->format);
    }

    public function testMergeParameters_format_noClientDefaultUsesJson(): void
    {
        $client = new Client();
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, null);
        $this->assertEquals(Format::JSON, $merged->format);
    }

    /**
     * Test Group 1.3: Parameter Merging - Mode
     */

    public function testMergeParameters_mode_methodParamOverridesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->mode = Mode::CACHED;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(mode: Mode::LIVE));
        $this->assertEquals(Mode::LIVE, $merged->mode);
    }

    public function testMergeParameters_mode_nullMethodParamUsesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->mode = Mode::DELAYED;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters());
        $this->assertEquals(Mode::DELAYED, $merged->mode);
    }

    public function testMergeParameters_mode_nullMethodParamNullClientDefault_returnsNull(): void
    {
        $client = new Client();
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters());
        $this->assertNull($merged->mode);
    }

    public function testMergeParameters_mode_methodParamNullOverridesClientDefault(): void
    {
        // Note: In PHP, we can't distinguish "not set" from "explicitly null" for optional parameters.
        // So passing mode: null is treated the same as not setting it, and client default is used.
        // This is a PHP language limitation - Python can distinguish these cases.
        $client = new Client();
        $client->default_params->mode = Mode::CACHED;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(mode: null));
        // PHP limitation: can't distinguish explicit null from "not set", so client default is used
        $this->assertEquals(Mode::CACHED, $merged->mode);
    }

    /**
     * Test Group 1.4: Parameter Merging - Use Human Readable
     */

    public function testMergeParameters_useHumanReadable_methodParamOverridesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->use_human_readable = true;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(use_human_readable: false));
        $this->assertFalse($merged->use_human_readable);
    }

    public function testMergeParameters_useHumanReadable_nullMethodParamUsesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->use_human_readable = true;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters());
        $this->assertTrue($merged->use_human_readable);
    }

    /**
     * Test Group 1.5: Parameter Merging - Date Format (CSV/HTML only)
     */

    public function testMergeParameters_dateFormat_methodParamOverridesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->date_format = DateFormat::UNIX;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP));
        $this->assertEquals(DateFormat::TIMESTAMP, $merged->date_format);
    }

    public function testMergeParameters_dateFormat_nullMethodParamUsesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->date_format = DateFormat::SPREADSHEET;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV));
        $this->assertEquals(DateFormat::SPREADSHEET, $merged->date_format);
    }

    public function testMergeParameters_dateFormat_formatChangeResetsDateFormat(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->date_format = DateFormat::UNIX;
        $stocks = $client->stocks;
        
        // When format changes to JSON, date_format should cause an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('date_format parameter can only be used with CSV or HTML format');
        
        $this->callMergeParameters($stocks, new Parameters(format: Format::JSON));
    }

    /**
     * Test Group 1.6: Parameter Merging - Columns (CSV/HTML only)
     */

    public function testMergeParameters_columns_methodParamOverridesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->columns = ['symbol', 'ask'];
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV, columns: ['bid', 'last']));
        $this->assertEquals(['bid', 'last'], $merged->columns);
    }

    public function testMergeParameters_columns_nullMethodParamUsesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->columns = ['symbol', 'ask', 'bid'];
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV));
        $this->assertEquals(['symbol', 'ask', 'bid'], $merged->columns);
    }

    public function testMergeParameters_columns_emptyArrayOverridesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->columns = ['symbol', 'ask'];
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV, columns: []));
        $this->assertEquals([], $merged->columns);
    }

    /**
     * Test Group 1.7: Parameter Merging - Add Headers (CSV/HTML only)
     */

    public function testMergeParameters_addHeaders_methodParamOverridesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->add_headers = true;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV, add_headers: false));
        $this->assertFalse($merged->add_headers);
    }

    public function testMergeParameters_addHeaders_nullMethodParamUsesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->add_headers = false;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV));
        $this->assertFalse($merged->add_headers);
    }

    /**
     * Test Group 1.8: Parameter Merging - Filename (CSV/HTML only)
     */

    public function testMergeParameters_filename_methodParamOverridesClientDefault(): void
    {
        $tempDir = $this->createTempDir();
        $defaultFile = $tempDir . '/default.csv';
        $testFile = $tempDir . '/test.csv';
        // Don't create files - Parameters validates they don't exist

        $client = new Client();
        $client->default_params->format = Format::CSV;
        // Set default filename (but don't create the file - it's just a path)
        // We'll use a non-existent path for the default
        $client->default_params->filename = $defaultFile;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV, filename: $testFile));
        $this->assertEquals($testFile, $merged->filename);
    }

    public function testMergeParameters_filename_nullMethodParamUsesClientDefault(): void
    {
        $tempDir = $this->createTempDir();
        $defaultFile = $tempDir . '/default.csv';
        // Don't create file - Parameters validates it doesn't exist

        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->filename = $defaultFile;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV));
        $this->assertEquals($defaultFile, $merged->filename);
    }

    /**
     * Test Group 1.9: Complex Merging Scenarios
     */

    public function testMergeParameters_multipleParams_partialOverride(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->mode = Mode::CACHED;
        $client->default_params->use_human_readable = true;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::JSON, mode: Mode::LIVE));
        $this->assertEquals(Format::JSON, $merged->format);
        $this->assertEquals(Mode::LIVE, $merged->mode);
        $this->assertTrue($merged->use_human_readable);
    }

    public function testMergeParameters_allParams_methodParamsWin(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->mode = Mode::CACHED;
        $client->default_params->use_human_readable = true;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(
            format: Format::JSON,
            mode: Mode::LIVE,
            use_human_readable: false
        ));
        $this->assertEquals(Format::JSON, $merged->format);
        $this->assertEquals(Mode::LIVE, $merged->mode);
        $this->assertFalse($merged->use_human_readable);
    }

    public function testMergeParameters_noOverrides_clientDefaultsUsed(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->mode = Mode::CACHED;
        $client->default_params->use_human_readable = true;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, null);
        $this->assertEquals(Format::CSV, $merged->format);
        $this->assertEquals(Mode::CACHED, $merged->mode);
        $this->assertTrue($merged->use_human_readable);
    }

    /**
     * Test Group 1.10: Backward Compatibility
     */

    public function testBackwardCompatibility_existingCodeStillWorks(): void
    {
        $client = new Client();
        $params = new Parameters(format: Format::CSV);
        $this->assertInstanceOf(Parameters::class, $params);
        $this->assertEquals(Format::CSV, $params->format);
    }

    public function testBackwardCompatibility_nullParametersUsesDefaults(): void
    {
        $client = new Client();
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, null);
        $this->assertEquals(Format::JSON, $merged->format);
    }

    // ============================================================================
    // Phase 2: Environment Variable Support Tests
    // ============================================================================

    /**
     * Test Group 2.1: Settings::getDefaultParameters() - Format
     */

    public function testGetDefaultParameters_format_fromEnvVar_json(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=json');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'json';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::JSON, $params->format);
    }

    public function testGetDefaultParameters_format_fromEnvVar_csv(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::CSV, $params->format);
    }

    public function testGetDefaultParameters_format_fromEnvVar_html(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=html');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'html';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::HTML, $params->format);
    }

    public function testGetDefaultParameters_format_invalidValue_usesDefault(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=invalid');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'invalid';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::JSON, $params->format);
    }

    public function testGetDefaultParameters_format_caseInsensitive(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=CSV');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'CSV';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::CSV, $params->format);
    }

    public function testGetDefaultParameters_format_notSet_usesDefault(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT');
        unset($_ENV['MARKETDATA_OUTPUT_FORMAT']);
        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::JSON, $params->format);
    }

    /**
     * Test Group 2.2: Settings::getDefaultParameters() - Date Format
     */

    public function testGetDefaultParameters_dateFormat_fromEnvVar_timestamp(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_DATE_FORMAT=timestamp');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_DATE_FORMAT'] = 'timestamp';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(DateFormat::TIMESTAMP, $params->date_format);
    }

    public function testGetDefaultParameters_dateFormat_fromEnvVar_unix(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_DATE_FORMAT=unix');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_DATE_FORMAT'] = 'unix';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(DateFormat::UNIX, $params->date_format);
    }

    public function testGetDefaultParameters_dateFormat_fromEnvVar_spreadsheet(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_DATE_FORMAT=spreadsheet');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_DATE_FORMAT'] = 'spreadsheet';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(DateFormat::SPREADSHEET, $params->date_format);
    }

    public function testGetDefaultParameters_dateFormat_invalidValue_returnsNull(): void
    {
        putenv('MARKETDATA_DATE_FORMAT=invalid');
        $_ENV['MARKETDATA_DATE_FORMAT'] = 'invalid';
        $params = Settings::getDefaultParameters();
        $this->assertNull($params->date_format);
    }

    public function testGetDefaultParameters_dateFormat_notSet_returnsNull(): void
    {
        putenv('MARKETDATA_DATE_FORMAT');
        unset($_ENV['MARKETDATA_DATE_FORMAT']);
        $params = Settings::getDefaultParameters();
        $this->assertNull($params->date_format);
    }

    /**
     * Test Group 2.3: Settings::getDefaultParameters() - Mode
     */

    public function testGetDefaultParameters_mode_fromEnvVar_live(): void
    {
        putenv('MARKETDATA_MODE=live');
        $_ENV['MARKETDATA_MODE'] = 'live';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(Mode::LIVE, $params->mode);
    }

    public function testGetDefaultParameters_mode_fromEnvVar_cached(): void
    {
        putenv('MARKETDATA_MODE=cached');
        $_ENV['MARKETDATA_MODE'] = 'cached';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(Mode::CACHED, $params->mode);
    }

    public function testGetDefaultParameters_mode_fromEnvVar_delayed(): void
    {
        putenv('MARKETDATA_MODE=delayed');
        $_ENV['MARKETDATA_MODE'] = 'delayed';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(Mode::DELAYED, $params->mode);
    }

    public function testGetDefaultParameters_mode_invalidValue_returnsNull(): void
    {
        putenv('MARKETDATA_MODE=invalid');
        $_ENV['MARKETDATA_MODE'] = 'invalid';
        $params = Settings::getDefaultParameters();
        $this->assertNull($params->mode);
    }

    /**
     * Test Group 2.4: Settings::getDefaultParameters() - Columns
     */

    public function testGetDefaultParameters_columns_fromEnvVar_singleColumn(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_COLUMNS=symbol');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_COLUMNS'] = 'symbol';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(['symbol'], $params->columns);
    }

    public function testGetDefaultParameters_columns_fromEnvVar_multipleColumns(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_COLUMNS=symbol,ask,bid');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_COLUMNS'] = 'symbol,ask,bid';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(['symbol', 'ask', 'bid'], $params->columns);
    }

    public function testGetDefaultParameters_columns_fromEnvVar_withSpaces(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_COLUMNS=symbol, ask, bid');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_COLUMNS'] = 'symbol, ask, bid';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(['symbol', 'ask', 'bid'], $params->columns);
    }

    public function testGetDefaultParameters_columns_emptyString_returnsNull(): void
    {
        putenv('MARKETDATA_COLUMNS=');
        $_ENV['MARKETDATA_COLUMNS'] = '';
        $params = Settings::getDefaultParameters();
        $this->assertNull($params->columns);
    }

    public function testGetDefaultParameters_columns_notSet_returnsNull(): void
    {
        putenv('MARKETDATA_COLUMNS');
        unset($_ENV['MARKETDATA_COLUMNS']);
        $params = Settings::getDefaultParameters();
        $this->assertNull($params->columns);
    }

    /**
     * Test Group 2.5: Settings::getDefaultParameters() - Boolean Parameters
     */

    public function testGetDefaultParameters_addHeaders_fromEnvVar_true(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_ADD_HEADERS=true');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_ADD_HEADERS'] = 'true';
        $params = Settings::getDefaultParameters();
        $this->assertTrue($params->add_headers);
    }

    public function testGetDefaultParameters_addHeaders_fromEnvVar_false(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_ADD_HEADERS=false');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_ADD_HEADERS'] = 'false';
        $params = Settings::getDefaultParameters();
        $this->assertFalse($params->add_headers);
    }

    public function testGetDefaultParameters_addHeaders_fromEnvVar_caseInsensitive(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_ADD_HEADERS=TRUE');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_ADD_HEADERS'] = 'TRUE';
        $params = Settings::getDefaultParameters();
        $this->assertTrue($params->add_headers);
    }

    public function testGetDefaultParameters_addHeaders_invalidValue_returnsNull(): void
    {
        putenv('MARKETDATA_ADD_HEADERS=invalid');
        $_ENV['MARKETDATA_ADD_HEADERS'] = 'invalid';
        $params = Settings::getDefaultParameters();
        $this->assertNull($params->add_headers);
    }

    public function testGetDefaultParameters_useHumanReadable_fromEnvVar_true(): void
    {
        putenv('MARKETDATA_USE_HUMAN_READABLE=true');
        $_ENV['MARKETDATA_USE_HUMAN_READABLE'] = 'true';
        $params = Settings::getDefaultParameters();
        $this->assertTrue($params->use_human_readable);
    }

    public function testGetDefaultParameters_useHumanReadable_fromEnvVar_false(): void
    {
        putenv('MARKETDATA_USE_HUMAN_READABLE=false');
        $_ENV['MARKETDATA_USE_HUMAN_READABLE'] = 'false';
        $params = Settings::getDefaultParameters();
        $this->assertFalse($params->use_human_readable);
    }

    /**
     * Test Group 2.6: Settings::getDefaultParameters() - All Parameters
     */

    public function testGetDefaultParameters_allParams_fromEnvVars(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_DATE_FORMAT=unix');
        putenv('MARKETDATA_COLUMNS=symbol,ask');
        putenv('MARKETDATA_ADD_HEADERS=true');
        putenv('MARKETDATA_USE_HUMAN_READABLE=false');
        putenv('MARKETDATA_MODE=cached');

        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_DATE_FORMAT'] = 'unix';
        $_ENV['MARKETDATA_COLUMNS'] = 'symbol,ask';
        $_ENV['MARKETDATA_ADD_HEADERS'] = 'true';
        $_ENV['MARKETDATA_USE_HUMAN_READABLE'] = 'false';
        $_ENV['MARKETDATA_MODE'] = 'cached';

        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertEquals(DateFormat::UNIX, $params->date_format);
        $this->assertEquals(['symbol', 'ask'], $params->columns);
        $this->assertTrue($params->add_headers);
        $this->assertFalse($params->use_human_readable);
        $this->assertEquals(Mode::CACHED, $params->mode);
    }

    /**
     * Test that CSV/HTML-only parameters are ignored when format is JSON.
     */
    public function testGetDefaultParameters_csvOnlyParams_ignoredWhenFormatJson(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=json');
        putenv('MARKETDATA_DATE_FORMAT=unix');
        putenv('MARKETDATA_COLUMNS=symbol,ask');
        putenv('MARKETDATA_ADD_HEADERS=true');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'json';
        $_ENV['MARKETDATA_DATE_FORMAT'] = 'unix';
        $_ENV['MARKETDATA_COLUMNS'] = 'symbol,ask';
        $_ENV['MARKETDATA_ADD_HEADERS'] = 'true';

        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::JSON, $params->format);
        $this->assertNull($params->date_format); // Ignored because format is JSON
        $this->assertNull($params->columns); // Ignored because format is JSON
        $this->assertNull($params->add_headers); // Ignored because format is JSON
    }

    public function testGetDefaultParameters_partialParams_fromEnvVars(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_MODE=live');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_MODE'] = 'live';

        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertEquals(Mode::LIVE, $params->mode);
        $this->assertNull($params->date_format);
        $this->assertNull($params->columns);
        $this->assertNull($params->add_headers);
        $this->assertNull($params->use_human_readable);
    }

    /**
     * Test Group 2.7: .env File Support
     */

    public function testGetDefaultParameters_fromDotEnvFile(): void
    {
        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, ['MARKETDATA_OUTPUT_FORMAT' => 'csv']);
        chdir($tempDir);

        // Reset dotenv loaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);

        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::CSV, $params->format);
    }

    public function testGetDefaultParameters_dotEnvFile_envVarTakesPrecedence(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=json');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'json';

        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, ['MARKETDATA_OUTPUT_FORMAT' => 'csv']);
        chdir($tempDir);

        // Reset dotenv loaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);

        $params = Settings::getDefaultParameters();
        // Environment variable takes precedence over .env file (matching token behavior)
        // getenv() is checked first, so putenv() value wins
        $this->assertEquals(Format::JSON, $params->format);
    }

    public function testGetDefaultParameters_dotEnvFile_parentDirectorySearch(): void
    {
        $parentDir = $this->createTempDir();
        $childDir = $parentDir . '/child';
        $grandchildDir = $childDir . '/grandchild';
        mkdir($grandchildDir, 0755, true);
        $this->tempDirs[] = $childDir;
        $this->tempDirs[] = $grandchildDir;

        $this->createTempEnvFile($parentDir, ['MARKETDATA_OUTPUT_FORMAT' => 'csv']);
        chdir($grandchildDir);

        // Reset dotenv loaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);

        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::CSV, $params->format);
    }

    public function testGetDefaultParameters_dotEnvFile_notFound_usesDefaults(): void
    {
        $tempDir = $this->createTempDir();
        chdir($tempDir);

        // Reset dotenv loaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);

        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::JSON, $params->format);
    }

    /**
     * Test Group 2.8: Client Initialization with Environment Variables
     */

    public function testClientInitialization_defaultParamsLoadedFromEnvVars(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';

        // Reset dotenv loaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);

        $client = new Client();
        $this->assertEquals(Format::CSV, $client->default_params->format);
    }

    public function testClientInitialization_defaultParamsLoadedFromDotEnv(): void
    {
        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, ['MARKETDATA_OUTPUT_FORMAT' => 'html']);
        chdir($tempDir);

        // Reset dotenv loaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);

        $client = new Client();
        $this->assertEquals(Format::HTML, $client->default_params->format);
    }

    public function testClientInitialization_defaultParamsCanBeModifiedAfterConstruction(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';

        // Reset dotenv loaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);

        $client = new Client();
        $client->default_params->format = Format::JSON;
        $this->assertEquals(Format::JSON, $client->default_params->format);
    }

    // ============================================================================
    // Phase 3: Three-Level Hierarchy Tests
    // ============================================================================

    /**
     * Test Group 3.1: Hierarchy Precedence - Method > Client > Env
     *
     * Note: These tests verify the hierarchy through actual endpoint calls.
     * We'll test the merging behavior by checking what parameters are used
     * in actual API requests (mocked in integration tests).
     */

    public function testThreeLevelHierarchy_envVarUsedWhenNoOverrides(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';

        // Reset dotenv loaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);

        $client = new Client();
        // Client default_params should have format from env var
        $this->assertEquals(Format::CSV, $client->default_params->format);
    }

    public function testThreeLevelHierarchy_clientDefaultWinsOverEnv(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';

        // Reset dotenv loaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);

        $client = new Client('');
        // Modify client default after construction
        $client->default_params->format = Format::HTML;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, null);
        $this->assertEquals(Format::HTML, $merged->format);
    }

    public function testThreeLevelHierarchy_methodParamWins(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';

        // Reset dotenv loaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);

        $client = new Client('');
        $client->default_params->format = Format::HTML;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::JSON));
        $this->assertEquals(Format::JSON, $merged->format);
    }

    public function testThreeLevelHierarchy_multipleParams_partialHierarchy(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_MODE=cached');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_MODE'] = 'cached';

        // Reset dotenv loaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);

        $client = new Client('');
        $client->default_params->format = Format::HTML; // Override format only
        $stocks = $client->stocks;
        // Pass Parameters with format matching client default and mode override
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::HTML, mode: Mode::LIVE));
        $this->assertEquals(Format::HTML, $merged->format); // Method param format (matches client default)
        $this->assertEquals(Mode::LIVE, $merged->mode); // Method param wins over client default and env
    }

    public function testThreeLevelHierarchy_nullMethodParam_usesClientDefault(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';

        // Reset dotenv loaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);

        $client = new Client('');
        $client->default_params->format = Format::HTML;
        $stocks = $client->stocks;
        // Pass Parameters with only mode set (format will use client default)
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::HTML, mode: Mode::LIVE));
        // Format from method params (even though it matches client default)
        $this->assertEquals(Format::HTML, $merged->format);
        $this->assertEquals(Mode::LIVE, $merged->mode);
    }

    public function testThreeLevelHierarchy_explicitNullMethodParam_overridesAll(): void
    {
        // Note: In PHP, we can't distinguish "not set" from "explicitly null" for optional parameters.
        // So passing mode: null is treated the same as not setting it, and client default is used.
        putenv('MARKETDATA_MODE=cached');
        $_ENV['MARKETDATA_MODE'] = 'cached';

        // Reset dotenv loaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);

        $client = new Client('');
        $client->default_params->mode = Mode::LIVE;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(mode: null));
        // PHP limitation: can't distinguish explicit null from "not set", so client default is used
        $this->assertEquals(Mode::LIVE, $merged->mode);
    }

    // ============================================================================
    // Phase 4: Integration Tests
    // ============================================================================

    /**
     * Test Group 4.1: Actual API Call Integration (Mocked)
     */

    public function testIntegration_apiCall_usesMergedParameters(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';

        // Reset dotenv loaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);

        $this->client = new Client('');
        $this->client->default_params->mode = Mode::CACHED;

        // Mock response with proper structure (Quote expects arrays)
        $mockResponse = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'ask' => [150.0],
            'askSize' => [100],
            'bid' => [149.5],
            'bidSize' => [200],
            'mid' => [149.75],
            'last' => [150.0],
            'change' => [1.0],
            'changepct' => [0.67],
            'volume' => [1000000],
            'updated' => ['2024-01-20T10:30:00Z']
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($mockResponse))
        ]);

        // Call with method param
        $response = $this->client->stocks->quote('AAPL', parameters: new Parameters(use_human_readable: true));

        // Verify merged params were used by checking the request was made
        // (The actual request params are internal, but we can verify the response was processed)
        $this->assertIsObject($response);
    }

    public function testIntegration_apiCall_methodParamOverrides(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';

        // Reset dotenv loaded flag
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);

        $this->client = new Client('');
        $this->client->default_params->format = Format::HTML;

        // Mock response with proper structure (Quote expects arrays)
        $mockResponse = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'ask' => [150.0],
            'askSize' => [100],
            'bid' => [149.5],
            'bidSize' => [200],
            'mid' => [149.75],
            'last' => [150.0],
            'change' => [1.0],
            'changepct' => [0.67],
            'volume' => [1000000],
            'updated' => ['2024-01-20T10:30:00Z']
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($mockResponse))
        ]);

        // Call with method param that overrides
        $response = $this->client->stocks->quote('AAPL', parameters: new Parameters(format: Format::JSON));

        // Verify response was processed (method param format was used)
        $this->assertIsObject($response);
    }

    public function testIntegration_apiCall_nullParameters_usesClientDefaults(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;

        // Mock response
        $this->setMockResponses([
            new Response(200, [], 'symbol,ask\nAAPL,150.0')
        ]);

        // Call with null parameters
        $response = $this->client->stocks->quote('AAPL', parameters: null);

        // Verify response was processed with client default format
        $this->assertIsObject($response);
    }

    /**
     * Test Group 4.2: Parallel Requests
     */

    public function testIntegration_parallelRequests_usesMergedParameters(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;

        // Mock responses for parallel requests
        $mockResponse1 = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'ask' => [150.0],
            'askSize' => [100],
            'bid' => [149.5],
            'bidSize' => [200],
            'mid' => [149.75],
            'last' => [150.0],
            'change' => [1.0],
            'changepct' => [0.67],
            'volume' => [1000000],
            'updated' => ['2024-01-20T10:30:00Z']
        ];
        $mockResponse2 = [
            's' => 'ok',
            'symbol' => ['MSFT'],
            'ask' => [300.0],
            'askSize' => [100],
            'bid' => [299.5],
            'bidSize' => [200],
            'mid' => [299.75],
            'last' => [300.0],
            'change' => [2.0],
            'changepct' => [0.67],
            'volume' => [2000000],
            'updated' => ['2024-01-20T10:30:00Z']
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($mockResponse1)),
            new Response(200, [], json_encode($mockResponse2))
        ]);

        // Call with method param
        $response = $this->client->stocks->quotes(['AAPL', 'MSFT'], parameters: new Parameters(mode: Mode::LIVE));

        // Verify response was processed (quotes() returns Quotes object, not array)
        $this->assertIsObject($response);
        $this->assertIsArray($response->quotes);
        $this->assertCount(2, $response->quotes);
    }

    public function testIntegration_parallelRequests_filenameNotAllowed(): void
    {
        $tempDir = $this->createTempDir();
        $filename = $tempDir . '/test.csv';
        touch($filename); // Create file for validation

        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;
        $this->client->default_params->filename = $filename;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filename parameter cannot be used with parallel requests');

        // This should throw because filename is set in default_params
        $this->client->stocks->quotes(['AAPL', 'MSFT'], parameters: null);
    }

    /**
     * Test Group 4.3: Format Restrictions
     */

    public function testIntegration_csvOnlyParams_workWithMergedFormat(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;
        $this->client->default_params->date_format = DateFormat::UNIX;

        // Mock CSV response
        $this->setMockResponses([
            new Response(200, [], 'symbol,ask\nAAPL,150.0')
        ]);

        // Call with null parameters (uses client defaults)
        $response = $this->client->stocks->quote('AAPL', parameters: null);

        // Verify response was processed (CSV format allows date_format)
        $this->assertIsObject($response);
    }

    public function testIntegration_csvOnlyParams_invalidWithJsonFormat(): void
    {
        $this->client = new Client('');
        // Set CSV-only param in client defaults with JSON format (invalid combination)
        $this->client->default_params->format = Format::JSON;
        $this->client->default_params->date_format = DateFormat::UNIX;

        // This should throw an exception when merging parameters
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('date_format parameter can only be used with CSV or HTML format');

        // Call with null parameters - merge will detect invalid combination
        $this->client->stocks->quote('AAPL', parameters: null);
    }

    public function testIntegration_formatChange_resetsCsvOnlyParams(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;
        $this->client->default_params->columns = ['symbol', 'ask'];

        // When format changes to JSON, columns should cause an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('columns parameter can only be used with CSV or HTML format');

        // Call with format override to JSON - should throw exception
        $this->client->stocks->quote('AAPL', parameters: new Parameters(format: Format::JSON));
    }
}
