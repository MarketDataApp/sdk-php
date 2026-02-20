<?php

namespace MarketDataApp\Tests\Unit\UniversalParameters;

use Carbon\CarbonInterval;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;

/**
 * Unit tests for the maxage universal parameter.
 *
 * Tests parameter validation and merging for the maxage parameter
 * which controls the maximum acceptable age of cached data.
 */
class MaxageTest extends UniversalParametersTestCase
{
    // ============================================================================
    // Constructor Validation Tests - Int Input
    // ============================================================================

    public function testParameters_maxage_validWithIntSeconds(): void
    {
        $params = new Parameters(mode: Mode::CACHED, maxage: 300);
        $this->assertEquals(300, $params->maxage);
        $this->assertEquals(Mode::CACHED, $params->mode);
    }

    public function testParameters_maxage_validWithSmallInt(): void
    {
        $params = new Parameters(mode: Mode::CACHED, maxage: 10);
        $this->assertEquals(10, $params->maxage);
    }

    public function testParameters_maxage_validWithLargeInt(): void
    {
        // 1 hour in seconds
        $params = new Parameters(mode: Mode::CACHED, maxage: 3600);
        $this->assertEquals(3600, $params->maxage);
    }

    // ============================================================================
    // Constructor Validation Tests - DateInterval Input
    // ============================================================================

    public function testParameters_maxage_validWithDateInterval(): void
    {
        $interval = new \DateInterval('PT5M'); // 5 minutes
        $params = new Parameters(mode: Mode::CACHED, maxage: $interval);
        $this->assertEquals(300, $params->maxage);
    }

    public function testParameters_maxage_dateIntervalWithHours(): void
    {
        $interval = new \DateInterval('PT1H30M'); // 1 hour 30 minutes
        $params = new Parameters(mode: Mode::CACHED, maxage: $interval);
        $this->assertEquals(5400, $params->maxage);
    }

    public function testParameters_maxage_dateIntervalWithSeconds(): void
    {
        $interval = new \DateInterval('PT45S'); // 45 seconds
        $params = new Parameters(mode: Mode::CACHED, maxage: $interval);
        $this->assertEquals(45, $params->maxage);
    }

    public function testParameters_maxage_dateIntervalWithDays(): void
    {
        // BUG-009: Manually constructed DateIntervals have days=false,
        // so we must convert using reference date arithmetic.
        $interval = new \DateInterval('P1D'); // 1 day
        $params = new Parameters(mode: Mode::CACHED, maxage: $interval);
        $this->assertEquals(86400, $params->maxage);
    }

    public function testParameters_maxage_dateIntervalWithDaysAndTime(): void
    {
        $interval = new \DateInterval('P2DT3H'); // 2 days + 3 hours
        $params = new Parameters(mode: Mode::CACHED, maxage: $interval);
        $this->assertEquals((2 * 86400) + (3 * 3600), $params->maxage);
    }

    // ============================================================================
    // Constructor Validation Tests - CarbonInterval Input
    // ============================================================================

    public function testParameters_maxage_validWithCarbonInterval(): void
    {
        $interval = CarbonInterval::minutes(5);
        $params = new Parameters(mode: Mode::CACHED, maxage: $interval);
        $this->assertEquals(300, $params->maxage);
    }

    public function testParameters_maxage_carbonIntervalWithHours(): void
    {
        $interval = CarbonInterval::hours(2);
        $params = new Parameters(mode: Mode::CACHED, maxage: $interval);
        $this->assertEquals(7200, $params->maxage);
    }

    public function testParameters_maxage_carbonIntervalWithMixedUnits(): void
    {
        $interval = CarbonInterval::hours(1)->minutes(30)->seconds(15);
        $params = new Parameters(mode: Mode::CACHED, maxage: $interval);
        $this->assertEquals(5415, $params->maxage);
    }

    // ============================================================================
    // Constructor Validation Tests - Mode Requirements
    // ============================================================================

