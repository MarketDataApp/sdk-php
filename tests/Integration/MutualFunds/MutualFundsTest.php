<?php

namespace MarketDataApp\Tests\Integration\MutualFunds;

use Carbon\Carbon;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\MutualFunds\Candle;
use MarketDataApp\Endpoints\Responses\MutualFunds\Candles;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use PHPUnit\Framework\TestCase;

/**
 * Class MutualFundsTest
 *
 * Integration tests for mutual funds-related functionality in the MarketDataApp.
 */
class MutualFundsTest extends TestCase
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
        // Use the same robust token detection as Settings class
        $token = getenv('MARKETDATA_TOKEN');
        if ($token === false || $token === '') {
            $token = $_ENV['MARKETDATA_TOKEN'] ?? $_SERVER['MARKETDATA_TOKEN'] ?? null;
        }
        if ($token === null || $token === '') {
            $this->markTestSkipped('MARKETDATA_TOKEN environment variable not set');
        }
        $client = new Client($token);
        $this->client = $client;
    }

    /**
     * Test successful candles retrieval for mutual funds.
     */
    public function testCandles_success()
    {
        $response = $this->client->mutual_funds->candles(
            symbol: 'VFINX',
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertNotEmpty($response->candles);

        // Verify each item in the response is an object of the correct type and has the correct values.
        $this->assertInstanceOf(Candle::class, $response->candles[0]);
        $this->assertEquals('double', gettype($response->candles[0]->close));
        $this->assertEquals('double', gettype($response->candles[0]->high));
        $this->assertEquals('double', gettype($response->candles[0]->low));
        $this->assertEquals('double', gettype($response->candles[0]->open));
        $this->assertInstanceOf(Carbon::class, $response->candles[0]->timestamp);
    }

    /**
     * Test successful candles retrieval for mutual funds in CSV format.
     */
    public function testCandles_csv_success()
    {
        $response = $this->client->mutual_funds->candles(
            symbol: 'VFINX',
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV)
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test mutual funds candles endpoint with CSV format and dateformat=unix.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException|ApiException
     */
    public function testCandles_csv_dateFormat_unix_returnsCsv(): void
    {
        $response = $this->client->mutual_funds->candles(
            symbol: 'VFINX',
            from: '2023-01-01',
            to: '2023-01-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);
    }

    /**
     * Test mutual funds candles endpoint with CSV format and dateformat=timestamp.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException|ApiException
     */
    public function testCandles_csv_dateFormat_timestamp_returnsCsv(): void
    {
        $response = $this->client->mutual_funds->candles(
            symbol: 'VFINX',
            from: '2023-01-01',
            to: '2023-01-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);
    }

    /**
     * Test mutual funds candles endpoint with CSV format and dateformat=spreadsheet.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException|ApiException
     */
    public function testCandles_csv_dateFormat_spreadsheet_returnsCsv(): void
    {
        $response = $this->client->mutual_funds->candles(
            symbol: 'VFINX',
            from: '2023-01-01',
            to: '2023-01-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::SPREADSHEET)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);
    }
}
