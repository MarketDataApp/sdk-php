<?php

namespace MarketDataApp\Tests\Unit\UniversalParameters;

use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\Format;
use MarketDataApp\Settings;

/**
 * Unit tests for the Format universal parameter.
 *
 * Tests parameter merging, environment variable support, and validation
 * for the format parameter (JSON, CSV, HTML).
 */
class FormatTest extends UniversalParametersTestCase
{
    // ============================================================================
    // Parameter Merging Tests
    // ============================================================================

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

    // ============================================================================
    // Environment Variable Tests
    // ============================================================================

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

    // ============================================================================
    // Client Initialization Tests
    // ============================================================================

    public function testClientInitialization_defaultParamsLoadedFromEnvVars(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $this->resetDotenvLoadedFlag();

        $client = new Client();
        $this->assertEquals(Format::CSV, $client->default_params->format);
    }

    public function testClientInitialization_defaultParamsCanBeModifiedAfterConstruction(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $this->resetDotenvLoadedFlag();

        $client = new Client();
        $client->default_params->format = Format::JSON;
        $this->assertEquals(Format::JSON, $client->default_params->format);
    }

    // ============================================================================
    // Integration Tests (Mocked)
    // ============================================================================

    public function testIntegration_apiCall_methodParamOverrides(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $this->resetDotenvLoadedFlag();

        $this->client = new Client('');
        $this->client->default_params->format = Format::HTML;

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

        $response = $this->client->stocks->quote('AAPL', parameters: new Parameters(format: Format::JSON));
        $this->assertIsObject($response);
    }

    public function testIntegration_apiCall_nullParameters_usesClientDefaults(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;

        $this->setMockResponses([
            new Response(200, [], 'symbol,ask\nAAPL,150.0')
        ]);

        $response = $this->client->stocks->quote('AAPL', parameters: null);
        $this->assertIsObject($response);
    }
}
