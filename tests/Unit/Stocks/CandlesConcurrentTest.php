<?php

namespace MarketDataApp\Tests\Unit\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Candle;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;
use MarketDataApp\Settings;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test case for the automatic concurrent request handling for Candles endpoint.
 *
 * Tests the automatic date range splitting feature that splits large intraday
 * date ranges into year-long chunks and fetches them concurrently.
 */
class CandlesConcurrentTest extends StocksTestCase
{
    /**
     * Test that MAX_CONCURRENT_REQUESTS constant exists and has correct value.
     *
     * This is an API-wide limit enforced across all parallel request operations.
     */
    public function testMaxConcurrentRequestsConstant(): void
    {
        $this->assertEquals(50, Settings::MAX_CONCURRENT_REQUESTS);
    }

    /**
     * Test isIntradayResolution() with minutely resolutions.
     */
    #[DataProvider('minutelyResolutionsProvider')]
    public function testIsIntradayResolution_minutely(string $resolution): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('isIntradayResolution');

        $this->assertTrue(
            $method->invoke($stocks, $resolution),
            "Resolution '{$resolution}' should be intraday"
        );
    }

    public static function minutelyResolutionsProvider(): array
    {
        return [
            'minutely' => ['minutely'],
            '1 minute' => ['1'],
            '3 minutes' => ['3'],
            '5 minutes' => ['5'],
            '15 minutes' => ['15'],
            '30 minutes' => ['30'],
            '45 minutes' => ['45'],
            '60 minutes' => ['60'],
        ];
    }

    /**
     * Test isIntradayResolution() with hourly resolutions.
     */
    #[DataProvider('hourlyResolutionsProvider')]
    public function testIsIntradayResolution_hourly(string $resolution): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('isIntradayResolution');

        $this->assertTrue(
            $method->invoke($stocks, $resolution),
            "Resolution '{$resolution}' should be intraday"
        );
    }

    public static function hourlyResolutionsProvider(): array
    {
        return [
            'H' => ['H'],
            'h lowercase' => ['h'],
            'hourly' => ['hourly'],
            '1H' => ['1H'],
            '2H' => ['2H'],
            '4H' => ['4H'],
        ];
    }

    /**
     * Test isIntradayResolution() with non-intraday resolutions.
     */
    #[DataProvider('nonIntradayResolutionsProvider')]
    public function testIsIntradayResolution_nonIntraday(string $resolution): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('isIntradayResolution');

        $this->assertFalse(
            $method->invoke($stocks, $resolution),
            "Resolution '{$resolution}' should NOT be intraday"
        );
    }

    public static function nonIntradayResolutionsProvider(): array
    {
        return [
            'daily D' => ['D'],
            'daily 1D' => ['1D'],
            'daily word' => ['daily'],
            'weekly W' => ['W'],
            'weekly 1W' => ['1W'],
            'weekly word' => ['weekly'],
            'monthly M' => ['M'],
            'monthly 1M' => ['1M'],
            'monthly word' => ['monthly'],
            'yearly Y' => ['Y'],
            'yearly 1Y' => ['1Y'],
            'yearly word' => ['yearly'],
        ];
    }

    /**
     * Test isParseableDate() with valid ISO dates.
     */
    #[DataProvider('validDatesProvider')]
    public function testIsParseableDate_valid(string $date): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('isParseableDate');

        $this->assertTrue(
            $method->invoke($stocks, $date),
            "Date '{$date}' should be parseable"
        );
    }

    public static function validDatesProvider(): array
    {
        return [
            'ISO date' => ['2023-01-15'],
            'ISO datetime' => ['2023-01-15T10:30:00'],
            'ISO with timezone' => ['2023-01-15T10:30:00Z'],
            'Unix timestamp' => ['1673784600'],
        ];
    }

    /**
     * Test isParseableDate() with relative dates.
     */
    #[DataProvider('relativeDatesProvider')]
    public function testIsParseableDate_relative(string $date): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('isParseableDate');

        $this->assertFalse(
            $method->invoke($stocks, $date),
            "Date '{$date}' should NOT be parseable (relative date)"
        );
    }

    public static function relativeDatesProvider(): array
    {
        return [
            'today' => ['today'],
            'yesterday' => ['yesterday'],
            'tomorrow' => ['tomorrow'],
            'now' => ['now'],
            '-5 days' => ['-5 days'],
            '+1 week' => ['+1 week'],
            '2 months ago pattern' => ['2 month'],
        ];
    }

    /**
     * Test isParseableDate() with truly invalid dates that cause Carbon to throw.
     */
    #[DataProvider('invalidDatesProvider')]
    public function testIsParseableDate_invalid(string $date): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('isParseableDate');

        $this->assertFalse(
            $method->invoke($stocks, $date),
            "Date '{$date}' should NOT be parseable (invalid date)"
        );
    }

    public static function invalidDatesProvider(): array
    {
        return [
            'random string' => ['not-a-date'],
            'invalid format' => ['xyz123'],
            // Note: empty string is parsed as "now" by Carbon, so it's not truly invalid
            'garbage characters' => ['!@#$%^&*()'],
        ];
    }

    /**
     * Test splitDateRangeIntoYearChunks() with a 2-year range.
     */
    public function testSplitDateRangeIntoYearChunks_twoYears(): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('splitDateRangeIntoYearChunks');

        $chunks = $method->invoke($stocks, '2022-01-01', '2023-12-31');

        $this->assertCount(2, $chunks);
        $this->assertEquals(['2022-01-01', '2022-12-31'], $chunks[0]);
        $this->assertEquals(['2023-01-01', '2023-12-31'], $chunks[1]);
    }

    /**
     * Test splitDateRangeIntoYearChunks() with a 3-year range.
     */
    public function testSplitDateRangeIntoYearChunks_threeYears(): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('splitDateRangeIntoYearChunks');

        $chunks = $method->invoke($stocks, '2021-06-15', '2024-03-20');

        $this->assertCount(3, $chunks);
        $this->assertEquals('2021-06-15', $chunks[0][0]);
        $this->assertEquals('2022-06-14', $chunks[0][1]);
        $this->assertEquals('2022-06-15', $chunks[1][0]);
        $this->assertEquals('2023-06-14', $chunks[1][1]);
        $this->assertEquals('2023-06-15', $chunks[2][0]);
        $this->assertEquals('2024-03-20', $chunks[2][1]);
    }

    /**
     * Test splitDateRangeIntoYearChunks() with exactly one year.
     */
    public function testSplitDateRangeIntoYearChunks_exactlyOneYear(): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('splitDateRangeIntoYearChunks');

        $chunks = $method->invoke($stocks, '2023-01-01', '2023-12-31');

        $this->assertCount(1, $chunks);
        $this->assertEquals(['2023-01-01', '2023-12-31'], $chunks[0]);
    }

    /**
     * Test splitDateRangeIntoYearChunks() preserves time-of-day in first and last chunks.
     *
     * Bug #011: When splitting date ranges, the original time-of-day was stripped
     * from the from/to values. The first chunk's from and last chunk's to should
     * preserve the original timestamp including time-of-day.
     */
    public function testSplitDateRangeIntoYearChunks_preservesTimeOfDay(): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('splitDateRangeIntoYearChunks');

        // Test with ISO 8601 timestamps including time-of-day
        $from = '2020-01-01T12:34:56Z';
        $to = '2022-06-15T09:15:30Z';

        $chunks = $method->invoke($stocks, $from, $to);

        $this->assertCount(3, $chunks);

        // First chunk's from should preserve original time-of-day
        $this->assertEquals($from, $chunks[0][0], 'First chunk from should preserve original timestamp');

        // Last chunk's to should preserve original time-of-day
        $this->assertEquals($to, $chunks[2][1], 'Last chunk to should preserve original timestamp');

        // Intermediate boundaries should use date strings
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $chunks[0][1], 'First chunk to should be date-only');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $chunks[1][0], 'Second chunk from should be date-only');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $chunks[1][1], 'Second chunk to should be date-only');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $chunks[2][0], 'Third chunk from should be date-only');
    }

    /**
     * Test splitDateRangeIntoYearChunks() with single chunk preserves both timestamps.
     *
     * When the date range is less than a year, only one chunk is created and
     * both from and to should preserve the original timestamps.
     */
    public function testSplitDateRangeIntoYearChunks_singleChunkPreservesTimestamps(): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('splitDateRangeIntoYearChunks');

        $from = '2023-03-15T08:30:00Z';
        $to = '2023-09-20T16:45:00Z';

        $chunks = $method->invoke($stocks, $from, $to);

        $this->assertCount(1, $chunks);
        $this->assertEquals($from, $chunks[0][0], 'Single chunk from should preserve original timestamp');
        $this->assertEquals($to, $chunks[0][1], 'Single chunk to should preserve original timestamp');
    }

    /**
     * Test needsAutomaticSplitting() returns true for large intraday range.
     */
    public function testNeedsAutomaticSplitting_largeIntradayRange(): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('needsAutomaticSplitting');

        // 2 year range with minutely resolution
        $result = $method->invoke($stocks, '5', '2022-01-01', '2024-01-01', null);
        $this->assertTrue($result);

        // 2 year range with hourly resolution
        $result = $method->invoke($stocks, 'H', '2022-01-01', '2024-01-01', null);
        $this->assertTrue($result);
    }

    /**
     * Test needsAutomaticSplitting() returns false for daily resolution.
     */
    public function testNeedsAutomaticSplitting_dailyResolution(): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('needsAutomaticSplitting');

        // Daily resolution should not trigger splitting
        $result = $method->invoke($stocks, 'D', '2020-01-01', '2024-01-01', null);
        $this->assertFalse($result);
    }

    /**
     * Test needsAutomaticSplitting() returns false when countback is specified.
     */
    public function testNeedsAutomaticSplitting_withCountback(): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('needsAutomaticSplitting');

        // Countback specified - should not split
        $result = $method->invoke($stocks, '5', '2022-01-01', '2024-01-01', 100);
        $this->assertFalse($result);
    }

    /**
     * Test needsAutomaticSplitting() returns false when to is null.
     */
    public function testNeedsAutomaticSplitting_noToDate(): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('needsAutomaticSplitting');

        // No 'to' date - should not split
        $result = $method->invoke($stocks, '5', '2022-01-01', null, null);
        $this->assertFalse($result);
    }

    /**
     * Test needsAutomaticSplitting() returns false for small date range.
     */
    public function testNeedsAutomaticSplitting_smallRange(): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('needsAutomaticSplitting');

        // Less than 1 year range - should not split
        $result = $method->invoke($stocks, '5', '2023-01-01', '2023-06-01', null);
        $this->assertFalse($result);
    }

    /**
     * Test needsAutomaticSplitting() returns false for relative dates.
     */
    public function testNeedsAutomaticSplitting_relativeDates(): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('needsAutomaticSplitting');

        // Relative dates - should not split (can't determine range)
        $result = $method->invoke($stocks, '5', 'today', '-2 years', null);
        $this->assertFalse($result);
    }

    /**
     * Test Candles::createMerged() creates correct object.
     */
    public function testCandlesCreateMerged_success(): void
    {
        $candle1 = new Candle(100.0, 105.0, 99.0, 104.0, 1000, Carbon::parse('2023-01-01 09:30:00'));
        $candle2 = new Candle(104.0, 106.0, 103.0, 105.0, 1200, Carbon::parse('2023-01-01 10:30:00'));

        $merged = Candles::createMerged('ok', [$candle1, $candle2]);

        $this->assertEquals('ok', $merged->status);
        $this->assertCount(2, $merged->candles);
        $this->assertEquals(100.0, $merged->candles[0]->open);
        $this->assertEquals(104.0, $merged->candles[1]->open);
    }

    /**
     * Test Candles::createMerged() with no_data status and next_time.
     */
    public function testCandlesCreateMerged_noDataWithNextTime(): void
    {
        $nextTime = 1704067200; // 2024-01-01

        $merged = Candles::createMerged('no_data', [], $nextTime);

        $this->assertEquals('no_data', $merged->status);
        $this->assertEmpty($merged->candles);
        $this->assertEquals($nextTime, $merged->next_time);
    }

    /**
     * Test automatic concurrent candles with 2-year range.
     */
    public function testCandles_automaticConcurrent_twoYearRange(): void
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/5/AAPL/?from=2022-01-03&to=2022-01-03" (first 3 candles)
        $response1 = [
            's' => 'ok',
            't' => [1641220200, 1641220500, 1641220800],
            'o' => [177.83, 178.97, 180.33],
            'h' => [179.31, 180.4, 180.84],
            'l' => [177.71, 178.92, 180.21],
            'c' => [178.965, 180.33, 180.595],
            'v' => [3342579, 2482107, 2219885],
        ];

        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/5/AAPL/?from=2023-01-03&to=2023-01-03" (first 3 candles)
        $response2 = [
            's' => 'ok',
            't' => [1672756200, 1672756500, 1672756800],
            'o' => [130.28, 129.83, 130.51],
            'h' => [130.6999, 130.68, 130.9],
            'l' => [129.44, 129.53, 129.74],
            'c' => [129.84, 130.5, 129.885],
            'v' => [3826842, 2219751, 2082915],
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);

        // Request 2 years of 5-minute candles
        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5'
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('ok', $result->status);
        $this->assertCount(6, $result->candles);

        // Verify candles are sorted by timestamp (2022 before 2023)
        $this->assertEquals(1641220200, $result->candles[0]->timestamp->timestamp);
        $this->assertEquals(1641220500, $result->candles[1]->timestamp->timestamp);
        $this->assertEquals(1641220800, $result->candles[2]->timestamp->timestamp);
        $this->assertEquals(1672756200, $result->candles[3]->timestamp->timestamp);
        $this->assertEquals(1672756500, $result->candles[4]->timestamp->timestamp);
        $this->assertEquals(1672756800, $result->candles[5]->timestamp->timestamp);
    }

    /**
     * Test automatic concurrent candles with hourly resolution.
     */
    public function testCandles_automaticConcurrent_hourlyResolution(): void
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/H/AAPL/?from=2022-01-03&to=2022-01-03" (first 2 candles)
        $response1 = [
            's' => 'ok',
            't' => [1641220200, 1641223800],
            'o' => [177.83, 180.85],
            'h' => [181.43, 181.77],
            'l' => [177.71, 180.39],
            'c' => [180.84, 181.75],
            'v' => [24032849, 11994284],
        ];

        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/H/AAPL/?from=2023-01-03&to=2023-01-03" (first 2 candles)
        $response2 = [
            's' => 'ok',
            't' => [1672756200, 1672759800],
            'o' => [130.28, 125.46],
            'h' => [130.9, 125.87],
            'l' => [125.23, 124.73],
            'c' => [125.46, 125.345],
            'v' => [25979936, 18105002],
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);

        // Request 2 years of hourly candles
        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: 'H'
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('ok', $result->status);
        $this->assertCount(4, $result->candles);
    }

    /**
     * Test that daily resolution does NOT trigger automatic splitting.
     */
    public function testCandles_dailyResolution_noAutomaticSplitting(): void
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/D/AAPL/?from=2022-01-03&to=2024-01-03" (first 3 candles)
        $response = [
            's' => 'ok',
            't' => [1641186000, 1641272400, 1641358800],
            'o' => [177.83, 182.63, 179.61],
            'h' => [182.88, 182.94, 180.17],
            'l' => [177.71, 179.12, 174.64],
            'c' => [182.01, 179.7, 174.92],
            'v' => [104701220, 99310438, 94537602],
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response)),
        ]);

        // Request 2 years of daily candles - should NOT split
        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: 'D'
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('ok', $result->status);
        $this->assertCount(3, $result->candles);
    }

    /**
     * Test concurrent candles with partial no_data responses.
     */
    public function testCandles_automaticConcurrent_partialNoData(): void
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        // First chunk has real data from 2022-01-03
        $response1 = [
            's' => 'ok',
            't' => [1641220200, 1641220500],
            'o' => [177.83, 178.97],
            'h' => [179.31, 180.4],
            'l' => [177.71, 178.92],
            'c' => [178.965, 180.33],
            'v' => [3342579, 2482107],
        ];

        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/5/AAPL/?from=2024-01-06&to=2024-01-07" (weekend, no data)
        $response2 = [
            's' => 'no_data',
            'prevTime' => null,
            'nextTime' => null,
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5'
        );

        $this->assertInstanceOf(Candles::class, $result);
        // Overall status should be 'ok' since at least one chunk had data
        $this->assertEquals('ok', $result->status);
        $this->assertCount(2, $result->candles);
    }

    /**
     * Test concurrent candles with all no_data responses.
     */
    public function testCandles_automaticConcurrent_allNoData(): void
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/5/AAPL/?from=2024-01-06&to=2024-01-07" (weekend, no data)
        $response1 = [
            's' => 'no_data',
            'prevTime' => null,
            'nextTime' => null,
        ];

        // Mock response: FROM real API output (captured on 2026-01-23)
        $response2 = [
            's' => 'no_data',
            'prevTime' => null,
            'nextTime' => null,
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5'
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('no_data', $result->status);
        $this->assertEmpty($result->candles);
    }

    /**
     * Test concurrent candles with all no_data responses that have nextTime values.
     * Verifies that the earliest nextTime is preserved in the merged result.
     */
    public function testCandles_automaticConcurrent_allNoDataWithNextTime(): void
    {
        // Mock response: NOT from real API output (synthetic edge case)
        // Tests nextTime comparison logic - first response has later nextTime
        $response1 = [
            's' => 'no_data',
            'nextTime' => 1672756200, // 2023-01-03 09:30:00 (later)
        ];

        // Mock response: NOT from real API output (synthetic edge case)
        // Second response has earlier nextTime - this should be preserved
        $response2 = [
            's' => 'no_data',
            'nextTime' => 1641220200, // 2022-01-03 09:30:00 (earlier)
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5'
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('no_data', $result->status);
        $this->assertEmpty($result->candles);
        // The earliest nextTime should be preserved
        $this->assertEquals(1641220200, $result->next_time);
    }

    /**
     * Test concurrent candles removes duplicate timestamps.
     */
    public function testCandles_automaticConcurrent_removeDuplicates(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        // This is a synthetic edge case to test deduplication when chunks have overlapping timestamps
        $response1 = [
            's' => 'ok',
            't' => [1641220200, 1672444800], // second timestamp is a synthetic boundary overlap
            'o' => [177.83, 130.0],
            'h' => [179.31, 135.0],
            'l' => [177.71, 129.0],
            'c' => [178.965, 134.0],
            'v' => [3342579, 1000000],
        ];

        // Mock response: NOT from real API output (uses synthetic/test data)
        $response2 = [
            's' => 'ok',
            't' => [1672444800, 1672756200], // same boundary timestamp as response1
            'o' => [130.0, 130.28],
            'h' => [135.0, 130.6999],
            'l' => [129.0, 129.44],
            'c' => [134.0, 129.84],
            'v' => [1000000, 3826842],
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5'
        );

        $this->assertInstanceOf(Candles::class, $result);
        // Should have 3 unique candles (1672444800 appears in both but should be deduplicated)
        $this->assertCount(3, $result->candles);
    }

    /**
     * Test concurrent candles with parameters (extended hours, splits adjustment).
     */
    public function testCandles_automaticConcurrent_withParameters(): void
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/5/AAPL/?from=2022-01-03&to=2022-01-03" (first 2 candles)
        $response1 = [
            's' => 'ok',
            't' => [1641220200, 1641220500],
            'o' => [177.83, 178.97],
            'h' => [179.31, 180.4],
            'l' => [177.71, 178.92],
            'c' => [178.965, 180.33],
            'v' => [3342579, 2482107],
        ];

        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/5/AAPL/?from=2023-01-03&to=2023-01-03" (first 2 candles)
        $response2 = [
            's' => 'ok',
            't' => [1672756200, 1672756500],
            'o' => [130.28, 129.83],
            'h' => [130.6999, 130.68],
            'l' => [129.44, 129.53],
            'c' => [129.84, 130.5],
            'v' => [3826842, 2219751],
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            extended: true,
            adjust_splits: true
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('ok', $result->status);
        $this->assertCount(4, $result->candles);
    }

    /**
     * Test that small intraday range does NOT trigger splitting.
     */
    public function testCandles_smallIntradayRange_noSplitting(): void
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/5/AAPL/?from=2023-01-03&to=2023-01-03" (first 2 candles)
        $response = [
            's' => 'ok',
            't' => [1672756200, 1672756500],
            'o' => [130.28, 129.83],
            'h' => [130.6999, 130.68],
            'l' => [129.44, 129.53],
            'c' => [129.84, 130.5],
            'v' => [3826842, 2219751],
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response)),
        ]);

        // 6 months of 5-minute candles - should NOT split
        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2023-01-01',
            to: '2023-06-30',
            resolution: '5'
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertCount(2, $result->candles);
    }

    /**
     * Test that countback parameter prevents automatic splitting.
     */
    public function testCandles_withCountback_noSplitting(): void
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/5/AAPL/?from=2023-01-03&to=2023-01-03" (first 2 candles)
        $response = [
            's' => 'ok',
            't' => [1672756200, 1672756500],
            'o' => [130.28, 129.83],
            'h' => [130.6999, 130.68],
            'l' => [129.44, 129.53],
            'c' => [129.84, 130.5],
            'v' => [3826842, 2219751],
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response)),
        ]);

        // Even with 2-year range, countback should prevent splitting
        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            resolution: '5',
            countback: 100
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertCount(2, $result->candles);
    }

    /**
     * Test mergeCandleResponses() with empty responses array.
     */
    public function testMergeCandleResponses_emptyArray(): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('mergeCandleResponses');

        $result = $method->invoke($stocks, []);

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('no_data', $result->status);
        $this->assertEmpty($result->candles);
    }

    /**
     * Test concurrent candles with 3+ year range (multiple chunks).
     */
    public function testCandles_automaticConcurrent_threeYearRange(): void
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/5/AAPL/?from=2021-01-04&to=2021-01-04" (first 2 candles)
        $response1 = [
            's' => 'ok',
            't' => [1609770600, 1609770900],
            'o' => [133.52, 132.83],
            'h' => [133.6116, 132.89],
            'l' => [132.39, 131.81],
            'c' => [132.81, 131.89],
            'v' => [4815264, 2541397],
        ];

        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/5/AAPL/?from=2022-01-03&to=2022-01-03" (first 2 candles)
        $response2 = [
            's' => 'ok',
            't' => [1641220200, 1641220500],
            'o' => [177.83, 178.97],
            'h' => [179.31, 180.4],
            'l' => [177.71, 178.92],
            'c' => [178.965, 180.33],
            'v' => [3342579, 2482107],
        ];

        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/5/AAPL/?from=2023-01-03&to=2023-01-03" (first 2 candles)
        $response3 = [
            's' => 'ok',
            't' => [1672756200, 1672756500],
            'o' => [130.28, 129.83],
            'h' => [130.6999, 130.68],
            'l' => [129.44, 129.53],
            'c' => [129.84, 130.5],
            'v' => [3826842, 2219751],
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
            new Response(200, [], json_encode($response3)),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2021-01-01',
            to: '2023-12-31',
            resolution: '5'
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('ok', $result->status);
        $this->assertCount(6, $result->candles);

        // Verify chronological order (2021 < 2022 < 2023)
        $this->assertLessThan(
            $result->candles[2]->timestamp->timestamp,
            $result->candles[0]->timestamp->timestamp + 1
        );
        $this->assertLessThan(
            $result->candles[4]->timestamp->timestamp,
            $result->candles[2]->timestamp->timestamp + 1
        );
    }

    /**
     * Test no_data response without nextTime field.
     */
    public function testCandles_automaticConcurrent_noDataWithoutNextTime(): void
    {
        // Mock response: NOT from real API output (synthetic edge case)
        // Tests handling of minimal no_data response without optional nextTime field
        $response1 = [
            's' => 'no_data',
        ];

        // Mock response: NOT from real API output (synthetic edge case)
        $response2 = [
            's' => 'no_data',
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5'
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('no_data', $result->status);
        $this->assertEmpty($result->candles);
        $this->assertFalse(isset($result->next_time));
    }

    /**
     * Test that splitDateRangeIntoYearChunks generates many chunks for large date range.
     *
     * This verifies the MAX_CONCURRENT_REQUESTS limiting behavior by testing
     * the splitDateRangeIntoYearChunks method directly with a very large range.
     */
    public function testSplitDateRangeIntoYearChunks_veryLargeRange(): void
    {
        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('splitDateRangeIntoYearChunks');

        // 55-year range should generate 55 chunks
        $chunks = $method->invoke($stocks, '1970-01-01', '2024-12-31');

        $this->assertCount(55, $chunks);
        $this->assertEquals('1970-01-01', $chunks[0][0]);
        $this->assertEquals('2024-12-31', $chunks[54][1]);
    }

    /**
     * Test candlesConcurrent requests all chunks even when exceeding MAX_CONCURRENT_REQUESTS.
     *
     * Tests the behavior where the date range generates more than the API-wide
     * MAX_CONCURRENT_REQUESTS limit of year-long chunks. All chunks are requested
     * (batched by execute_in_parallel's concurrency limit), not truncated.
     */
    public function testCandles_automaticConcurrent_allChunksRequested(): void
    {
        // Mock response: NOT from real API output (synthetic edge case)
        // This test requires 55 mock responses for a 55-year range
        // Using synthetic data with incrementing values for each year chunk
        $numChunks = 55;
        $responses = [];
        for ($i = 0; $i < $numChunks; $i++) {
            $responses[] = new Response(200, [], json_encode([
                's' => 'ok',
                't' => [1640995200 + ($i * 31536000)], // Add 1 year in seconds for each
                'o' => [100.0 + $i],
                'h' => [105.0 + $i],
                'l' => [99.0 + $i],
                'c' => [104.0 + $i],
                'v' => [1000 + $i * 100],
            ]));
        }

        $this->setMockResponses($responses);

        // Request a 55-year range - should make ALL 55 requests (not truncated to 50)
        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '1970-01-01',
            to: '2024-12-31',
            resolution: '5'
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('ok', $result->status);
        // Should have 55 candles (one from each of the 55 chunks - no truncation)
        $this->assertCount($numChunks, $result->candles);
    }

    /**
     * Test candlesConcurrent tolerates partial 404 failures.
     *
     * When some chunks return 404 (no historical data available), those failures
     * are tolerated and data from successful chunks is still returned.
     */
    public function testCandles_automaticConcurrent_toleratesPartial404s(): void
    {
        // Mock response: FROM real API output (captured on 2026-01-25)
        // First chunk has real data
        $response1 = [
            's' => 'ok',
            't' => [1641220200, 1641220500],
            'o' => [177.83, 178.97],
            'h' => [179.31, 180.4],
            'l' => [177.71, 178.92],
            'c' => [178.965, 180.33],
            'v' => [3342579, 2482107],
        ];

        // Second chunk returns 404 (simulating no historical data for that year)
        // Use ClientException to simulate Guzzle's http_errors behavior
        $request = new \GuzzleHttp\Psr7\Request('GET', 'https://api.marketdata.app/v1/stocks/candles/5/AAPL/');
        $response404 = new Response(404, [], json_encode(['s' => 'error', 'errmsg' => 'No data available']));

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new \GuzzleHttp\Exception\ClientException('Not Found', $request, $response404),
        ]);

        // Request 2-year range where second year has no data
        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5'
        );

        $this->assertInstanceOf(Candles::class, $result);
        // Should still be 'ok' since at least one chunk succeeded
        $this->assertEquals('ok', $result->status);
        // Should have 2 candles from the successful chunk
        $this->assertCount(2, $result->candles);
    }

    /**
     * Test candlesConcurrent throws when ALL chunks fail with 404.
     *
     * When every chunk returns a 404, an exception should be thrown
     * since there's no data to return at all. The exception is ApiException
     * because 404 responses return a response body with s: error that gets
     * processed by processResponse which throws ApiException.
     */
    public function testCandles_automaticConcurrent_throwsWhenAll404s(): void
    {
        // Use ClientException to simulate Guzzle's http_errors behavior
        $request = new \GuzzleHttp\Psr7\Request('GET', 'https://api.marketdata.app/v1/stocks/candles/5/AAPL/');
        $response404 = new Response(404, [], json_encode(['s' => 'error', 'errmsg' => 'No data available']));

        $this->setMockResponses([
            new \GuzzleHttp\Exception\ClientException('Not Found', $request, $response404),
            new \GuzzleHttp\Exception\ClientException('Not Found', $request, $response404),
        ]);

        $this->expectException(\MarketDataApp\Exceptions\ApiException::class);
        $this->expectExceptionMessage('No data available');

        // Request 2-year range where both years have no data
        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5'
        );
    }

    /**
     * Test that rate limits are updated during async parallel execution.
     *
     * This specifically tests ClientBase line 256 - the rate limit assignment
     * inside the async promise handler when rate limit headers are present.
     */
    public function testCandles_automaticConcurrent_updatesRateLimits(): void
    {
        $resetTimestamp = time() + 3600;
        $rateLimitHeaders = [
            'x-api-ratelimit-limit'     => ['100'],
            'x-api-ratelimit-remaining' => ['95'],
            'x-api-ratelimit-reset'     => [(string)$resetTimestamp],
            'x-api-ratelimit-consumed'  => ['5'],
        ];

        // Mock response: FROM real API output (captured on 2026-01-23)
        // Using real candles data with rate limit headers added
        $response1 = [
            's' => 'ok',
            't' => [1641220200, 1641220500],
            'o' => [177.83, 178.97],
            'h' => [179.31, 180.4],
            'l' => [177.71, 178.92],
            'c' => [178.965, 180.33],
            'v' => [3342579, 2482107],
        ];

        // Mock response: FROM real API output (captured on 2026-01-23)
        $response2 = [
            's' => 'ok',
            't' => [1672756200, 1672756500],
            'o' => [130.28, 129.83],
            'h' => [130.6999, 130.68],
            'l' => [129.44, 129.53],
            'c' => [129.84, 130.5],
            'v' => [3826842, 2219751],
        ];

        $this->setMockResponses([
            new Response(200, $rateLimitHeaders, json_encode($response1)),
            new Response(200, $rateLimitHeaders, json_encode($response2)),
        ]);

        // Verify rate limits are null before the request
        $this->assertNull($this->client->rate_limits);

        // Make concurrent request that triggers async execution
        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5'
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('ok', $result->status);

        // Verify rate limits were updated from async response headers
        $this->assertNotNull($this->client->rate_limits);
        $this->assertEquals(100, $this->client->rate_limits->limit);
        $this->assertEquals(95, $this->client->rate_limits->remaining);
        $this->assertEquals(5, $this->client->rate_limits->consumed);
    }

    /**
     * Test that filename parameter throws exception when used with parallel requests.
     *
     * This tests UniversalParameters lines 173-177 - the filename validation
     * exception that is thrown when a filename parameter is used with parallel requests.
     */
    public function testCandles_automaticConcurrent_filenameThrowsException(): void
    {
        // No mock responses needed - exception should be thrown before any requests are made

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('filename parameter cannot be used with parallel requests');

        // Attempt to use filename with a large date range that triggers parallel execution
        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(
                format: Format::CSV,
                filename: '/tmp/test_output.csv'
            )
        );
    }

    /**
     * Test that use_human_readable parameter is passed correctly in parallel requests.
     *
     * This tests UniversalParameters line 185 - the human readable parameter
     * being applied to each parallel request.
     */
    public function testCandles_automaticConcurrent_withHumanReadable(): void
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        $response1 = [
            's' => 'ok',
            't' => [1641220200],
            'o' => [177.83],
            'h' => [179.31],
            'l' => [177.71],
            'c' => [178.965],
            'v' => [3342579],
        ];

        // Mock response: FROM real API output (captured on 2026-01-23)
        $response2 = [
            's' => 'ok',
            't' => [1672756200],
            'o' => [130.28],
            'h' => [130.6999],
            'l' => [129.44],
            'c' => [129.84],
            'v' => [3826842],
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(
                format: Format::JSON,
                use_human_readable: true
            )
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('ok', $result->status);
        $this->assertCount(2, $result->candles);
    }

    /**
     * Test that mode parameter is passed correctly in parallel requests.
     *
     * This tests UniversalParameters line 189 - the mode parameter
     * being applied to each parallel request.
     */
    public function testCandles_automaticConcurrent_withMode(): void
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        $response1 = [
            's' => 'ok',
            't' => [1641220200],
            'o' => [177.83],
            'h' => [179.31],
            'l' => [177.71],
            'c' => [178.965],
            'v' => [3342579],
        ];

        // Mock response: FROM real API output (captured on 2026-01-23)
        $response2 = [
            's' => 'ok',
            't' => [1672756200],
            'o' => [130.28],
            'h' => [130.6999],
            'l' => [129.44],
            'c' => [129.84],
            'v' => [3826842],
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(
                format: Format::JSON,
                mode: Mode::LIVE
            )
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('ok', $result->status);
        $this->assertCount(2, $result->candles);
    }

    /**
     * Test that date_format parameter is passed correctly in parallel CSV requests.
     *
     * This tests UniversalParameters line 194 - the date_format parameter
     * being applied to each parallel request when format is CSV.
     *
     * Uses reflection to call execute_in_parallel directly since candlesConcurrent
     * doesn't support CSV format (it tries to merge responses as Candles objects).
     */
    public function testExecuteInParallel_withDateFormatCsv(): void
    {
        // Mock response: NOT from real API output (synthetic CSV response)
        $csvResponse1 = "t,o,h,l,c,v\n1641220200,177.83,179.31,177.71,178.965,3342579";
        $csvResponse2 = "t,o,h,l,c,v\n1672756200,130.28,130.6999,129.44,129.84,3826842";

        $this->setMockResponses([
            new Response(200, [], $csvResponse1),
            new Response(200, [], $csvResponse2),
        ]);

        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('execute_in_parallel');

        // Build calls similar to what candlesConcurrent would build
        $calls = [
            ['candles/5/AAPL/', ['from' => '2022-01-01', 'to' => '2022-12-31']],
            ['candles/5/AAPL/', ['from' => '2023-01-01', 'to' => '2023-12-31']],
        ];

        $parameters = new Parameters(
            format: Format::CSV,
            date_format: DateFormat::UNIX
        );

        $results = $method->invoke($stocks, $calls, $parameters);

        // Verify we got CSV responses back
        $this->assertCount(2, $results);
        $this->assertIsObject($results[0]);
        $this->assertTrue(property_exists($results[0], 'csv'));
    }

    /**
     * Test that columns parameter is passed correctly in parallel CSV requests.
     *
     * This tests UniversalParameters line 199 - the columns parameter
     * being applied to each parallel request when format is CSV.
     */
    public function testExecuteInParallel_withColumnsCsv(): void
    {
        // Mock response: NOT from real API output (synthetic CSV response)
        $csvResponse1 = "t,o,c\n1641220200,177.83,178.965";
        $csvResponse2 = "t,o,c\n1672756200,130.28,129.84";

        $this->setMockResponses([
            new Response(200, [], $csvResponse1),
            new Response(200, [], $csvResponse2),
        ]);

        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('execute_in_parallel');

        $calls = [
            ['candles/5/AAPL/', ['from' => '2022-01-01', 'to' => '2022-12-31']],
            ['candles/5/AAPL/', ['from' => '2023-01-01', 'to' => '2023-12-31']],
        ];

        $parameters = new Parameters(
            format: Format::CSV,
            columns: ['t', 'o', 'c']
        );

        $results = $method->invoke($stocks, $calls, $parameters);

        // Verify we got CSV responses back
        $this->assertCount(2, $results);
        $this->assertIsObject($results[0]);
        $this->assertTrue(property_exists($results[0], 'csv'));
    }

    /**
     * Test that add_headers parameter is passed correctly in parallel CSV requests.
     *
     * This tests UniversalParameters line 204 - the add_headers parameter
     * being applied to each parallel request when format is CSV.
     */
    public function testExecuteInParallel_withAddHeadersCsv(): void
    {
        // Mock response: NOT from real API output (synthetic CSV response)
        $csvResponse1 = "1641220200,177.83,179.31,177.71,178.965,3342579";
        $csvResponse2 = "1672756200,130.28,130.6999,129.44,129.84,3826842";

        $this->setMockResponses([
            new Response(200, [], $csvResponse1),
            new Response(200, [], $csvResponse2),
        ]);

        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('execute_in_parallel');

        $calls = [
            ['candles/5/AAPL/', ['from' => '2022-01-01', 'to' => '2022-12-31']],
            ['candles/5/AAPL/', ['from' => '2023-01-01', 'to' => '2023-12-31']],
        ];

        $parameters = new Parameters(
            format: Format::CSV,
            add_headers: false
        );

        $results = $method->invoke($stocks, $calls, $parameters);

        // Verify we got CSV responses back
        $this->assertCount(2, $results);
        $this->assertIsObject($results[0]);
        $this->assertTrue(property_exists($results[0], 'csv'));
    }

    /**
     * Test that multiple CSV parameters work together in parallel requests.
     *
     * This tests all CSV-specific parameters (date_format, columns, add_headers)
     * being applied together in parallel requests.
     */
    public function testExecuteInParallel_withAllCsvParameters(): void
    {
        // Mock response: NOT from real API output (synthetic CSV response)
        $csvResponse1 = "t,o,c\n1641220200,177.83,178.965";
        $csvResponse2 = "t,o,c\n1672756200,130.28,129.84";

        $this->setMockResponses([
            new Response(200, [], $csvResponse1),
            new Response(200, [], $csvResponse2),
        ]);

        $stocks = $this->client->stocks;
        $reflection = new \ReflectionClass($stocks);
        $method = $reflection->getMethod('execute_in_parallel');

        $calls = [
            ['candles/5/AAPL/', ['from' => '2022-01-01', 'to' => '2022-12-31']],
            ['candles/5/AAPL/', ['from' => '2023-01-01', 'to' => '2023-12-31']],
        ];

        $parameters = new Parameters(
            format: Format::CSV,
            date_format: DateFormat::UNIX,
            columns: ['t', 'o', 'c'],
            add_headers: true
        );

        $results = $method->invoke($stocks, $calls, $parameters);

        // Verify we got CSV responses back
        $this->assertCount(2, $results);
        $this->assertIsObject($results[0]);
        $this->assertTrue(property_exists($results[0], 'csv'));
    }

    /**
     * Test that HTML format throws exception for split requests.
     *
     * Bug #016: HTML format is not supported for intraday candle requests spanning
     * more than 1 year because the API doesn't support HTML for combined results.
     */
    public function testCandles_automaticConcurrent_htmlFormatThrowsException(): void
    {
        // No mock responses needed - exception should be thrown before any requests are made

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('HTML format is not supported for intraday candle requests spanning more than 1 year');

        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(format: Format::HTML)
        );
    }

    /**
     * Test that CSV format works correctly with split requests.
     *
     * Bug #016: CSV format should combine individual CSV responses correctly,
     * with headers only on the first request.
     */
    public function testCandles_automaticConcurrent_csvFormat(): void
    {
        // Mock response: NOT from real API output (synthetic CSV response for testing)
        $csvResponse1 = "t,o,h,l,c,v\n1641220200,177.83,179.31,177.71,178.965,3342579\n1641220500,178.97,180.4,178.92,180.33,2482107";
        $csvResponse2 = "1672756200,130.28,130.6999,129.44,129.84,3826842\n1672756500,129.83,130.68,129.53,130.5,2219751";

        $this->setMockResponses([
            new Response(200, [], $csvResponse1),
            new Response(200, [], $csvResponse2),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Candles::class, $result);
        // Should be able to get CSV content
        $csv = $result->getCsv();
        $this->assertNotEmpty($csv);
        // Should contain both sets of data combined
        $this->assertStringContainsString('1641220200', $csv);
        $this->assertStringContainsString('1672756200', $csv);
    }

    /**
     * Test that CSV format respects user's add_headers=false setting.
     *
     * Bug #016: When user explicitly requests no headers, all requests should omit headers.
     */
    public function testCandles_automaticConcurrent_csvFormatNoHeaders(): void
    {
        // Mock response: NOT from real API output (synthetic CSV response for testing)
        // No headers in either response since user requested no headers
        $csvResponse1 = "1641220200,177.83,179.31,177.71,178.965,3342579";
        $csvResponse2 = "1672756200,130.28,130.6999,129.44,129.84,3826842";

        $this->setMockResponses([
            new Response(200, [], $csvResponse1),
            new Response(200, [], $csvResponse2),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(format: Format::CSV, add_headers: false)
        );

        $this->assertInstanceOf(Candles::class, $result);
        $csv = $result->getCsv();
        $this->assertNotEmpty($csv);
        // Should NOT contain header row
        $this->assertStringNotContainsString('t,o,h,l,c,v', $csv);
        // Should contain data rows
        $this->assertStringContainsString('1641220200', $csv);
        $this->assertStringContainsString('1672756200', $csv);
    }

    /**
     * Test that CSV format handles partial failures gracefully.
     *
     * Bug #016: When some chunks fail with 404, the successful chunks should still
     * be combined into the output.
     */
    public function testCandles_automaticConcurrent_csvFormatPartialFailure(): void
    {
        // Mock response: NOT from real API output (synthetic CSV response for testing)
        $csvResponse1 = "t,o,h,l,c,v\n1641220200,177.83,179.31,177.71,178.965,3342579";

        // Second chunk returns 404 (simulating no historical data for that year)
        $request = new \GuzzleHttp\Psr7\Request('GET', 'https://api.marketdata.app/v1/stocks/candles/5/AAPL/');
        $response404 = new Response(404, [], json_encode(['s' => 'error', 'errmsg' => 'No data available']));

        $this->setMockResponses([
            new Response(200, [], $csvResponse1),
            new \GuzzleHttp\Exception\ClientException('Not Found', $request, $response404),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Candles::class, $result);
        $csv = $result->getCsv();
        // Should contain data from the successful chunk only
        $this->assertStringContainsString('1641220200', $csv);
    }

    /**
     * Test that CSV format throws exception when ALL chunks fail.
     *
     * Bug #016: When every chunk returns a failure, an exception should be thrown.
     */
    public function testCandles_automaticConcurrent_csvFormatAllFailures(): void
    {
        $request = new \GuzzleHttp\Psr7\Request('GET', 'https://api.marketdata.app/v1/stocks/candles/5/AAPL/');
        $response404 = new Response(404, [], json_encode(['s' => 'error', 'errmsg' => 'No data available']));

        $this->setMockResponses([
            new \GuzzleHttp\Exception\ClientException('Not Found', $request, $response404),
            new \GuzzleHttp\Exception\ClientException('Not Found', $request, $response404),
        ]);

        $this->expectException(\MarketDataApp\Exceptions\ApiException::class);
        $this->expectExceptionMessage('No data available');

        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(format: Format::CSV)
        );
    }

    /**
     * Test that CSV format works with 3+ year range (multiple chunks).
     *
     * Bug #016: CSV format should combine many chunks correctly.
     */
    public function testCandles_automaticConcurrent_csvFormatThreeYears(): void
    {
        // Mock response: NOT from real API output (synthetic CSV response for testing)
        $csvResponse1 = "t,o,h,l,c,v\n1609770600,133.52,133.6116,132.39,132.81,4815264";
        $csvResponse2 = "1641220200,177.83,179.31,177.71,178.965,3342579";
        $csvResponse3 = "1672756200,130.28,130.6999,129.44,129.84,3826842";

        $this->setMockResponses([
            new Response(200, [], $csvResponse1),
            new Response(200, [], $csvResponse2),
            new Response(200, [], $csvResponse3),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2021-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Candles::class, $result);
        $csv = $result->getCsv();
        // Should contain all three years of data
        $this->assertStringContainsString('1609770600', $csv);  // 2021
        $this->assertStringContainsString('1641220200', $csv);  // 2022
        $this->assertStringContainsString('1672756200', $csv);  // 2023
        // Should only have one header row
        $this->assertEquals(1, substr_count($csv, 't,o,h,l,c,v'));
    }

    /**
     * Test that CSV format passes date_format parameter correctly.
     *
     * Bug #016: CSV-specific parameters like date_format should be preserved.
     */
    public function testCandles_automaticConcurrent_csvFormatWithDateFormat(): void
    {
        // Mock response: NOT from real API output (synthetic CSV response for testing)
        $csvResponse1 = "t,o,h,l,c,v\n2022-01-03T09:30:00Z,177.83,179.31,177.71,178.965,3342579";
        $csvResponse2 = "2023-01-03T09:30:00Z,130.28,130.6999,129.44,129.84,3826842";

        $this->setMockResponses([
            new Response(200, [], $csvResponse1),
            new Response(200, [], $csvResponse2),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(
                format: Format::CSV,
                date_format: DateFormat::TIMESTAMP
            )
        );

        $this->assertInstanceOf(Candles::class, $result);
        $csv = $result->getCsv();
        // Should contain ISO 8601 formatted dates
        $this->assertStringContainsString('2022-01-03T09:30:00Z', $csv);
        $this->assertStringContainsString('2023-01-03T09:30:00Z', $csv);
    }

    /**
     * Test that CSV format passes columns parameter correctly.
     *
     * Bug #016: CSV-specific parameters like columns should be preserved.
     */
    public function testCandles_automaticConcurrent_csvFormatWithColumns(): void
    {
        // Mock response: NOT from real API output (synthetic CSV response for testing)
        $csvResponse1 = "t,o,c\n1641220200,177.83,178.965";
        $csvResponse2 = "1672756200,130.28,129.84";

        $this->setMockResponses([
            new Response(200, [], $csvResponse1),
            new Response(200, [], $csvResponse2),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(
                format: Format::CSV,
                columns: ['t', 'o', 'c']
            )
        );

        $this->assertInstanceOf(Candles::class, $result);
        $csv = $result->getCsv();
        // Should only have the specified columns
        $this->assertStringContainsString('t,o,c', $csv);
        // Should NOT contain h,l,v columns
        $this->assertStringNotContainsString(',h,', $csv);
        $this->assertStringNotContainsString(',l,', $csv);
        $this->assertStringNotContainsString(',v', $csv);
    }

    /**
     * Test CSV format with extended=true parameter.
     *
     * This test covers line 621 in Stocks.php where extended=true is set
     * in the arguments for CSV split requests.
     */
    public function testCandles_automaticConcurrent_csvFormatWithExtended(): void
    {
        // Mock response: NOT from real API output (synthetic CSV response for testing)
        $csvResponse1 = "t,o,h,l,c,v\n1641220200,177.83,179.31,177.71,178.965,3342579";
        $csvResponse2 = "1672756200,130.28,130.6999,129.44,129.84,3826842";

        $this->setMockResponses([
            new Response(200, [], $csvResponse1),
            new Response(200, [], $csvResponse2),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            extended: true,
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Candles::class, $result);
        $csv = $result->getCsv();
        $this->assertStringContainsString('1641220200', $csv);
        $this->assertStringContainsString('1672756200', $csv);
    }

    /**
     * Test CSV format with adjust_splits=false parameter.
     *
     * This test covers line 624 in Stocks.php where adjustsplits is set
     * in the arguments for CSV split requests.
     */
    public function testCandles_automaticConcurrent_csvFormatWithAdjustSplits(): void
    {
        // Mock response: NOT from real API output (synthetic CSV response for testing)
        $csvResponse1 = "t,o,h,l,c,v\n1641220200,177.83,179.31,177.71,178.965,3342579";
        $csvResponse2 = "1672756200,130.28,130.6999,129.44,129.84,3826842";

        $this->setMockResponses([
            new Response(200, [], $csvResponse1),
            new Response(200, [], $csvResponse2),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            adjust_splits: false,
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Candles::class, $result);
        $csv = $result->getCsv();
        $this->assertStringContainsString('1641220200', $csv);
        $this->assertStringContainsString('1672756200', $csv);
    }

    /**
     * Test CSV format when all responses are empty (no data).
     *
     * This test covers lines 703-705 in Stocks.php where an ApiException is thrown
     * when there are no valid responses and no error messages.
     */
    public function testCandles_automaticConcurrent_csvFormatNoData(): void
    {
        // Mock responses that are empty (not JSON errors, just empty)
        $this->setMockResponses([
            new Response(200, [], ''),
            new Response(200, [], ''),
        ]);

        $this->expectException(\MarketDataApp\Exceptions\ApiException::class);
        $this->expectExceptionMessage('No data available for the requested date range');

        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(format: Format::CSV)
        );
    }

    /**
     * Test that filename parameter throws exception with parallel CSV requests.
     *
     * This test covers lines 176-180 in UniversalParameters.php where an exception
     * is thrown when filename is used with parallel requests.
     */
    public function testCandles_automaticConcurrent_csvFormatWithFilename_throwsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('filename parameter cannot be used with parallel requests');

        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(format: Format::CSV, filename: '/tmp/test.csv')
        );
    }

    /**
     * Test CSV format when ALL requests fail with non-404 HTTP errors.
     *
     * This test covers line 661 in Stocks.php where the first exception
     * is re-thrown when ALL parallel requests fail with exceptions.
     *
     * Key difference from testCandles_automaticConcurrent_csvFormatAllFailures:
     * - That test uses 404 errors which are handled specially (response body is parsed for error message)
     * - This test uses 401 errors which throw exceptions directly and go to $failedRequests
     */
    public function testCandles_automaticConcurrent_csvFormatAll401Failures(): void
    {
        // Use 401 Unauthorized which throws immediately (no retries, no special 404 handling)
        $request = new \GuzzleHttp\Psr7\Request('GET', 'https://api.marketdata.app/v1/stocks/candles/5/AAPL/');
        $response401 = new Response(401, [], json_encode(['s' => 'error', 'errmsg' => 'Unauthorized']));

        $this->setMockResponses([
            new \GuzzleHttp\Exception\ClientException('Unauthorized', $request, $response401),
            new \GuzzleHttp\Exception\ClientException('Unauthorized', $request, $response401),
        ]);

        $this->expectException(\MarketDataApp\Exceptions\UnauthorizedException::class);

        // Request 2-year range where both years fail with 401
        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(format: Format::CSV)
        );
    }

    /**
     * Test CSV format when some responses are empty and some fail with exceptions.
     *
     * This test covers line 701 in Stocks.php where an exception is thrown
     * when there's no valid CSV data, no JSON error messages, but there are failed requests.
     */
    public function testCandles_automaticConcurrent_csvFormatEmptyAndFailure(): void
    {
        // First request returns empty CSV (valid 200 response but no data)
        $emptyCsvResponse = new Response(200, [], '');

        // Second request fails with 401 (throws exception, stored in $failedRequests)
        $request = new \GuzzleHttp\Psr7\Request('GET', 'https://api.marketdata.app/v1/stocks/candles/5/AAPL/');
        $response401 = new Response(401, [], json_encode(['s' => 'error', 'errmsg' => 'Unauthorized']));

        $this->setMockResponses([
            $emptyCsvResponse,
            new \GuzzleHttp\Exception\ClientException('Unauthorized', $request, $response401),
        ]);

        // The exception from the failed request should be re-thrown
        $this->expectException(\MarketDataApp\Exceptions\UnauthorizedException::class);

        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(format: Format::CSV)
        );
    }

    /**
     * Test that maxage parameter is passed correctly in parallel requests.
     *
     * This tests UniversalParameters line 216 - the maxage parameter
     * being applied to each parallel request when using CACHED mode.
     */
    public function testCandles_automaticConcurrent_withMaxage(): void
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        $response1 = [
            's' => 'ok',
            't' => [1641220200],
            'o' => [177.83],
            'h' => [179.31],
            'l' => [177.71],
            'c' => [178.965],
            'v' => [3342579],
        ];

        // Mock response: FROM real API output (captured on 2026-01-23)
        $response2 = [
            's' => 'ok',
            't' => [1672756200],
            'o' => [130.28],
            'h' => [130.6999],
            'l' => [129.44],
            'c' => [129.84],
            'v' => [3826842],
        ];

        $this->setMockResponses([
            new Response(203, [], json_encode($response1)),
            new Response(203, [], json_encode($response2)),
        ]);

        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(
                format: Format::JSON,
                mode: Mode::CACHED,
                maxage: 300  // 5 minutes
            )
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('ok', $result->status);
        $this->assertCount(2, $result->candles);
    }

    /**
     * Test that maxage parameter is included in parallel CSV requests.
     *
     * This is a regression test for BUG-010 where maxage was dropped when
     * rebuilding Parameters for CSV parallel requests in candlesConcurrentCsv().
     *
     * Mock response: NOT from real API output (synthetic CSV response for testing)
     */
    public function testCandles_automaticConcurrent_csvFormat_includesMaxage(): void
    {
        $csvResponse1 = "t,o,h,l,c,v\n1641220200,177.83,179.31,177.71,178.965,3342579";
        $csvResponse2 = "1672756200,130.28,130.6999,129.44,129.84,3826842";

        $history = [];
        $this->setMockResponsesWithHistory([
            new Response(200, [], $csvResponse1),
            new Response(200, [], $csvResponse2),
        ], $history);

        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2022-01-01',
            to: '2023-12-31',
            resolution: '5',
            parameters: new Parameters(
                format: Format::CSV,
                mode: Mode::CACHED,
                maxage: 60
            )
        );

        // Verify both requests include maxage in query string
        $this->assertCount(2, $history);

        foreach ($history as $index => $entry) {
            $query = [];
            parse_str($entry['request']->getUri()->getQuery(), $query);
            $this->assertArrayHasKey('maxage', $query, "Request $index should include maxage parameter");
            $this->assertEquals('60', $query['maxage'], "Request $index maxage should equal 60");
        }
    }
}
