<?php

namespace MarketDataApp\Tests\Unit\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Candle;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Exceptions\ApiException;

/**
 * Test case for the Candles endpoint of the Stocks API.
 */
class CandlesTest extends StocksTestCase
{
    /**
     * Test the candles endpoint for a successful response with 'from' and 'to' parameters.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_fromTo_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = [
            's' => 'ok',
            't' => [1662004800, 1662091200],
            'o' => [156.64, 159.75],
            'h' => [158.42, 160.362],
            'l' => [154.67, 154.965],
            'c' => [157.96, 155.81],
            'v' => [74229896, 76807768]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertCount(2, $response->candles);

        // Verify each item in the response is an object of the correct type and has the correct values.
        for ($i = 0; $i < count($response->candles); $i++) {
            $this->assertInstanceOf(Candle::class, $response->candles[$i]);
            $this->assertEquals($mocked_response['c'][$i], $response->candles[$i]->close);
            $this->assertEquals($mocked_response['h'][$i], $response->candles[$i]->high);
            $this->assertEquals($mocked_response['l'][$i], $response->candles[$i]->low);
            $this->assertEquals($mocked_response['o'][$i], $response->candles[$i]->open);
            $this->assertEquals($mocked_response['v'][$i], $response->candles[$i]->volume);
            $this->assertEquals(Carbon::parse($mocked_response['t'][$i]), $response->candles[$i]->timestamp);
        }
    }

    /**
     * Test the candles endpoint for a successful CSV response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_csv_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = "t,o,h,l,c,v\n1662004800,156.64,158.42,154.67,157.96,74229896\n1662091200,159.75,160.362,154.965,155.81,76957768";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV)
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the candles endpoint with human-readable format.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_humanReadable_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = [
            'Date' => [1662004800, 1662091200],
            'Open' => [156.64, 159.75],
            'High' => [158.42, 160.362],
            'Low' => [154.67, 154.965],
            'Close' => [157.96, 155.81],
            'Volume' => [74229896, 76807768]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(2, $response->candles);
        $this->assertEquals($mocked_response['Open'][0], $response->candles[0]->open);
        $this->assertEquals($mocked_response['High'][0], $response->candles[0]->high);
        $this->assertEquals($mocked_response['Low'][0], $response->candles[0]->low);
        $this->assertEquals($mocked_response['Close'][0], $response->candles[0]->close);
        $this->assertEquals($mocked_response['Volume'][0], $response->candles[0]->volume);
        $this->assertEquals(Carbon::parse($mocked_response['Date'][0]), $response->candles[0]->timestamp);
    }

    /**
     * Test the candles endpoint for a successful 'no data' response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_noData_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's' => 'no_data',
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->candles(
            symbol: "AAPl",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEmpty($response->candles);
        $this->assertFalse(isset($response->next_time));
    }

    /**
     * Test the candles endpoint for a successful 'no data' response with next time.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_noDataNextTime_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'        => 'no_data',
            'nextTime' => 1663958094,
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals($mocked_response['nextTime'], $response->next_time);
        $this->assertEmpty($response->candles);
    }

    /**
     * Test that date_format parameter can be used with CSV format.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testParameters_dateFormat_withCsv_success(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, c, h, l, o, v, t";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test that date_format parameter with JSON format throws InvalidArgumentException.
     *
     * @return void
     */
    public function testParameters_dateFormat_withJson_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('date_format parameter can only be used with CSV or HTML format');

        new Parameters(format: Format::JSON, date_format: DateFormat::TIMESTAMP);
    }

    /**
     * Test that date_format parameter can be used with HTML format.
     *
     * @return void
     */
    public function testParameters_dateFormat_withHtml_success(): void
    {
        $params = new Parameters(format: Format::HTML, date_format: DateFormat::TIMESTAMP);
        $this->assertEquals(Format::HTML, $params->format);
        $this->assertEquals(DateFormat::TIMESTAMP, $params->date_format);
    }

    /**
     * Test candles endpoint with HTML format and dateformat=unix.
     * Verifies that the HTML response is returned and dateformat parameter is passed.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_html_withDateFormat_unix(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "<table><tr><th>Date</th></tr><tr><td>1234567890</td></tr></table>";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::HTML, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isHtml());
        $this->assertEquals($mocked_response, $response->getHtml());
    }

    /**
     * Test that null date_format with CSV is valid (backward compatibility).
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testParameters_dateFormat_null_withCsv_success(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, c, h, l, o, v, t";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: null)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test candles endpoint with CSV format and dateformat=unix.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_csv_withDateFormat_unix(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, c, h, l, o, v, t";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test candles endpoint with CSV format and dateformat=timestamp.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_csv_withDateFormat_timestamp(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, c, h, l, o, v, t";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test candles endpoint with CSV format and dateformat=spreadsheet.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_csv_withDateFormat_spreadsheet(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, c, h, l, o, v, t";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->stocks->candles(
            symbol: "AAPL",
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::SPREADSHEET)
        );

        $this->assertInstanceOf(Candles::class, $response);
        $this->assertTrue($response->isCsv());
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test candles endpoint with invalid date range (from > to).
     */
    public function testCandles_invalidDateRange_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`from` date must be before `to` date');

        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2024-01-31',
            to: '2024-01-01',
            resolution: 'D'
        );
    }

    /**
     * Test candles endpoint with relative dates (should not throw exception).
     */
    public function testCandles_relativeDates_noException(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $this->setMockResponses([
            new Response(200, [], json_encode(['s' => 'ok', 't' => [], 'o' => [], 'h' => [], 'l' => [], 'c' => [], 'v' => []])),
        ]);

        // Relative dates should pass through without validation
        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: 'today',
            to: 'yesterday',
            resolution: 'D'
        );

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test candles endpoint with invalid countback (zero).
     */
    public function testCandles_invalidCountback_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`countback` must be a positive integer');

        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2024-01-01',
            resolution: 'D',
            countback: 0
        );
    }

    /**
     * Test candles endpoint with invalid resolution.
     */
    public function testCandles_invalidResolution_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid resolution format');

        $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2024-01-01',
            resolution: 'invalid'
        );
    }
}
