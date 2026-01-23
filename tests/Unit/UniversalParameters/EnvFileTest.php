<?php

namespace MarketDataApp\Tests\Unit\UniversalParameters;

use MarketDataApp\Client;
use MarketDataApp\Enums\Format;
use MarketDataApp\Settings;

/**
 * Unit tests for .env file support in universal parameters.
 *
 * Tests loading parameters from .env files, parent directory search,
 * and precedence over environment variables.
 */
class EnvFileTest extends UniversalParametersTestCase
{
    // ============================================================================
    // .env File Support Tests
    // ============================================================================

    public function testGetDefaultParameters_fromDotEnvFile(): void
    {
        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, ['MARKETDATA_OUTPUT_FORMAT' => 'csv']);
        chdir($tempDir);
        $this->resetDotenvLoadedFlag();

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
        $this->resetDotenvLoadedFlag();

        $params = Settings::getDefaultParameters();
        // Environment variable takes precedence over .env file
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
        $this->resetDotenvLoadedFlag();

        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::CSV, $params->format);
    }

    public function testGetDefaultParameters_dotEnvFile_notFound_usesDefaults(): void
    {
        $tempDir = $this->createTempDir();
        chdir($tempDir);
        $this->resetDotenvLoadedFlag();

        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::JSON, $params->format);
    }

    // ============================================================================
    // Client Initialization with .env File
    // ============================================================================

    public function testClientInitialization_defaultParamsLoadedFromDotEnv(): void
    {
        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, ['MARKETDATA_OUTPUT_FORMAT' => 'html']);
        chdir($tempDir);
        $this->resetDotenvLoadedFlag();

        $client = new Client();
        $this->assertEquals(Format::HTML, $client->default_params->format);
    }
}
