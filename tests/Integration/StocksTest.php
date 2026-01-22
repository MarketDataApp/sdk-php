<?php

namespace MarketDataApp\Tests\Integration;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\BulkCandles;
use MarketDataApp\Endpoints\Responses\Stocks\Candle;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Endpoints\Responses\Stocks\Earnings;
use MarketDataApp\Endpoints\Responses\Stocks\News;
use MarketDataApp\Endpoints\Responses\Stocks\Prices;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Endpoints\Responses\Stocks\Quotes;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Exceptions\UnauthorizedException;
use PHPUnit\Framework\TestCase;

/**
 * Class StocksTest
 *
 * Integration tests for stocks-related functionality in the MarketDataApp.
 * This class tests various API endpoints related to stocks, including
 * candles, quotes, bulk quotes, and earnings data.
 */
class StocksTest extends TestCase
{

    /**
     * @var Client The client instance used for testing.
     */
    private Client $client;

    /**
     * Set up the test environment.
     * Initializes a new Client instance with the API token.
     */
    protected function setUp(): void
    {
        error_reporting(E_ALL);
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
     * Test successful retrieval of stock candles.
     *
     * @throws GuzzleException|ApiException
     */
    public function testCandles_success()
    {
        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D'
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertNotEmpty($response->candles);

        $this->assertInstanceOf(Candle::class, $response->candles[0]);
        $this->assertEquals('double', gettype($response->candles[0]->close));
        $this->assertEquals('double', gettype($response->candles[0]->high));
        $this->assertEquals('double', gettype($response->candles[0]->low));
        $this->assertEquals('double', gettype($response->candles[0]->open));
        $this->assertEquals('integer', gettype($response->candles[0]->volume));
        $this->assertInstanceOf(Carbon::class, $response->candles[0]->timestamp);
    }

    /**
     * Test successful retrieval of stock candles in CSV format.
     */
    public function testCandles_csv_success()
    {
        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test successful retrieval of bulk stock candles.
     *
     * @throws GuzzleException|ApiException
     */
    public function testBulkCandles_success()
    {
        $response = $this->client->stocks->bulkCandles(
            symbols: ["AAPL"],
            resolution: 'D'
        );

        $this->assertInstanceOf(BulkCandles::class, $response);
        $this->assertNotEmpty($response->candles);

        $this->assertInstanceOf(Candle::class, $response->candles[0]);
        $this->assertEquals('double', gettype($response->candles[0]->close));
        $this->assertEquals('double', gettype($response->candles[0]->high));
        $this->assertEquals('double', gettype($response->candles[0]->low));
        $this->assertEquals('double', gettype($response->candles[0]->open));
        $this->assertEquals('integer', gettype($response->candles[0]->volume));
        $this->assertInstanceOf(Carbon::class, $response->candles[0]->timestamp);
    }

    /**
     * Test successful retrieval of bulk stock candles in CSV format.
     *
     * @throws GuzzleException|ApiException
     */
    public function testBulkCandles_csv_success()
    {
        $response = $this->client->stocks->bulkCandles(
            symbols: ["AAPL"],
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(BulkCandles::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test successful retrieval of a stock quote.
     */
    public function testQuote_success()
    {
        $response = $this->client->stocks->quote('AAPL');

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('string', gettype($response->status));
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('integer', gettype($response->ask_size));
        $this->assertEquals('double', gettype($response->bid));
        $this->assertEquals('integer', gettype($response->bid_size));
        $this->assertEquals('double', gettype($response->mid));
        $this->assertEquals('double', gettype($response->last));
        $this->assertTrue(in_array(gettype($response->change), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->change_percent), ['double', 'NULL']));
        $this->assertNull($response->fifty_two_week_high);
        $this->assertNull($response->fifty_two_week_low);
        $this->assertEquals('integer', gettype($response->volume));
        $this->assertInstanceOf(Carbon::class, $response->updated);
    }

    /**
     * Test successful retrieval of a stock quote in CSV format.
     */
    public function testQuote_csv_success()
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test quote endpoint with CSV format and columns parameter (single column).
     * Verifies that the CSV response contains only the requested column.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_columns_singleColumn_returnsFilteredCsv(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(
                format: Format::CSV,
                columns: ['symbol']
            )
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        $lines = explode("\n", trim($csv));
        $headerRow = str_getcsv($lines[0], ',', '"', '\\');

        // Verify only requested columns are present
        $this->assertEquals(['symbol'], $headerRow);

        // Verify data row exists and has correct number of columns
        if (count($lines) > 1) {
            $dataRow = str_getcsv($lines[1], ',', '"', '\\');
            $this->assertCount(1, $dataRow);
            $this->assertEquals('AAPL', $dataRow[0]);
        }
    }

    /**
     * Test quote endpoint with CSV format and columns parameter (multiple columns).
     * Verifies that the CSV response contains only the requested columns in the correct order.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_columns_multipleColumns_returnsFilteredCsv(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(
                format: Format::CSV,
                columns: ['symbol', 'ask', 'bid', 'last']
            )
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        $lines = explode("\n", trim($csv));
        $headerRow = str_getcsv($lines[0], ',', '"', '\\');

        // Verify only requested columns are present in the correct order
        $this->assertEquals(['symbol', 'ask', 'bid', 'last'], $headerRow);

        // Verify data row exists and has correct number of columns
        if (count($lines) > 1) {
            $dataRow = str_getcsv($lines[1], ',', '"', '\\');
            $this->assertCount(4, $dataRow);
            $this->assertEquals('AAPL', $dataRow[0]);
        }
    }

    /**
     * Test quote endpoint with CSV format and columns parameter verifies column order.
     * Verifies that columns appear in the order specified in the request.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_columns_verifiesColumnOrder(): void
    {
        // Test with different column order
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(
                format: Format::CSV,
                columns: ['bid', 'ask', 'symbol']
            )
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        $lines = explode("\n", trim($csv));
        $headerRow = str_getcsv($lines[0], ',', '"', '\\');

        // Verify columns appear in the exact order specified
        $this->assertEquals(['bid', 'ask', 'symbol'], $headerRow);
    }

    /**
     * Test successful retrieval of multiple stock quotes.
     */
    public function testQuotes_success()
    {
        $response = $this->client->stocks->quotes(['AAPL']);

        $this->assertInstanceOf(Quote::class, $response->quotes[0]);
        $this->assertEquals('string', gettype($response->quotes[0]->status));
        $this->assertEquals('string', gettype($response->quotes[0]->symbol));
        $this->assertEquals('double', gettype($response->quotes[0]->ask));
        $this->assertEquals('integer', gettype($response->quotes[0]->ask_size));
        $this->assertEquals('double', gettype($response->quotes[0]->bid));
        $this->assertEquals('integer', gettype($response->quotes[0]->bid_size));
        $this->assertEquals('double', gettype($response->quotes[0]->mid));
        $this->assertEquals('double', gettype($response->quotes[0]->last));
        $this->assertTrue(in_array(gettype($response->quotes[0]->change), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->quotes[0]->change_percent), ['double', 'NULL']));
        $this->assertNull($response->quotes[0]->fifty_two_week_high);
        $this->assertNull($response->quotes[0]->fifty_two_week_low);
        $this->assertEquals('integer', gettype($response->quotes[0]->volume));
        $this->assertInstanceOf(Carbon::class, $response->quotes[0]->updated);
    }

    /**
     * Test successful retrieval of earnings data.
     */
    public function testEarnings_success()
    {
        $response = $this->client->stocks->earnings(symbol: 'AAPL', from: '2024-01-01');

        $this->assertInstanceOf(Earnings::class, $response);
        $this->assertNotEmpty($response->earnings);

        $this->assertEquals('string', gettype($response->status));
        $this->assertEquals('string', gettype($response->earnings[0]->symbol));
        $this->assertEquals('integer', gettype($response->earnings[0]->fiscal_year));
        $this->assertEquals('integer', gettype($response->earnings[0]->fiscal_quarter));
        $this->assertInstanceOf(Carbon::class, $response->earnings[0]->date);
        $this->assertInstanceOf(Carbon::class, $response->earnings[0]->report_date);
        $this->assertEquals('string', gettype($response->earnings[0]->report_time));
        // Currency may be null for future/estimated earnings reports
        $this->assertTrue(in_array(gettype($response->earnings[0]->currency), ['string', 'NULL']));
        $this->assertEquals('double', gettype($response->earnings[0]->reported_eps));
        $this->assertEquals('double', gettype($response->earnings[0]->estimated_eps));
        $this->assertEquals('double', gettype($response->earnings[0]->surprise_eps));
        $this->assertEquals('double', gettype($response->earnings[0]->surprise_eps_pct));
        $this->assertInstanceOf(Carbon::class, $response->earnings[0]->updated);
    }

    /**
     * Test successful retrieval of earnings data in CSV format.
     */
    public function testEarnings_csv_success()
    {
        $response = $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2024-01-01',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Earnings::class, $response);
        $this->assertNotEmpty($response->getCsv());
    }

    /**
     * Test SPY quote with no token throws UnauthorizedException.
     *
     * @return void
     */
    public function testQuote_noToken_throwsUnauthorizedException()
    {
        $client = new Client('');
        
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionCode(401);
        
        try {
            $client->stocks->quote('SPY');
        } catch (UnauthorizedException $e) {
            $this->assertEquals(401, $e->getCode());
            $this->assertNotNull($e->getResponse());
            $this->assertEquals(401, $e->getResponse()->getStatusCode());
            throw $e;
        }
    }

    /**
     * Test stocks quote with human-readable format.
     * Verifies that the API returns human-readable JSON keys with spaces.
     */
    public function testQuote_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->stocks->quote(
            'AAPL',
            false,
            new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('integer', gettype($response->ask_size));
        $this->assertEquals('double', gettype($response->bid));
        $this->assertEquals('integer', gettype($response->bid_size));
        $this->assertEquals('double', gettype($response->mid));
        $this->assertEquals('double', gettype($response->last));
        $this->assertTrue(in_array(gettype($response->change), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->change_percent), ['double', 'NULL']));
        $this->assertEquals('integer', gettype($response->volume));
        $this->assertInstanceOf(Carbon::class, $response->updated);
    }

    /**
     * Test stocks quote with human_readable=false.
     * Verifies that the API returns regular JSON keys.
     */
    public function testQuote_humanReadableFalse_returnsRegularKeys()
    {
        $response = $this->client->stocks->quote(
            'AAPL',
            false,
            new Parameters(use_human_readable: false)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('integer', gettype($response->ask_size));
    }

    /**
     * Test stocks quotes (parallel) with human-readable format.
     * Verifies that the API returns human-readable JSON keys for parallel requests.
     */
    public function testQuotes_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->stocks->quotes(
            ['AAPL'],
            false,
            new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertNotEmpty($response->quotes);
        $this->assertInstanceOf(Quote::class, $response->quotes[0]);
        $this->assertEquals('ok', $response->quotes[0]->status);
        $this->assertEquals('string', gettype($response->quotes[0]->symbol));
        $this->assertEquals('double', gettype($response->quotes[0]->ask));
    }

    /**
     * Test stocks candles with human-readable format.
     * Verifies that the API returns human-readable JSON keys with spaces.
     */
    public function testCandles_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2024-01-01',
            to: '2024-01-05',
            resolution: 'D',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->candles);
        $this->assertInstanceOf(Candle::class, $response->candles[0]);
        $this->assertEquals('double', gettype($response->candles[0]->open));
        $this->assertEquals('double', gettype($response->candles[0]->high));
        $this->assertEquals('double', gettype($response->candles[0]->low));
        $this->assertEquals('double', gettype($response->candles[0]->close));
        $this->assertEquals('integer', gettype($response->candles[0]->volume));
        $this->assertInstanceOf(Carbon::class, $response->candles[0]->timestamp);
    }

    /**
     * Test stocks bulkCandles with human-readable format.
     * Verifies that the API returns human-readable JSON keys with spaces.
     */
    public function testBulkCandles_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->stocks->bulkCandles(
            symbols: ["AAPL"],
            resolution: 'D',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(BulkCandles::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->candles);
        $this->assertInstanceOf(Candle::class, $response->candles[0]);
        $this->assertEquals('double', gettype($response->candles[0]->open));
        $this->assertEquals('double', gettype($response->candles[0]->close));
    }

    /**
     * Test stocks earnings with human-readable format.
     * Verifies that the API returns human-readable JSON keys with spaces.
     */
    public function testEarnings_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2024-01-01',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Earnings::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->earnings);
        $this->assertEquals('string', gettype($response->earnings[0]->symbol));
        $this->assertEquals('integer', gettype($response->earnings[0]->fiscal_year));
        $this->assertEquals('integer', gettype($response->earnings[0]->fiscal_quarter));
        $this->assertInstanceOf(Carbon::class, $response->earnings[0]->date);
    }

