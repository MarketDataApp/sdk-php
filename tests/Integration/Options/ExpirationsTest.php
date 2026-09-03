<?php

namespace MarketDataApp\Tests\Integration\Options;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\Expirations;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;

/**
 * Integration tests for the Options Expirations endpoint.
 */
class ExpirationsTest extends OptionsTestCase
{
    /**
     * Test successful retrieval of option expirations.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testExpirations_success()
    {
        $response = $this->client->options->expirations('AAPL');

        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertSame('ok', $response->status);
        $this->assertNotEmpty($response->expirations);
        $this->assertInstanceOf(Carbon::class, $response->updated);
        $this->assertInstanceOf(Carbon::class, $response->expirations[0]);
    }

    /**
     * Test successful retrieval of option expirations in CSV format.
     */
    public function testExpirations_csv_success()
    {
        $response = $this->client->options->expirations(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test options expirations with human-readable format.
     */
    public function testExpirations_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->options->expirations(
            symbol: 'AAPL',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->expirations);
        $this->assertInstanceOf(Carbon::class, $response->expirations[0]);
        $this->assertInstanceOf(Carbon::class, $response->updated);
    }

    /**
     * Test options expirations endpoint with CSV format and dateformat=unix.
     */
    public function testExpirations_csv_dateFormat_unix_returnsCsv(): void
    {
        $response = $this->client->options->expirations(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Expirations::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);
    }
}
