<?php

namespace MarketDataApp\Tests\Unit\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Earning;
use MarketDataApp\Endpoints\Responses\Stocks\Earnings;
use MarketDataApp\Enums\Format;

/**
 * Test case for the Earnings endpoint of the Stocks API.
 */
class EarningsTest extends StocksTestCase
{
    /**
     * Test the earnings endpoint for a successful response.
     *
     * @return void
     */
    public function testEarnings_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = [
            's'              => 'ok',
            'symbol'         => ['AAPL', 'AAPL'],
            'fiscalYear'     => [2023, 2023],
            'fiscalQuarter'  => [1, 2],
            'date'           => [1672462800, 1680235200],
            'reportDate'     => [1675314000, 1683172800],
            'reportTime'     => ['after close', 'after close'],
            'currency'       => ['USD', 'USD'],
            'reportedEPS'    => [1.88, 1.52],
            'estimatedEPS'   => [1.95, 1.43],
            'surpriseEPS'    => [-0.07, 0.09],
            'surpriseEPSpct' => [-0.0359, 0.0629],
            'updated'        => [1768971600, 1768971600]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);
        $response = $this->client->stocks->earnings(symbol: 'AAPL', from: '2023-01-01');

        $this->assertInstanceOf(Earnings::class, $response);
        $this->assertEquals($response->status, $mocked_response['s']);
        $this->assertNotEmpty($response->earnings);