    /**
     * Test stocks news with human-readable format.
     * Verifies that the API returns human-readable JSON keys (mixed format).
     */
    public function testNews_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->stocks->news(
            symbol: 'AAPL',
            from: '2024-01-01',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(News::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('string', gettype($response->headline));
        $this->assertEquals('string', gettype($response->content));
        $this->assertEquals('string', gettype($response->source));
        $this->assertInstanceOf(Carbon::class, $response->publication_date);
    }

    /**
     * Test stocks quote with mode=LIVE.
     * Verifies that the API accepts and processes the mode parameter with LIVE value.
     */
    public function testQuote_modeLive_success()
    {
        $response = $this->client->stocks->quote(
            'AAPL',
            false,
            new Parameters(mode: Mode::LIVE)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('integer', gettype($response->ask_size));
        $this->assertEquals('double', gettype($response->bid));
        $this->assertEquals('integer', gettype($response->bid_size));
        $this->assertEquals('double', gettype($response->mid));
        $this->assertEquals('double', gettype($response->last));
    }

    /**
     * Test stocks quote with mode=CACHED.
     * Verifies that the API accepts and processes the mode parameter with CACHED value.
     */
    public function testQuote_modeCached_success()
    {
        $response = $this->client->stocks->quote(
            'AAPL',
            false,
            new Parameters(mode: Mode::CACHED)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('integer', gettype($response->ask_size));
        $this->assertEquals('double', gettype($response->bid));
        $this->assertEquals('integer', gettype($response->bid_size));
        $this->assertEquals('double', gettype($response->mid));
        $this->assertEquals('double', gettype($response->last));
    }

    /**
     * Test stocks quote with mode=DELAYED.
     * Verifies that the API accepts and processes the mode parameter with DELAYED value.
     */
    public function testQuote_modeDelayed_success()
    {
        $response = $this->client->stocks->quote(
            'AAPL',
            false,
            new Parameters(mode: Mode::DELAYED)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('double', gettype($response->ask));
        $this->assertEquals('integer', gettype($response->ask_size));
        $this->assertEquals('double', gettype($response->bid));
        $this->assertEquals('integer', gettype($response->bid_size));
        $this->assertEquals('double', gettype($response->mid));
        $this->assertEquals('double', gettype($response->last));
    }

    /**
     * Test candles endpoint with CSV format and dateformat=unix.
     * Verifies that the CSV response contains Unix timestamps.
     *
     * @throws GuzzleException|ApiException
     */
    public function testCandles_csv_dateFormat_unix_returnsCsv(): void
    {
        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2023-01-01',
            to: '2023-01-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        // Parse CSV and verify date column contains numeric values (Unix timestamps)
        $lines = explode("\n", trim($csv));
        if (count($lines) > 1) {
            // Get header row to find date column index
            $headerRow = str_getcsv($lines[0], ',', '"', '\\');
            $dateColumnIndex = array_search('t', $headerRow);
            if ($dateColumnIndex === false) {
                $dateColumnIndex = array_search('Date', $headerRow);
            }
            
            if ($dateColumnIndex !== false && count($lines) > 1) {
                // Check first data row
                $dataRow = str_getcsv($lines[1], ',', '"', '\\');
                if (isset($dataRow[$dateColumnIndex])) {
                    $dateValue = $dataRow[$dateColumnIndex];
                    // Unix timestamps are numeric
                    $this->assertTrue(is_numeric($dateValue), "Date value should be numeric (Unix timestamp), got: $dateValue");
                    $this->assertGreaterThan(1000000000, (int)$dateValue, "Unix timestamp should be > 1000000000, got: $dateValue");
                }
            }
        }
    }

    /**
     * Test candles endpoint with CSV format and dateformat=timestamp.
     * Verifies that the CSV response contains ISO timestamp strings.
     *
     * @throws GuzzleException|ApiException
     */
    public function testCandles_csv_dateFormat_timestamp_returnsCsv(): void
    {
        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2023-01-01',
            to: '2023-01-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        // Parse CSV and verify date column contains ISO timestamp strings
        $lines = explode("\n", trim($csv));
        if (count($lines) > 1) {
            // Get header row to find date column index
            $headerRow = str_getcsv($lines[0], ',', '"', '\\');
            $dateColumnIndex = array_search('t', $headerRow);
            if ($dateColumnIndex === false) {
                $dateColumnIndex = array_search('Date', $headerRow);
            }
            
            if ($dateColumnIndex !== false && count($lines) > 1) {
                // Check first data row
                $dataRow = str_getcsv($lines[1], ',', '"', '\\');
                if (isset($dataRow[$dateColumnIndex])) {
                    $dateValue = $dataRow[$dateColumnIndex];
                    // ISO timestamps contain 'T' or '-' and are not purely numeric
                    $this->assertFalse(is_numeric($dateValue), "Date value should be ISO string, got: $dateValue");
                    // Check for ISO format pattern (contains T or -)
                    $this->assertTrue(
                        strpos($dateValue, 'T') !== false || strpos($dateValue, '-') !== false,
                        "Date value should be ISO format, got: $dateValue"
                    );
                }
            }
        }
    }

    /**
     * Test candles endpoint with CSV format and dateformat=spreadsheet.
     * Verifies that the CSV response contains spreadsheet date numbers.
     *
     * @throws GuzzleException|ApiException
     */
    public function testCandles_csv_dateFormat_spreadsheet_returnsCsv(): void
    {
        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2023-01-01',
            to: '2023-01-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::SPREADSHEET)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        // Parse CSV and verify date column contains numeric values (spreadsheet dates are smaller than Unix)
        $lines = explode("\n", trim($csv));
        if (count($lines) > 1) {
            // Get header row to find date column index
            $headerRow = str_getcsv($lines[0], ',', '"', '\\');
            $dateColumnIndex = array_search('t', $headerRow);
            if ($dateColumnIndex === false) {
                $dateColumnIndex = array_search('Date', $headerRow);
            }
            
            if ($dateColumnIndex !== false && count($lines) > 1) {
                // Check first data row
                $dataRow = str_getcsv($lines[1], ',', '"', '\\');
                if (isset($dataRow[$dateColumnIndex])) {
                    $dateValue = $dataRow[$dateColumnIndex];
                    // Spreadsheet dates are numeric but smaller than Unix timestamps
                    $this->assertTrue(is_numeric($dateValue), "Date value should be numeric (spreadsheet date), got: $dateValue");
                    // Spreadsheet dates are typically < 1000000 (days since 1900)
                    $numericValue = (float)$dateValue;
                    $this->assertLessThan(1000000, $numericValue, "Spreadsheet date should be < 1000000, got: $dateValue");
                }
            }
        }
    }

    /**
     * Test quote endpoint with CSV format and dateformat=unix.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_dateFormat_unix_returnsCsv(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);
    }

    /**
     * Test earnings endpoint with CSV format and dateformat=timestamp.
     *
     * @throws GuzzleException|ApiException
     */
    public function testEarnings_csv_dateFormat_timestamp_returnsCsv(): void
    {
        $response = $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2023-01-01',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP)
        );

        $this->assertInstanceOf(Earnings::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);
    }

    /**
     * Test quote endpoint with CSV format and add_headers=true.
     * Verifies that the CSV response includes header row.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_addHeadersTrue_includesHeaders(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV, add_headers: true)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        $lines = explode("\n", trim($csv));
        $this->assertGreaterThanOrEqual(2, count($lines), 'CSV should have at least header row and one data row');

        // First line should be headers
        $headerRow = str_getcsv($lines[0], ',', '"', '\\');
        $this->assertNotEmpty($headerRow);
        $this->assertContains('symbol', $headerRow, 'Header row should contain "symbol" column');
    }

    /**
     * Test quote endpoint with CSV format and add_headers=false.
     * Verifies that the CSV response does NOT include header row.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_addHeadersFalse_excludesHeaders(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV, add_headers: false)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);

        $lines = explode("\n", trim($csv));
        $this->assertGreaterThanOrEqual(1, count($lines), 'CSV should have at least one data row');

        // First line should be data, not headers
        $firstRow = str_getcsv($lines[0], ',', '"', '\\');
        $this->assertNotEmpty($firstRow);
        
        // If first row contains "symbol" as a value (not header), it's likely data
        // If it contains column names like "symbol", "ask", "bid" as headers, that's wrong
        // We check that the first value is not "symbol" (which would indicate it's a header)
        // Actually, let's check if the first row looks like data (contains AAPL) vs headers
        if (count($lines) > 0) {
            $firstValue = $firstRow[0] ?? '';
            // If headers are present, first row would start with column names
            // If no headers, first row should start with actual data (like "AAPL")
            // We verify that the first row does NOT look like a header row
            // by checking if it contains the symbol value or numeric values
            $hasNumericValues = false;
            foreach ($firstRow as $value) {
                if (is_numeric($value)) {
                    $hasNumericValues = true;
                    break;
                }
            }
            // If we have numeric values in the first row, it's likely data, not headers
            $this->assertTrue(
                $firstValue === 'AAPL' || $hasNumericValues,
                'First row should be data (contain symbol or numeric values), not headers'
            );
        }
    }

    /**
     * Test quotes endpoint (parallel) with CSV format and add_headers=true.
     * Verifies that the CSV response includes header row for parallel requests.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuotes_csv_addHeadersTrue_includesHeaders(): void
    {
        $response = $this->client->stocks->quotes(
            symbols: ['AAPL'],
            parameters: new Parameters(format: Format::CSV, add_headers: true)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertNotEmpty($response->quotes);

        $quote = $response->quotes[0];
        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertTrue($quote->isCsv());

        $csv = $quote->getCsv();
        $this->assertNotEmpty($csv);

        $lines = explode("\n", trim($csv));
        $this->assertGreaterThanOrEqual(2, count($lines), 'CSV should have at least header row and one data row');

        // First line should be headers
        $headerRow = str_getcsv($lines[0], ',', '"', '\\');
        $this->assertNotEmpty($headerRow);
        $this->assertContains('symbol', $headerRow, 'Header row should contain "symbol" column');
    }

    /**
     * Test quotes endpoint (parallel) with CSV format and add_headers=false.
     * Verifies that the CSV response does NOT include header row for parallel requests.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuotes_csv_addHeadersFalse_excludesHeaders(): void
    {
        $response = $this->client->stocks->quotes(
            symbols: ['AAPL'],
            parameters: new Parameters(format: Format::CSV, add_headers: false)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertNotEmpty($response->quotes);

        $quote = $response->quotes[0];
        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertTrue($quote->isCsv());

        $csv = $quote->getCsv();
        $this->assertNotEmpty($csv);

        $lines = explode("\n", trim($csv));
        $this->assertGreaterThanOrEqual(1, count($lines), 'CSV should have at least one data row');

        // First line should be data, not headers
        $firstRow = str_getcsv($lines[0], ',', '"', '\\');
        $this->assertNotEmpty($firstRow);
        
        $firstValue = $firstRow[0] ?? '';
        $hasNumericValues = false;
        foreach ($firstRow as $value) {
            if (is_numeric($value)) {
                $hasNumericValues = true;
                break;
            }
        }
        // If we have numeric values in the first row, it's likely data, not headers
        $this->assertTrue(
            $firstValue === 'AAPL' || $hasNumericValues,
            'First row should be data (contain symbol or numeric values), not headers'
        );
    }

    /**
     * Test quote endpoint with CSV format and filename parameter.
     * Verifies that the CSV file is created and contains correct data.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_withFilename_createsFile(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_quote_' . uniqid() . '.csv';

        try {
            $response = $this->client->stocks->quote(
                symbol: 'AAPL',
                parameters: new Parameters(format: Format::CSV, filename: $testFile)
            );

            $this->assertInstanceOf(Quote::class, $response);
            $this->assertTrue($response->isCsv());

            // Verify file was created
            $this->assertFileExists($testFile, 'CSV file should be created');

            // Verify file contains data
            $fileContent = file_get_contents($testFile);
            $this->assertNotEmpty($fileContent, 'CSV file should contain data');
            $this->assertStringContainsString('AAPL', $fileContent, 'CSV file should contain symbol');

            // Verify getCsv() still works
            $csvString = $response->getCsv();
            $this->assertNotEmpty($csvString);
            $this->assertEquals($fileContent, $csvString, 'getCsv() should return same content as file');

            // Verify _saved_filename property exists
            $this->assertObjectHasProperty('_saved_filename', $response);
            $this->assertEquals($testFile, $response->_saved_filename);
        } finally {
            // Clean up
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    /**
     * Test quote endpoint with CSV format without filename parameter.
     * Verifies backward compatibility - object is returned without file creation.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_withoutFilename_returnsObject(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csvString = $response->getCsv();
        $this->assertNotEmpty($csvString);
        $this->assertStringContainsString('AAPL', $csvString);

        // Verify no file was created (_saved_filename should be null)
        $this->assertNull($response->_saved_filename ?? null, '_saved_filename should be null when no filename parameter is provided');
    }

    /**
     * Test quote endpoint with CSV format and nested directory path.
     * Verifies that directory is created automatically.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_nestedDirectory_createsDirectory(): void
    {
        $tempDir = sys_get_temp_dir();
        $nestedDir = $tempDir . '/test_nested_' . uniqid();
        // Create the parent directory first (validation requires it to exist)
        mkdir($nestedDir, 0755, true);
        $testFile = $nestedDir . '/subdir/test.csv';

        try {
            $response = $this->client->stocks->quote(
                symbol: 'AAPL',
                parameters: new Parameters(format: Format::CSV, filename: $testFile)
            );

            $this->assertInstanceOf(Quote::class, $response);
            $this->assertTrue($response->isCsv());

            // Verify directory was created
            $this->assertDirectoryExists(dirname($testFile), 'Nested directory should be created');

            // Verify file was created
            $this->assertFileExists($testFile, 'CSV file should be created in nested directory');

            // Verify file contains data
            $fileContent = file_get_contents($testFile);
            $this->assertNotEmpty($fileContent);
        } finally {
            // Clean up
            if (file_exists($testFile)) {
                unlink($testFile);
            }
            $subdir = dirname($testFile);
            if (is_dir($subdir)) {
                rmdir($subdir);
            }
            if (is_dir($nestedDir)) {
                rmdir($nestedDir);
            }
        }
    }

    /**
     * Test quote endpoint with CSV format and existing file.
     * Verifies that exception is thrown to prevent overwriting.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_existingFile_throwsException(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_existing_' . uniqid() . '.csv';

        // Create the file first
        file_put_contents($testFile, 'existing content');

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('File already exists');

            $this->client->stocks->quote(
                symbol: 'AAPL',
                parameters: new Parameters(format: Format::CSV, filename: $testFile)
            );
        } finally {
            // Clean up
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    /**
     * Test quote endpoint with CSV format and invalid extension.
     * Verifies that exception is thrown for invalid extension.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_invalidExtension_throwsException(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_invalid_' . uniqid() . '.txt';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('filename must end with .csv');

        $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV, filename: $testFile)
        );
    }

    /**
     * Test saveToFile() method on response object.
     * Verifies that saveToFile() works correctly.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuote_csv_saveToFile_works(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_savetofile_' . uniqid() . '.csv';

        try {
            // Get response without filename
            $response = $this->client->stocks->quote(
                symbol: 'AAPL',
                parameters: new Parameters(format: Format::CSV)
            );

            $this->assertInstanceOf(Quote::class, $response);
            $this->assertTrue($response->isCsv());

            // Use saveToFile() method
            $savedPath = $response->saveToFile($testFile);

            // Verify file was created
            $this->assertFileExists($testFile, 'File should be created by saveToFile()');
            $this->assertFileExists($savedPath, 'Returned path should exist');

            // Verify file contains data
            $fileContent = file_get_contents($testFile);
            $this->assertNotEmpty($fileContent);
            $this->assertStringContainsString('AAPL', $fileContent);

            // Verify content matches getCsv()
            $this->assertEquals($response->getCsv(), $fileContent);
        } finally {
            // Clean up
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    /**
     * Test quotes endpoint (parallel) with filename parameter.
     * Verifies that exception is thrown for parallel requests with filename.
     *
     * @throws GuzzleException|ApiException
     */
    public function testQuotes_csv_withFilename_throwsException(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_parallel_' . uniqid() . '.csv';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('filename parameter cannot be used with parallel requests');

        $this->client->stocks->quotes(
            symbols: ['AAPL'],
            parameters: new Parameters(format: Format::CSV, filename: $testFile)
        );
    }

    /**
     * Test successful retrieval of stock prices for a single symbol.
     *
     * @throws GuzzleException|ApiException
     */
    public function testPrices_singleSymbol_success()
    {
        $response = $this->client->stocks->prices('AAPL');

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->symbols);
        $this->assertCount(1, $response->symbols);
        $this->assertEquals('AAPL', $response->symbols[0]);
        $this->assertNotEmpty($response->mid);
        $this->assertCount(1, $response->mid);
        $this->assertTrue(in_array(gettype($response->mid[0]), ['double', 'integer']), "Expected mid to be double or integer");
        $this->assertNotEmpty($response->change);
        $this->assertCount(1, $response->change);
        $this->assertTrue(in_array(gettype($response->change[0]), ['double', 'integer', 'NULL']));
        $this->assertNotEmpty($response->changepct);
        $this->assertCount(1, $response->changepct);
        $this->assertTrue(in_array(gettype($response->changepct[0]), ['double', 'integer', 'NULL']));
        $this->assertNotEmpty($response->updated);
        $this->assertCount(1, $response->updated);
        $this->assertInstanceOf(Carbon::class, $response->updated[0]);
    }

    /**
     * Test successful retrieval of stock prices for multiple symbols.
     *
     * @throws GuzzleException|ApiException
     */
    public function testPrices_multipleSymbols_success()
    {
        $response = $this->client->stocks->prices(['AAPL', 'META', 'MSFT']);

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->symbols);
        $this->assertCount(3, $response->symbols);
        $this->assertContains('AAPL', $response->symbols);
        $this->assertContains('META', $response->symbols);
        $this->assertContains('MSFT', $response->symbols);
        
        // Verify all arrays have the same length
        $this->assertCount(3, $response->mid);
        $this->assertCount(3, $response->change);
        $this->assertCount(3, $response->changepct);
        $this->assertCount(3, $response->updated);
        
        // Verify data types (API may return integer for round numbers or double for decimals)
        foreach ($response->mid as $mid) {
            $this->assertTrue(in_array(gettype($mid), ['double', 'integer']), "Expected mid to be double or integer, got " . gettype($mid));
        }
        foreach ($response->change as $change) {
            $this->assertTrue(in_array(gettype($change), ['double', 'integer', 'NULL']), "Expected change to be double, integer, or NULL, got " . gettype($change));
        }
        foreach ($response->changepct as $changepct) {
            $this->assertTrue(in_array(gettype($changepct), ['double', 'integer', 'NULL']), "Expected changepct to be double, integer, or NULL, got " . gettype($changepct));
        }
        foreach ($response->updated as $updated) {
            $this->assertInstanceOf(Carbon::class, $updated);
        }
    }

    /**
     * Test prices endpoint with extended=true parameter.
     *
     * @throws GuzzleException|ApiException
     */
    public function testPrices_extendedTrue_success()
    {
        $response = $this->client->stocks->prices('AAPL', extended: true);

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->symbols);
        $this->assertCount(1, $response->symbols);
        $this->assertNotEmpty($response->mid);
        $this->assertNotEmpty($response->updated);
    }

