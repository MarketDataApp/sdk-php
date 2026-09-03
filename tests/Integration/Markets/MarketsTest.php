<?php

namespace MarketDataApp\Tests\Integration\Markets;

use Carbon\Carbon;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Markets\Status;
use MarketDataApp\Endpoints\Responses\Markets\Statuses;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Tests\Integration\TestCase;

/**
 * Integration tests for Markets endpoints.
 */
class MarketsTest extends TestCase
{
    /**
     * @var Client The client instance used for testing.
     */
    private Client $client;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        $this->client = new Client($this->requireMarketDataToken());
    }

    /**
     * Test markets status with human-readable format.
     * Verifies that the API returns human-readable JSON keys with spaces.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testStatus_humanReadable_returnsHumanReadableKeys()
    {
        $from = Carbon::now('America/New_York')->subMonthNoOverflow()->startOfMonth();
        $to = $from->copy()->addDays(7);
        $response = $this->client->markets->status(
            from: $from->toDateString(),
            to: $to->toDateString(),
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Statuses::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->statuses);
        $dates = array_map(fn (Status $status) => $status->date->toDateString(), $response->statuses);
        $this->assertSame($from->toDateString(), $dates[0]);
        $this->assertSame($to->toDateString(), $dates[array_key_last($dates)]);
        $this->assertInstanceOf(Status::class, $response->statuses[0]);
        $this->assertInstanceOf(Carbon::class, $response->statuses[0]->date);
        $this->assertTrue(in_array($response->statuses[0]->status, ['open', 'closed']));
    }

    /**
     * Test markets status endpoint with CSV format and dateformat=unix.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException|ApiException
     */
    public function testStatus_csv_dateFormat_unix_returnsCsv(): void
    {
        $response = $this->client->markets->status(
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Statuses::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);
    }

    /**
     * Test markets status endpoint with CSV format and dateformat=timestamp.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException|ApiException
     */
    public function testStatus_csv_dateFormat_timestamp_returnsCsv(): void
    {
        $response = $this->client->markets->status(
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP)
        );

        $this->assertInstanceOf(Statuses::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);
    }

    /**
     * Test markets status endpoint with CSV format and dateformat=spreadsheet.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException|ApiException
     */
    public function testStatus_csv_dateFormat_spreadsheet_returnsCsv(): void
    {
        $response = $this->client->markets->status(
            from: '2023-01-01',
            to: '2023-01-05',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::SPREADSHEET)
        );

        $this->assertInstanceOf(Statuses::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);
    }
}
