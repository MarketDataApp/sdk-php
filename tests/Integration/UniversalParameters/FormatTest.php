<?php

namespace MarketDataApp\Tests\Integration\UniversalParameters;

use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Enums\Format;

/**
 * Integration tests for the Format universal parameter.
 *
 * Tests that the format parameter (JSON, CSV, HTML) works correctly
 * with the actual API across different endpoints.
 */
class FormatTest extends UniversalParametersTestCase
{
    public function testFormat_json_returnsJsonObject(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::JSON)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertFalse($response->isCsv());
        $this->assertEquals('ok', $response->status);
    }

    public function testFormat_csv_returnsCsvString(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());
        $this->assertNotEmpty($response->getCsv());
    }

    public function testFormat_csv_candles_returnsCsvString(): void
    {
        $response = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2024-01-02',
            to: '2024-01-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());
        $this->assertNotEmpty($response->getCsv());
    }
}