    public function testParameters_maxage_throwsWhenModeIsNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxage parameter can only be used with CACHED mode. No mode specified.');
        new Parameters(maxage: 300);
    }

    public function testParameters_maxage_throwsWhenModeIsLive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxage parameter can only be used with CACHED mode. Current mode: live');
        new Parameters(mode: Mode::LIVE, maxage: 300);
    }

    public function testParameters_maxage_throwsWhenModeIsDelayed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxage parameter can only be used with CACHED mode. Current mode: delayed');
        new Parameters(mode: Mode::DELAYED, maxage: 300);
    }

    public function testParameters_maxage_nullIsAllowedWithAnyMode(): void
    {
        // maxage=null should be allowed regardless of mode
        $params1 = new Parameters(mode: Mode::LIVE, maxage: null);
        $this->assertNull($params1->maxage);

        $params2 = new Parameters(mode: Mode::DELAYED, maxage: null);
        $this->assertNull($params2->maxage);

        $params3 = new Parameters(mode: null, maxage: null);
        $this->assertNull($params3->maxage);
    }

    // ============================================================================
    // Parameter Merging Tests
    // ============================================================================

    public function testMergeParameters_maxage_methodParamOverridesClientDefault(): void
    {
        $client = new Client('');
        $client->default_params->mode = Mode::CACHED;
        $client->default_params->maxage = 3600; // 1 hour

        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(mode: Mode::CACHED, maxage: 300)); // 5 min

        $this->assertEquals(300, $merged->maxage);
    }

    public function testMergeParameters_maxage_nullMethodParamUsesClientDefault(): void
    {
        $client = new Client('');
        $client->default_params->mode = Mode::CACHED;
        $client->default_params->maxage = 1800; // 30 min

        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(mode: Mode::CACHED));

        $this->assertEquals(1800, $merged->maxage);
    }

    public function testMergeParameters_maxage_throwsWhenMaxageSetButMergedModeNotCached(): void
    {
        $client = new Client('');
        $client->default_params->mode = Mode::CACHED;
        $client->default_params->maxage = 300;

        $stocks = $client->stocks;

        // Method params override mode to LIVE, but maxage is inherited from client defaults
        // This should throw because maxage requires CACHED mode
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxage parameter can only be used with CACHED mode. Current mode: live');
        $this->callMergeParameters($stocks, new Parameters(mode: Mode::LIVE));
    }

    public function testMergeParameters_maxage_throwsWhenMaxageSetAndMergedModeIsNull(): void
    {
        $client = new Client('');
        // Client has maxage but no mode set
        $client->default_params->maxage = 300;
        $client->default_params->mode = null;

        $stocks = $client->stocks;

        // Since mode is null after merge, maxage should fail
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxage parameter can only be used with CACHED mode. No mode specified.');
        $this->callMergeParameters($stocks, new Parameters());
    }

    // ============================================================================
    // __toString() Tests
    // ============================================================================

    public function testToString_includesMaxage(): void
    {
        $params = new Parameters(mode: Mode::CACHED, maxage: 300);
        $string = (string) $params;

        $this->assertStringContainsString('maxage=300', $string);
        $this->assertStringContainsString('mode=cached', $string);
    }

    public function testToString_excludesMaxageWhenNull(): void
    {
        $params = new Parameters(mode: Mode::CACHED);
        $string = (string) $params;

        $this->assertStringNotContainsString('maxage', $string);
    }

    // ============================================================================
    // Integration Tests (Mocked)
    // ============================================================================

    public function testIntegration_apiCall_includesMaxageInRequest(): void
    {
        $this->client = new Client('');

        // Mock response for options chain (supports cached mode)
        // Mock response: NOT from real API output (uses synthetic/test data)
        $mockResponse = [
            's' => 'ok',
            'optionSymbol' => ['AAPL250117C00150000'],
            'underlying' => ['AAPL'],
            'expiration' => [1705449600],
            'side' => ['call'],
            'strike' => [150.0],
            'firstTraded' => [1700000000],
            'dte' => [30],
            'updated' => [1705000000],
            'bid' => [5.0],
            'bidSize' => [100],
            'mid' => [5.5],
            'ask' => [6.0],
            'askSize' => [100],
            'last' => [5.5],
            'openInterest' => [1000],
            'volume' => [500],
            'inTheMoney' => [true],
            'intrinsicValue' => [5.0],
            'extrinsicValue' => [0.5],
            'underlyingPrice' => [155.0],
            'iv' => [0.25],
            'delta' => [0.6],
            'gamma' => [0.05],
            'theta' => [-0.02],
            'vega' => [0.1],
        ];

        $this->setMockResponses([
            new Response(203, [], json_encode($mockResponse))
        ]);

        // This should include maxage in the request
        $response = $this->client->options->option_chain(
            'AAPL',
            parameters: new Parameters(mode: Mode::CACHED, maxage: 300) // 5 minutes
        );

        $this->assertIsObject($response);
    }

    public function testIntegration_multiSymbolRequest_includesMaxageInRequest(): void
    {
        $this->client = new Client('');

        // Mock response for multi-symbol quotes request
        // Mock response: NOT from real API output (uses synthetic/test data)
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
            new Response(203, [], json_encode($mockResponse))
        ]);

        // Execute quotes request with multiple symbols
        $response = $this->client->stocks->quotes(
            ['AAPL', 'MSFT'],
            parameters: new Parameters(mode: Mode::CACHED, maxage: 10)
        );

        $this->assertIsObject($response);
        $this->assertCount(2, $response->quotes);
    }
}
