<?php

namespace MarketDataApp\Tests\Unit\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Endpoints\Responses\Stocks\Candle;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Settings;

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
     *
     * @dataProvider minutelyResolutionsProvider
     */
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
     *
     * @dataProvider hourlyResolutionsProvider
     */
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
     *
     * @dataProvider nonIntradayResolutionsProvider
     */
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
     *
     * @dataProvider validDatesProvider
     */
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
     *
     * @dataProvider relativeDatesProvider
     */
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
     *
     * @dataProvider invalidDatesProvider
     */
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
            adjust_splits: true,
            adjust_dividends: true
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
     * Test concurrent candles with exchange parameter.
     */
    public function testCandles_automaticConcurrent_withExchange(): void
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/5/AAPL/?from=2022-01-03&to=2022-01-03" (first candle)
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
        // curl "https://api.marketdata.app/v1/stocks/candles/5/AAPL/?from=2023-01-03&to=2023-01-03" (first candle)
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
            exchange: 'NASDAQ'
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('ok', $result->status);
        $this->assertCount(2, $result->candles);
    }

    /**
     * Test concurrent candles with country parameter.
     */
    public function testCandles_automaticConcurrent_withCountry(): void
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        // curl "https://api.marketdata.app/v1/stocks/candles/5/AAPL/?from=2022-01-03&to=2022-01-03" (first candle)
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
        // curl "https://api.marketdata.app/v1/stocks/candles/5/AAPL/?from=2023-01-03&to=2023-01-03" (first candle)
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
            country: 'US'
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('ok', $result->status);
        $this->assertCount(2, $result->candles);
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
     * Test candlesConcurrent limits to MAX_CONCURRENT_REQUESTS when chunks exceed limit.
     *
     * Tests the edge case where the date range generates more than the API-wide
     * MAX_CONCURRENT_REQUESTS limit of year-long chunks. The candlesConcurrent method
     * pre-limits chunks to this value, and execute_in_parallel enforces the hard limit.
     */
    public function testCandles_automaticConcurrent_maxConcurrentRequestsLimit(): void
    {
        // Mock response: NOT from real API output (synthetic edge case)
        // This test requires 50 mock responses to test the MAX_CONCURRENT_REQUESTS limit
        // Using synthetic data with incrementing values for each year chunk
        $responses = [];
        for ($i = 0; $i < Settings::MAX_CONCURRENT_REQUESTS; $i++) {
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

        // Request a 55-year range - should only make 50 requests
        $result = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '1970-01-01',
            to: '2024-12-31',
            resolution: '5'
        );

        $this->assertInstanceOf(Candles::class, $result);
        $this->assertEquals('ok', $result->status);
        // Should have 50 candles (one from each of the 50 chunks)
        $this->assertCount(Settings::MAX_CONCURRENT_REQUESTS, $result->candles);
    }
}
