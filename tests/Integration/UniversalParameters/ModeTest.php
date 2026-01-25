<?php

namespace MarketDataApp\Tests\Integration\UniversalParameters;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Enums\Mode;

/**
 * Integration tests for the Mode universal parameter.
 *
 * Tests that the mode parameter (LIVE, CACHED, DELAYED) works correctly
 * with the actual API.
 */
class ModeTest extends UniversalParametersTestCase
{
    public function testMode_live_returnsValidQuote(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(mode: Mode::LIVE)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('double', gettype($response->bid));
        $this->assertInstanceOf(Carbon::class, $response->updated);
    }

    public function testMode_cached_returnsValidQuote(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(mode: Mode::CACHED)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('double', gettype($response->bid));
    }

    public function testMode_delayed_returnsValidQuote(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(mode: Mode::DELAYED)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('double', gettype($response->bid));
    }
}
