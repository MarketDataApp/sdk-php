<?php

namespace MarketDataApp\Tests\Unit\UniversalParameters;

use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;

/**
 * Unit tests for Client default_params property.
 *
 * Tests property existence, initialization, modification,
 * and backward compatibility for the client-level default parameters.
 */
class DefaultParamsTest extends UniversalParametersTestCase
{
    // ============================================================================
    // Property Existence and Initialization Tests
    // ============================================================================

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

    // ============================================================================
    // Complex Merging Scenarios
    // ============================================================================

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

    // ============================================================================
    // Backward Compatibility Tests
    // ============================================================================

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
}