    /**
     * Test prices endpoint with extended=false parameter.
     *
     * @throws GuzzleException|ApiException
     */
    public function testPrices_extendedFalse_success()
    {
        $response = $this->client->stocks->prices('AAPL', extended: false);

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->symbols);
        $this->assertCount(1, $response->symbols);
        $this->assertNotEmpty($response->mid);
        $this->assertNotEmpty($response->updated);
    }

    /**
     * Test successful retrieval of stock prices in CSV format.
     *
     * @throws GuzzleException|ApiException
     */
    public function testPrices_csv_success()
    {
        $response = $this->client->stocks->prices(
            'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
        $this->assertNotEmpty($response->getCsv());
    }

    /**
     * Test prices endpoint with human-readable format.
     * Verifies that the API returns human-readable JSON keys with spaces.
     *
     * @throws GuzzleException|ApiException
     */
    public function testPrices_humanReadable_success()
    {
        $response = $this->client->stocks->prices(
            ['AAPL', 'META'],
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Prices::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->symbols);
        $this->assertCount(2, $response->symbols);
        $this->assertNotEmpty($response->mid);
        $this->assertCount(2, $response->mid);
        $this->assertNotEmpty($response->change);
        $this->assertCount(2, $response->change);
        $this->assertNotEmpty($response->changepct);
        $this->assertCount(2, $response->changepct);
        $this->assertNotEmpty($response->updated);
        $this->assertCount(2, $response->updated);
        foreach ($response->updated as $updated) {
            $this->assertInstanceOf(Carbon::class, $updated);
        }
    }
}
