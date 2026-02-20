<?php

namespace MarketDataApp\Tests\Unit\UniversalParameters;

use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Settings;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for Universal Parameters tests.
 *
 * Provides shared setup and helper methods for testing the three-level
 * configuration hierarchy:
 * 1. Environment Variables (lowest priority)
 * 2. Client Instance Defaults (middle priority)
 * 3. Method-Level Parameters (highest priority)
 */
abstract class UniversalParametersTestCase extends TestCase
{
    use MockResponses;

    /**
     * Original environment variable values to restore after tests.
     */
    protected array $originalEnv = [];

    /**
     * Client instance for testing.
     */
    protected ?Client $client = null;

    /**
     * Temporary directories created during tests.
     */
    protected array $tempDirs = [];

    /**
     * Temporary files created during tests.
     */
    protected array $tempFiles = [];

    /**
     * Original working directory.
     */
    protected string $originalCwd;

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
    protected function saveEnvironmentState(): void
    {
        $envVars = [
            'MARKETDATA_OUTPUT_FORMAT',
            'MARKETDATA_DATE_FORMAT',
            'MARKETDATA_COLUMNS',
            'MARKETDATA_ADD_HEADERS',
            'MARKETDATA_USE_HUMAN_READABLE',
            'MARKETDATA_MODE',
            'MARKETDATA_TOKEN',
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
    protected function restoreEnvironmentState(): void
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
    protected function clearUniversalParamEnvVars(): void
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

        $this->resetDotenvLoadedFlag();
    }

    /**
     * Reset Settings dotenv loaded flag by reflection.
     */
    protected function resetDotenvLoadedFlag(): void
    {
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);
    }

    /**
     * Create a temporary directory.
     *
     * @return string Path to temporary directory.
     */
    protected function createTempDir(): string
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
    protected function createTempEnvFile(string $dir, array $content): string
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
    protected function cleanupTempFiles(): void
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

    /**
     * Helper method to call protected mergeParameters method via reflection.
     *
     * @param \MarketDataApp\Endpoints\Stocks $stocks Stocks endpoint instance.
     * @param Parameters|null $methodParams Method-level parameters.
     *
     * @return Parameters Merged parameters.
     */
    protected function callMergeParameters(\MarketDataApp\Endpoints\Stocks $stocks, ?Parameters $methodParams): Parameters
    {
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('mergeParameters');
        return $method->invoke($stocks, $methodParams);
    }
}
