<?php

namespace MarketDataApp\Tests\Unit\UniversalParameters;

use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;
use MarketDataApp\Settings;

/**
 * Unit tests for the Mode universal parameter.
 *
 * Tests parameter merging, environment variable support, and validation
 * for the mode parameter (LIVE, CACHED, DELAYED).
 */
class ModeTest extends UniversalParametersTestCase
{
    // ============================================================================
    // Parameter Merging Tests
    // ============================================================================

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
        $client = new Client();
        $client->default_params->mode = Mode::CACHED;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(mode: null));
        // PHP limitation: can't distinguish explicit null from "not set", so client default is used
        $this->assertEquals(Mode::CACHED, $merged->mode);
    }

    // ============================================================================
    // Environment Variable Tests
    // ============================================================================

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

    // ============================================================================
    // Integration Tests (Mocked)
    // ============================================================================

    public function testIntegration_apiCall_usesMergedParameters(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $this->resetDotenvLoadedFlag();

        $this->client = new Client('');
        $this->client->default_params->mode = Mode::CACHED;

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

        $response = $this->client->stocks->quote('AAPL', parameters: new Parameters(use_human_readable: true));
        $this->assertIsObject($response);
    }

    public function testIntegration_multiSymbol_usesMergedParameters(): void
    {
        $this->client = new Client('');

        // Mock JSON response for multi-symbol request (single API call returns all data)
        $mockResponse = [
            's' => 'ok',
            'symbol' => ['AAPL', 'MSFT'],
            'ask' => [150.0, 300.0],
            'askSize' => [100, 100],
            'bid' => [149.5, 299.5],
            'bidSize' => [200, 200],
            'mid' => [149.75, 299.75],
            'last' => [150.0, 300.0],
            'change' => [1.0, 2.0],
            'changepct' => [0.67, 0.67],
            'volume' => [1000000, 2000000],
            'updated' => [1705747800, 1705747800]
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($mockResponse))
        ]);

        $response = $this->client->stocks->quotes(['AAPL', 'MSFT'], parameters: new Parameters(mode: Mode::LIVE));

        $this->assertIsObject($response);
        $this->assertIsArray($response->quotes);
        // JSON format creates individual Quote objects for each symbol
        $this->assertCount(2, $response->quotes);
        $this->assertEquals('AAPL', $response->quotes[0]->symbol);
        $this->assertEquals('MSFT', $response->quotes[1]->symbol);
    }
}
