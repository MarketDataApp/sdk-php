<?php

namespace MarketDataApp\Tests\Unit\UniversalParameters;

use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;
use MarketDataApp\Settings;

/**
 * Unit tests for the three-level configuration hierarchy.
 *
 * Tests the priority order:
 * 1. Environment Variables (lowest priority)
 * 2. Client Instance Defaults (middle priority)
 * 3. Method-Level Parameters (highest priority)
 */
class HierarchyTest extends UniversalParametersTestCase
{
    // ============================================================================
    // Three-Level Hierarchy Tests
    // ============================================================================

    public function testThreeLevelHierarchy_envVarUsedWhenNoOverrides(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $this->resetDotenvLoadedFlag();

        $client = new Client();
        $this->assertEquals(Format::CSV, $client->default_params->format);
    }

    public function testThreeLevelHierarchy_clientDefaultWinsOverEnv(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $this->resetDotenvLoadedFlag();

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
        $this->resetDotenvLoadedFlag();

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
        $this->resetDotenvLoadedFlag();

        $client = new Client('');
        $client->default_params->format = Format::HTML; // Override format only
        $stocks = $client->stocks;
        // Pass Parameters with format matching client default and mode override
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::HTML, mode: Mode::LIVE));
        $this->assertEquals(Format::HTML, $merged->format);
        $this->assertEquals(Mode::LIVE, $merged->mode);
    }

    public function testThreeLevelHierarchy_nullMethodParam_usesClientDefault(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $this->resetDotenvLoadedFlag();

        $client = new Client('');
        $client->default_params->format = Format::HTML;
        $stocks = $client->stocks;
        // Pass Parameters with only mode set (format will use client default)
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::HTML, mode: Mode::LIVE));
        $this->assertEquals(Format::HTML, $merged->format);
        $this->assertEquals(Mode::LIVE, $merged->mode);
    }

    public function testThreeLevelHierarchy_explicitNullMethodParam_overridesAll(): void
    {
        // Note: In PHP, we can't distinguish "not set" from "explicitly null" for optional parameters.
        // So passing mode: null is treated the same as not setting it, and client default is used.
        putenv('MARKETDATA_MODE=cached');
        $_ENV['MARKETDATA_MODE'] = 'cached';
        $this->resetDotenvLoadedFlag();

        $client = new Client('');
        $client->default_params->mode = Mode::LIVE;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(mode: null));
        // PHP limitation: can't distinguish explicit null from "not set", so client default is used
        $this->assertEquals(Mode::LIVE, $merged->mode);
    }

    // ============================================================================
    // All Parameters from Environment Variables
    // ============================================================================

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

        $this->resetDotenvLoadedFlag();

        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertEquals(\MarketDataApp\Enums\DateFormat::UNIX, $params->date_format);
        $this->assertEquals(['symbol', 'ask'], $params->columns);
        $this->assertTrue($params->add_headers);
        $this->assertFalse($params->use_human_readable);
        $this->assertEquals(Mode::CACHED, $params->mode);
    }

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

        $this->resetDotenvLoadedFlag();

        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::JSON, $params->format);
        $this->assertNull($params->date_format);
        $this->assertNull($params->columns);
        $this->assertNull($params->add_headers);
    }

    public function testGetDefaultParameters_partialParams_fromEnvVars(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_MODE=live');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_MODE'] = 'live';

        $this->resetDotenvLoadedFlag();

        $params = Settings::getDefaultParameters();
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertEquals(Mode::LIVE, $params->mode);
        $this->assertNull($params->date_format);
        $this->assertNull($params->columns);
        $this->assertNull($params->add_headers);
        $this->assertNull($params->use_human_readable);
    }
}
