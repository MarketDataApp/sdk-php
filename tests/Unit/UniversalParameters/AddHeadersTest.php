<?php

namespace MarketDataApp\Tests\Unit\UniversalParameters;

use InvalidArgumentException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\Format;
use MarketDataApp\Settings;

/**
 * Unit tests for the AddHeaders universal parameter.
 *
 * Tests parameter merging, environment variable support, and validation
 * for the add_headers parameter (boolean for CSV/HTML output).
 * Note: add_headers is only valid for CSV/HTML formats.
 */
class AddHeadersTest extends UniversalParametersTestCase
{
    // ============================================================================
    // Parameter Merging Tests
    // ============================================================================

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

    // ============================================================================
    // Environment Variable Tests
    // ============================================================================

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

    // ============================================================================
    // Format Restriction Tests
    // ============================================================================

    public function testIntegration_addHeaders_invalidWithJsonFormat(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;
        $this->client->default_params->add_headers = true;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('add_headers parameter can only be used with CSV or HTML format');

        $this->client->stocks->quote('AAPL', parameters: new Parameters(format: Format::JSON));
    }
}