        for ($i = 0; $i < count($response->earnings); $i++) {
            $this->assertInstanceOf(Earning::class, $response->earnings[$i]);
            $this->assertEquals($mocked_response['symbol'][$i], $response->earnings[$i]->symbol);
            $this->assertEquals($mocked_response['fiscalYear'][$i], $response->earnings[$i]->fiscal_year);
            $this->assertEquals($mocked_response['fiscalQuarter'][$i], $response->earnings[$i]->fiscal_quarter);
            $this->assertEquals(Carbon::parse($mocked_response['date'][$i]), $response->earnings[$i]->date);
            $this->assertEquals(Carbon::parse($mocked_response['reportDate'][$i]),
                $response->earnings[$i]->report_date);
            $this->assertEquals($mocked_response['reportTime'][$i], $response->earnings[$i]->report_time);
            $this->assertEquals($mocked_response['currency'][$i], $response->earnings[$i]->currency);
            $this->assertEquals($mocked_response['reportedEPS'][$i], $response->earnings[$i]->reported_eps);
            $this->assertEquals($mocked_response['estimatedEPS'][$i], $response->earnings[$i]->estimated_eps);
            $this->assertEquals($mocked_response['surpriseEPS'][$i], $response->earnings[$i]->surprise_eps);
            $this->assertEquals($mocked_response['surpriseEPSpct'][$i], $response->earnings[$i]->surprise_eps_pct);
            $this->assertEquals(Carbon::parse($mocked_response['updated'][$i]), $response->earnings[$i]->updated);
        }
    }

    /**
     * Test the earnings endpoint for a successful CSV response.
     *
     * @return void
     */
    public function testEarnings_csv_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = "symbol,fiscalYear,fiscalQuarter,date,reportDate,reportTime,currency,reportedEPS,estimatedEPS,surpriseEPS,surpriseEPSpct,updated\nAAPL,2023,1,1672462800,1675314000,after close,USD,1.88,1.95,-0.07,-0.0359,1768971600";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);
        $response = $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2023-01-01',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Earnings::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the earnings endpoint with human-readable format.
     *
     * @return void
     */
    public function testEarnings_humanReadable_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = [
            'Symbol' => ['AAPL', 'AAPL'],
            'Fiscal Year' => [2023, 2023],
            'Fiscal Quarter' => [1, 2],
            'Date' => [1672462800, 1680235200],
            'Report Date' => [1675314000, 1683172800],
            'Report Time' => ['after close', 'after close'],
            'Currency' => ['USD', 'USD'],
            'Reported EPS' => [1.88, 1.52],
            'Estimated EPS' => [1.95, 1.43],
            'Surprise EPS' => [-0.07, 0.09],
            'Surprise EPS %' => [-0.0359, 0.0629],
            'Updated' => [1768971600, 1768971600]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);
        $response = $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2023-01-01',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Earnings::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(2, $response->earnings);
        $this->assertEquals($mocked_response['Symbol'][0], $response->earnings[0]->symbol);
        $this->assertEquals($mocked_response['Fiscal Year'][0], $response->earnings[0]->fiscal_year);
        $this->assertEquals($mocked_response['Fiscal Quarter'][0], $response->earnings[0]->fiscal_quarter);
    }

    /**
     * Test the earnings endpoint works without date parameters.
     *
     * The API returns recent/upcoming earnings when no date parameters are provided.
     *
     * @return void
     */
    public function testEarnings_withoutDateParams_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-25)
        $mocked_response = [
            's'              => 'ok',
            'symbol'         => ['AAPL', 'AAPL'],
            'fiscalYear'     => [2026, 2026],
            'fiscalQuarter'  => [1, 2],
            'date'           => [1767157200, 1774929600],
            'reportDate'     => [1769662800, 1777435200],
            'reportTime'     => ['after close', 'before open'],
            'currency'       => ['USD', null],
            'reportedEPS'    => [null, null],
            'estimatedEPS'   => [2.67, null],
            'surpriseEPS'    => [null, null],
            'surpriseEPSpct' => [null, null],
            'updated'        => [1769317200, 1769317200]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        // Call without any date parameters - should work fine
        $response = $this->client->stocks->earnings(symbol: 'AAPL');

        $this->assertInstanceOf(Earnings::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(2, $response->earnings);
        $this->assertEquals('AAPL', $response->earnings[0]->symbol);
        $this->assertEquals(2026, $response->earnings[0]->fiscal_year);
    }

    /**
     * Test earnings endpoint with invalid date range.
     */
    public function testEarnings_invalidDateRange_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`from` date must be before `to` date');

        $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2024-01-31',
            to: '2024-01-01'
        );
    }

    /**
     * Test earnings endpoint with invalid countback.
     */
    public function testEarnings_invalidCountback_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`countback` must be a positive integer');

        $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2024-01-01',
            to: '2024-01-31',
            countback: -5
        );
    }

    /**
     * Test that earnings properties are accessible for CSV responses (BUG-013 fix).
     *
     * CSV responses trigger an early return in the constructor. Properties should
     * have default values to prevent "uninitialized property" errors.
     *
     * @return void
     */
    public function testEarnings_csv_propertiesAccessible(): void
    {
        // Mock response: NOT from real API output (uses synthetic CSV data)
        $csvResponse = "symbol,fiscalYear,fiscalQuarter,date,reportDate,reportTime,currency,reportedEPS,estimatedEPS,surpriseEPS,surpriseEPSpct,updated\nAAPL,2024,1,1704067200,1706745600,amc,USD,2.18,2.10,0.08,3.81,1706832000";
        $this->setMockResponses([new Response(200, [], $csvResponse)]);

        $response = $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2024-01-01',
            parameters: new Parameters(format: Format::CSV)
        );

        // These should NOT throw "uninitialized property" errors
        $this->assertEquals('no_data', $response->status);
        $this->assertIsArray($response->earnings);
        $this->assertCount(0, $response->earnings);
    }

    /**
     * Test that earnings properties are accessible for no_data responses (BUG-013 fix).
     *
     * no_data responses skip property initialization. Properties should
     * have default values to prevent "uninitialized property" errors.
     *
     * @return void
     */
    public function testEarnings_noData_propertiesAccessible(): void
    {
        // Mock response: NOT from real API output (uses synthetic no_data response)
        $noDataResponse = ['s' => 'no_data'];
        $this->setMockResponses([new Response(200, [], json_encode($noDataResponse))]);

        $response = $this->client->stocks->earnings(
            symbol: 'AAPL',
            from: '2099-01-01',
            to: '2099-12-31'
        );

        // These should NOT throw "uninitialized property" errors
        $this->assertEquals('no_data', $response->status);
        $this->assertIsArray($response->earnings);
        $this->assertCount(0, $response->earnings);
    }
}
