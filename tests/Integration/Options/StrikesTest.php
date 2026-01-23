<?php

namespace MarketDataApp\Tests\Integration\Options;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\Strikes;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;

/**
 * Integration tests for the Options Strikes endpoint.
 */
class StrikesTest extends OptionsTestCase
{
    /**
     * Test successful retrieval of option strikes.
     */
    public function testStrikes_success()
    {
        $response = $this->client->options->strikes(
            symbol: 'AAPL',
            date: '2023-01-03',
        );

        $this->assertInstanceOf(Strikes::class, $response);
        $this->assertInstanceOf(Carbon::class, $response->updated);
        $this->assertNotEmpty($response->dates);
        $this->assertNotEmpty(array_pop($response->dates));
    }

    /**
     * Test successful retrieval of option strikes in CSV format.
     */
    public function testStrikes_csv_success()
    {
        $response = $this->client->options->strikes(
            symbol: 'AAPL',
            date: '2023-01-03',
            parameters: new Parameters(format: Format::CSV),
        );

        $this->assertInstanceOf(Strikes::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test options strikes with human-readable format.
     */
    public function testStrikes_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->options->strikes(
            symbol: 'AAPL',
            date: '2024-01-03',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Strikes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->dates);
        $this->assertInstanceOf(Carbon::class, $response->updated);
    }

    /**
     * Test options strikes endpoint with CSV format and dateformat=spreadsheet.
     */
    public function testStrikes_csv_dateFormat_spreadsheet_returnsCsv(): void
    {
        $response = $this->client->options->strikes(
            symbol: 'AAPL',
            expiration: '2024-01-19',
            date: '2024-01-15',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::SPREADSHEET)
        );

        $this->assertInstanceOf(Strikes::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);
    }
}
