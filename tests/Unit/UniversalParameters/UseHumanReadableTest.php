<?php

namespace MarketDataApp\Tests\Unit\UniversalParameters;

use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Settings;

/**
 * Unit tests for the UseHumanReadable universal parameter.
 *
 * Tests parameter merging, environment variable support, and validation
 * for the use_human_readable parameter (boolean for JSON output with spaces in keys).
 */
class UseHumanReadableTest extends UniversalParametersTestCase
{
    // ============================================================================
    // Parameter Merging Tests
    // ============================================================================

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

    // ============================================================================
    // Environment Variable Tests
    // ============================================================================

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
}
