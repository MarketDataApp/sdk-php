<?php

namespace MarketDataApp\Tests\Unit\Options;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\OptionQuote;
use MarketDataApp\Endpoints\Responses\Options\Quotes;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Side;

/**
 * Unit tests for the Options Quotes endpoint.
 */
class QuotesTest extends OptionsTestCase
{
    /**
     * Test the quotes endpoint for a successful response.
     */
    public function testQuotes_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL230616C00060000', 'AAPL230616C00065000'],
            'underlying'      => ['AAPL', 'AAPL'],
            'expiration'      => [1686873600, 1686873600],
            'side'            => ['call', 'call'],
            'strike'          => [60, 65],
            'firstTraded'     => [1617197400, 1617197400],
            'dte'             => [30, 30],
            'ask'             => [116.9, 112.15],
            'askSize'         => [90, 90],
            'bid'             => [114.1, 108.6],
            'bidSize'         => [90, 90],
            'mid'             => [115.5, 110.38],
            'last'            => [115, 107.82],
            'openInterest'    => [21957, 3012],
            'volume'          => [0, 0],
            'inTheMoney'      => [true, true],
            'underlyingPrice' => [175.13, 175.13],
            'iv'              => [1.629, 1.923],
            'delta'           => [1, 1],
            'gamma'           => [0, 0],
            'theta'           => [-0.009, -0.009],
            'vega'            => [0, 0],
            'intrinsicValue'  => [115.13, 110.13],
            'extrinsicValue'  => [0.37, 0.25],
            'updated'         => [1684702875, 1684702875],
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->quotes('AAPL250117C00150000');

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals($mocked_response['s'], $response->status);
        $this->assertCount(2, $response->quotes);

        for ($i = 0; $i < count($response->quotes); $i++) {
            $this->assertInstanceOf(OptionQuote::class, $response->quotes[$i]);
            $this->assertEquals($mocked_response['optionSymbol'][$i], $response->quotes[$i]->option_symbol);
            $this->assertEquals($mocked_response['ask'][$i], $response->quotes[$i]->ask);
            $this->assertEquals($mocked_response['askSize'][$i], $response->quotes[$i]->ask_size);
            $this->assertEquals($mocked_response['bid'][$i], $response->quotes[$i]->bid);
            $this->assertEquals($mocked_response['bidSize'][$i], $response->quotes[$i]->bid_size);
            $this->assertEquals($mocked_response['mid'][$i], $response->quotes[$i]->mid);
            $this->assertEquals($mocked_response['last'][$i], $response->quotes[$i]->last);
            $this->assertEquals($mocked_response['openInterest'][$i], $response->quotes[$i]->open_interest);
            $this->assertEquals($mocked_response['volume'][$i], $response->quotes[$i]->volume);
            $this->assertEquals($mocked_response['inTheMoney'][$i], $response->quotes[$i]->in_the_money);
            $this->assertEquals($mocked_response['underlyingPrice'][$i], $response->quotes[$i]->underlying_price);
            $this->assertEquals($mocked_response['iv'][$i], $response->quotes[$i]->implied_volatility);
            $this->assertEquals($mocked_response['delta'][$i], $response->quotes[$i]->delta);
            $this->assertEquals($mocked_response['gamma'][$i], $response->quotes[$i]->gamma);
            $this->assertEquals($mocked_response['theta'][$i], $response->quotes[$i]->theta);
            $this->assertEquals($mocked_response['vega'][$i], $response->quotes[$i]->vega);
            $this->assertEquals($mocked_response['intrinsicValue'][$i], $response->quotes[$i]->intrinsic_value);
            $this->assertEquals($mocked_response['extrinsicValue'][$i], $response->quotes[$i]->extrinsic_value);
            $this->assertEquals(Carbon::parse($mocked_response['updated'][$i]), $response->quotes[$i]->updated);
        }
    }

    /**
     * Test the quotes endpoint for a successful CSV response.
     */
    public function testQuotes_csv_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, optionSymbol, ask...\r\n";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->quotes(
            option_symbols: 'AAPL250117C00150000',
            parameters: new Parameters(Format::CSV)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test the quotes endpoint for a successful 'no data' response.
     */
    public function testQuotes_noData_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'        => 'no_data',
            'nextTime' => 1663704000,
            'prevTime' => 1663705000
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->quotes('AAPL');

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEmpty($response->quotes);
        $this->assertEquals(Carbon::parse($mocked_response['nextTime']), $response->next_time);
        $this->assertEquals(Carbon::parse($mocked_response['prevTime']), $response->prev_time);
    }

    /**
     * Test the quotes endpoint with human-readable format.
     */
    public function testQuotes_humanReadable_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'Symbol' => ['AAPL281215C00400000'],
            'Underlying' => ['AAPL'],
            'Expiration Date' => [1840579200],
            'Option Side' => ['call'],
            'Strike' => [400],
            'First Traded' => [1617197400],
            'Days To Expiration' => [100],
            'Date' => [1684702875],
            'Bid' => [114.1],
            'Bid Size' => [90],
            'Mid' => [115.5],
            'Ask' => [116.9],
            'Ask Size' => [90],
            'Last' => [115],
            'Open Interest' => [21957],
            'Volume' => [0],
            'In The Money' => [true],
            'Intrinsic Value' => [115.13],
            'Extrinsic Value' => [0.37],
            'Underlying Price' => [175.13],
            'IV' => [1.629],
            'Delta' => [1],
            'Gamma' => [0],
            'Theta' => [-0.009],
            'Vega' => [0]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->quotes(
            option_symbols: 'AAPL281215C00400000',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->quotes);
        $this->assertInstanceOf(OptionQuote::class, $response->quotes[0]);
        $this->assertEquals($mocked_response['Symbol'][0], $response->quotes[0]->option_symbol);
        $this->assertEquals($mocked_response['Ask'][0], $response->quotes[0]->ask);
        $this->assertEquals($mocked_response['Ask Size'][0], $response->quotes[0]->ask_size);
        $this->assertEquals($mocked_response['Bid'][0], $response->quotes[0]->bid);
        $this->assertEquals($mocked_response['Bid Size'][0], $response->quotes[0]->bid_size);
        $this->assertEquals($mocked_response['Mid'][0], $response->quotes[0]->mid);
        $this->assertEquals($mocked_response['Last'][0], $response->quotes[0]->last);
        $this->assertEquals($mocked_response['Volume'][0], $response->quotes[0]->volume);
        $this->assertEquals($mocked_response['Open Interest'][0], $response->quotes[0]->open_interest);
        $this->assertEquals($mocked_response['Underlying Price'][0], $response->quotes[0]->underlying_price);
        $this->assertEquals($mocked_response['In The Money'][0], $response->quotes[0]->in_the_money);
        $this->assertEquals($mocked_response['Intrinsic Value'][0], $response->quotes[0]->intrinsic_value);
        $this->assertEquals($mocked_response['Extrinsic Value'][0], $response->quotes[0]->extrinsic_value);
        $this->assertEquals($mocked_response['IV'][0], $response->quotes[0]->implied_volatility);
        $this->assertEquals($mocked_response['Delta'][0], $response->quotes[0]->delta);
        $this->assertEquals($mocked_response['Gamma'][0], $response->quotes[0]->gamma);
        $this->assertEquals($mocked_response['Theta'][0], $response->quotes[0]->theta);
        $this->assertEquals($mocked_response['Vega'][0], $response->quotes[0]->vega);
        $this->assertEquals(Carbon::parse($mocked_response['Date'][0]), $response->quotes[0]->updated);
    }

    /**
     * Test options quotes endpoint with CSV format and dateformat=unix.
     */
    public function testQuotes_csv_withDateFormat_unix(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, symbol, ask, bid";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->quotes(
            option_symbols: 'AAPL250117C00150000',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::UNIX)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertTrue($response->isCsv());
    }

    /**
     * Test options quotes endpoint with CSV format and dateformat=spreadsheet.
     */
    public function testQuotes_csv_withDateFormat_spreadsheet(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, symbol, ask, bid";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->quotes(
            option_symbols: 'AAPL250117C00150000',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::SPREADSHEET)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertTrue($response->isCsv());
    }

    /**
     * Test quotes endpoint with invalid date range.
     */
    public function testQuotes_invalidDateRange_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`from` date must be before `to` date');

        $this->client->options->quotes(
            option_symbols: 'AAPL250117C00150000',
            from: '2024-01-31',
            to: '2024-01-01'
        );
    }

    // =========================================================================
    // Multi-Symbol (Array) Tests
    // =========================================================================

    /**
     * Test quotes endpoint with multiple symbols returns merged response.
     */
    public function testQuotes_multipleSymbols_success(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $response1 = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL250117C00150000'],
            'underlying'      => ['AAPL'],
            'expiration'      => [1737072000],
            'side'            => ['call'],
            'strike'          => [150],
            'firstTraded'     => [1617197400],
            'dte'             => [30],
            'ask'             => [5.50],
            'askSize'         => [100],
            'bid'             => [5.40],
            'bidSize'         => [100],
            'mid'             => [5.45],
            'last'            => [5.45],
            'openInterest'    => [1000],
            'volume'          => [500],
            'inTheMoney'      => [false],
            'underlyingPrice' => [145.00],
            'iv'              => [0.25],
            'delta'           => [0.50],
            'gamma'           => [0.05],
            'theta'           => [-0.02],
            'vega'            => [0.10],
            'intrinsicValue'  => [0.00],
            'extrinsicValue'  => [5.45],
            'updated'         => [1684702875],
        ];

        $response2 = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL250117P00150000'],
            'underlying'      => ['AAPL'],
            'expiration'      => [1737072000],
            'side'            => ['put'],
            'strike'          => [150],
            'firstTraded'     => [1617197400],
            'dte'             => [30],
            'ask'             => [4.20],
            'askSize'         => [50],
            'bid'             => [4.10],
            'bidSize'         => [50],
            'mid'             => [4.15],
            'last'            => [4.15],
            'openInterest'    => [800],
            'volume'          => [300],
            'inTheMoney'      => [true],
            'underlyingPrice' => [145.00],
            'iv'              => [0.28],
            'delta'           => [-0.45],
            'gamma'           => [0.04],
            'theta'           => [-0.01],
            'vega'            => [0.08],
            'intrinsicValue'  => [5.00],
            'extrinsicValue'  => [-0.85],
            'updated'         => [1684702880],
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);

        $response = $this->client->options->quotes([
            'AAPL250117C00150000',
            'AAPL250117P00150000',
        ]);

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(2, $response->quotes);

        // Verify both quotes are present
        $this->assertEquals('AAPL250117C00150000', $response->quotes[0]->option_symbol);
        $this->assertEquals('AAPL250117P00150000', $response->quotes[1]->option_symbol);
    }

    /**
     * Test quotes endpoint with single symbol array delegates to single request.
     */
    public function testQuotes_singleSymbolArray_delegatesToSingleRequest(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL250117C00150000'],
            'underlying'      => ['AAPL'],
            'expiration'      => [1737072000],
            'side'            => ['call'],
            'strike'          => [150],
            'firstTraded'     => [1617197400],
            'dte'             => [30],
            'ask'             => [5.50],
            'askSize'         => [100],
            'bid'             => [5.40],
            'bidSize'         => [100],
            'mid'             => [5.45],
            'last'            => [5.45],
            'openInterest'    => [1000],
            'volume'          => [500],
            'inTheMoney'      => [false],
            'underlyingPrice' => [145.00],
            'iv'              => [0.25],
            'delta'           => [0.50],
            'gamma'           => [0.05],
            'theta'           => [-0.02],
            'vega'            => [0.10],
            'intrinsicValue'  => [0.00],
            'extrinsicValue'  => [5.45],
            'updated'         => [1684702875],
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->quotes(['AAPL250117C00150000']);

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->quotes);
    }

    /**
     * Test quotes endpoint with duplicate symbols deduplicates.
     */
    public function testQuotes_duplicateSymbols_deduplicated(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL250117C00150000'],
            'underlying'      => ['AAPL'],
            'expiration'      => [1737072000],
            'side'            => ['call'],
            'strike'          => [150],
            'firstTraded'     => [1617197400],
            'dte'             => [30],
            'ask'             => [5.50],
            'askSize'         => [100],
            'bid'             => [5.40],
            'bidSize'         => [100],
            'mid'             => [5.45],
            'last'            => [5.45],
            'openInterest'    => [1000],
            'volume'          => [500],
            'inTheMoney'      => [false],
            'underlyingPrice' => [145.00],
            'iv'              => [0.25],
            'delta'           => [0.50],
            'gamma'           => [0.05],
            'theta'           => [-0.02],
            'vega'            => [0.10],
            'intrinsicValue'  => [0.00],
            'extrinsicValue'  => [5.45],
            'updated'         => [1684702875],
        ];
        // Only one request should be made (duplicates removed, single symbol delegates)
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->quotes([
            'AAPL250117C00150000',
            'AAPL250117C00150000',
            ' AAPL250117C00150000 ', // With whitespace
        ]);

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertCount(1, $response->quotes);
    }

    /**
     * Test quotes endpoint with empty array throws exception.
     */
    public function testQuotes_emptyArray_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`option_symbols` array cannot be empty');

        $this->client->options->quotes([]);
    }

    /**
     * Test quotes endpoint with array containing empty string throws exception.
     */
    public function testQuotes_arrayWithEmptyString_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All elements in `option_symbols` must be non-empty strings');

        $this->client->options->quotes(['AAPL250117C00150000', '']);
    }

    /**
     * Test quotes endpoint with array containing non-string throws exception.
     */
    public function testQuotes_arrayWithNonString_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All elements in `option_symbols` must be non-empty strings');

        $this->client->options->quotes(['AAPL250117C00150000', 123]);
    }

    /**
     * Test quotes endpoint with partial no_data returns ok status.
     */
    public function testQuotes_partialNoData_returnsOkStatus(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $okResponse = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL250117C00150000'],
            'underlying'      => ['AAPL'],
            'expiration'      => [1737072000],
            'side'            => ['call'],
            'strike'          => [150],
            'firstTraded'     => [1617197400],
            'dte'             => [30],
            'ask'             => [5.50],
            'askSize'         => [100],
            'bid'             => [5.40],
            'bidSize'         => [100],
            'mid'             => [5.45],
            'last'            => [5.45],
            'openInterest'    => [1000],
            'volume'          => [500],
            'inTheMoney'      => [false],
            'underlyingPrice' => [145.00],
            'iv'              => [0.25],
            'delta'           => [0.50],
            'gamma'           => [0.05],
            'theta'           => [-0.02],
            'vega'            => [0.10],
            'intrinsicValue'  => [0.00],
            'extrinsicValue'  => [5.45],
            'updated'         => [1684702875],
        ];

        $noDataResponse = [
            's'        => 'no_data',
            'nextTime' => 1663704000,
            'prevTime' => 1663705000,
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($okResponse)),
            new Response(200, [], json_encode($noDataResponse)),
        ]);

        $response = $this->client->options->quotes([
            'AAPL250117C00150000',
            'INVALID_SYMBOL_XYZ',
        ]);

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->quotes);
    }

    /**
     * Test quotes endpoint with all no_data returns no_data status.
     */
    public function testQuotes_allNoData_returnsNoDataStatus(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $noDataResponse1 = [
            's'        => 'no_data',
            'nextTime' => 1663704000,
            'prevTime' => 1663705000,
        ];

        $noDataResponse2 = [
            's'        => 'no_data',
            'nextTime' => 1663703000,
            'prevTime' => 1663706000,
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($noDataResponse1)),
            new Response(200, [], json_encode($noDataResponse2)),
        ]);

        $response = $this->client->options->quotes([
            'INVALID_SYMBOL_1',
            'INVALID_SYMBOL_2',
        ]);

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('no_data', $response->status);
        $this->assertEmpty($response->quotes);
    }

    /**
     * Test quotes endpoint tracks earliest next_time from no_data responses.
     */
    public function testQuotes_tracksEarliestNextTime(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $noDataResponse1 = [
            's'        => 'no_data',
            'nextTime' => 1663704000, // Later
        ];

        $noDataResponse2 = [
            's'        => 'no_data',
            'nextTime' => 1663703000, // Earlier (should be kept)
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($noDataResponse1)),
            new Response(200, [], json_encode($noDataResponse2)),
        ]);

        $response = $this->client->options->quotes([
            'SYMBOL1',
            'SYMBOL2',
        ]);

        $this->assertEquals('no_data', $response->status);
        $this->assertEquals(Carbon::parse(1663703000), $response->next_time);
    }

    /**
     * Test quotes endpoint tracks latest prev_time from no_data responses.
     */
    public function testQuotes_tracksLatestPrevTime(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $noDataResponse1 = [
            's'        => 'no_data',
            'prevTime' => 1663705000, // Earlier
        ];

        $noDataResponse2 = [
            's'        => 'no_data',
            'prevTime' => 1663706000, // Later (should be kept)
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($noDataResponse1)),
            new Response(200, [], json_encode($noDataResponse2)),
        ]);

        $response = $this->client->options->quotes([
            'SYMBOL1',
            'SYMBOL2',
        ]);

        $this->assertEquals('no_data', $response->status);
        $this->assertEquals(Carbon::parse(1663706000), $response->prev_time);
    }

    /**
     * Test quotes endpoint with multiple symbols and date parameter.
     */
    public function testQuotes_multipleSymbols_withDate(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $response1 = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL250117C00150000'],
            'underlying'      => ['AAPL'],
            'expiration'      => [1737072000],
            'side'            => ['call'],
            'strike'          => [150],
            'firstTraded'     => [1617197400],
            'dte'             => [30],
            'ask'             => [5.50],
            'askSize'         => [100],
            'bid'             => [5.40],
            'bidSize'         => [100],
            'mid'             => [5.45],
            'last'            => [5.45],
            'openInterest'    => [1000],
            'volume'          => [500],
            'inTheMoney'      => [false],
            'underlyingPrice' => [145.00],
            'iv'              => [0.25],
            'delta'           => [0.50],
            'gamma'           => [0.05],
            'theta'           => [-0.02],
            'vega'            => [0.10],
            'intrinsicValue'  => [0.00],
            'extrinsicValue'  => [5.45],
            'updated'         => [1684702875],
        ];

        $response2 = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL250117P00150000'],
            'underlying'      => ['AAPL'],
            'expiration'      => [1737072000],
            'side'            => ['put'],
            'strike'          => [150],
            'firstTraded'     => [1617197400],
            'dte'             => [30],
            'ask'             => [4.20],
            'askSize'         => [50],
            'bid'             => [4.10],
            'bidSize'         => [50],
            'mid'             => [4.15],
            'last'            => [4.15],
            'openInterest'    => [800],
            'volume'          => [300],
            'inTheMoney'      => [true],
            'underlyingPrice' => [145.00],
            'iv'              => [0.28],
            'delta'           => [-0.45],
            'gamma'           => [0.04],
            'theta'           => [-0.01],
            'vega'            => [0.08],
            'intrinsicValue'  => [5.00],
            'extrinsicValue'  => [-0.85],
            'updated'         => [1684702880],
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);

        $response = $this->client->options->quotes(
            option_symbols: ['AAPL250117C00150000', 'AAPL250117P00150000'],
            date: '2024-01-15'
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(2, $response->quotes);
    }

    /**
     * Test quotes endpoint with multiple symbols and date range.
     */
    public function testQuotes_multipleSymbols_withDateRange(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $response1 = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL250117C00150000', 'AAPL250117C00150000'],
            'underlying'      => ['AAPL', 'AAPL'],
            'expiration'      => [1737072000, 1737072000],
            'side'            => ['call', 'call'],
            'strike'          => [150, 150],
            'firstTraded'     => [1617197400, 1617197400],
            'dte'             => [30, 29],
            'ask'             => [5.50, 5.60],
            'askSize'         => [100, 100],
            'bid'             => [5.40, 5.50],
            'bidSize'         => [100, 100],
            'mid'             => [5.45, 5.55],
            'last'            => [5.45, 5.55],
            'openInterest'    => [1000, 1000],
            'volume'          => [500, 600],
            'inTheMoney'      => [false, false],
            'underlyingPrice' => [145.00, 146.00],
            'iv'              => [0.25, 0.26],
            'delta'           => [0.50, 0.51],
            'gamma'           => [0.05, 0.05],
            'theta'           => [-0.02, -0.02],
            'vega'            => [0.10, 0.10],
            'intrinsicValue'  => [0.00, 0.00],
            'extrinsicValue'  => [5.45, 5.55],
            'updated'         => [1684702875, 1684789275],
        ];

        $response2 = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL250117P00150000', 'AAPL250117P00150000'],
            'underlying'      => ['AAPL', 'AAPL'],
            'expiration'      => [1737072000, 1737072000],
            'side'            => ['put', 'put'],
            'strike'          => [150, 150],
            'firstTraded'     => [1617197400, 1617197400],
            'dte'             => [30, 29],
            'ask'             => [4.20, 4.30],
            'askSize'         => [50, 50],
            'bid'             => [4.10, 4.20],
            'bidSize'         => [50, 50],
            'mid'             => [4.15, 4.25],
            'last'            => [4.15, 4.25],
            'openInterest'    => [800, 800],
            'volume'          => [300, 400],
            'inTheMoney'      => [true, true],
            'underlyingPrice' => [145.00, 146.00],
            'iv'              => [0.28, 0.29],
            'delta'           => [-0.45, -0.44],
            'gamma'           => [0.04, 0.04],
            'theta'           => [-0.01, -0.01],
            'vega'            => [0.08, 0.08],
            'intrinsicValue'  => [5.00, 4.00],
            'extrinsicValue'  => [-0.85, 0.25],
            'updated'         => [1684702880, 1684789280],
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);

        $response = $this->client->options->quotes(
            option_symbols: ['AAPL250117C00150000', 'AAPL250117P00150000'],
            from: '2024-01-01',
            to: '2024-01-15'
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        // Each response has 2 quotes (date range), so 4 total
        $this->assertCount(4, $response->quotes);
    }

    /**
     * Test quotes endpoint with many symbols (tests sliding window concurrency).
     */
    public function testQuotes_manySymbols_allProcessed(): void
    {
        // Mock responses: NOT from real API output (synthetic/test data)
        $responses = [];
        $symbolCount = 5; // Use 5 symbols to verify concurrent handling

        for ($i = 0; $i < $symbolCount; $i++) {
            $responses[] = new Response(200, [], json_encode([
                's'               => 'ok',
                'optionSymbol'    => ["AAPL25011{$i}C00150000"],
                'underlying'      => ['AAPL'],
                'expiration'      => [1737072000],
                'side'            => ['call'],
                'strike'          => [150],
                'firstTraded'     => [1617197400],
                'dte'             => [30],
                'ask'             => [5.50 + $i * 0.1],
                'askSize'         => [100],
                'bid'             => [5.40 + $i * 0.1],
                'bidSize'         => [100],
                'mid'             => [5.45 + $i * 0.1],
                'last'            => [5.45 + $i * 0.1],
                'openInterest'    => [1000],
                'volume'          => [500],
                'inTheMoney'      => [false],
                'underlyingPrice' => [145.00],
                'iv'              => [0.25],
                'delta'           => [0.50],
                'gamma'           => [0.05],
                'theta'           => [-0.02],
                'vega'            => [0.10],
                'intrinsicValue'  => [0.00],
                'extrinsicValue'  => [5.45 + $i * 0.1],
                'updated'         => [1684702875],
            ]));
        }

        $this->setMockResponses($responses);

        $symbols = [];
        for ($i = 0; $i < $symbolCount; $i++) {
            $symbols[] = "AAPL25011{$i}C00150000";
        }

        $response = $this->client->options->quotes($symbols);

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount($symbolCount, $response->quotes);
    }

    /**
     * Test createMerged factory method on Quotes response class.
     */
    public function testQuotes_createMerged_success(): void
    {
        $quote1 = new OptionQuote(
            option_symbol: 'AAPL250117C00150000',
            underlying: 'AAPL',
            expiration: Carbon::parse('2025-01-17'),
            side: Side::CALL,
            strike: 150.00,
            first_traded: Carbon::parse('2021-03-31'),
            dte: 30,
            ask: 5.50,
            ask_size: 100,
            bid: 5.40,
            bid_size: 100,
            mid: 5.45,
            last: 5.45,
            volume: 500,
            open_interest: 1000,
            underlying_price: 145.00,
            in_the_money: false,
            intrinsic_value: 0.00,
            extrinsic_value: 5.45,
            implied_volatility: 0.25,
            delta: 0.50,
            gamma: 0.05,
            theta: -0.02,
            vega: 0.10,
            updated: Carbon::now()
        );

        $merged = Quotes::createMerged('ok', [$quote1]);

        $this->assertInstanceOf(Quotes::class, $merged);
        $this->assertEquals('ok', $merged->status);
        $this->assertCount(1, $merged->quotes);
        $this->assertSame($quote1, $merged->quotes[0]);
    }

    /**
     * Test createMerged factory method with no_data status.
     */
    public function testQuotes_createMerged_noData_withTimes(): void
    {
        $nextTime = Carbon::parse('2024-01-15 10:00:00');
        $prevTime = Carbon::parse('2024-01-14 16:00:00');

        $merged = Quotes::createMerged('no_data', [], $nextTime, $prevTime);

        $this->assertInstanceOf(Quotes::class, $merged);
        $this->assertEquals('no_data', $merged->status);
        $this->assertEmpty($merged->quotes);
        $this->assertEquals($nextTime, $merged->next_time);
        $this->assertEquals($prevTime, $merged->prev_time);
    }

    // =========================================================================
    // Partial Failure Tests
    // =========================================================================

    /**
     * Test quotes endpoint returns partial data when some symbols fail.
     */
    public function testQuotes_partialFailure_returnsSuccessfulData(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $successResponse = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL250117C00150000'],
            'underlying'      => ['AAPL'],
            'expiration'      => [1737072000],
            'side'            => ['call'],
            'strike'          => [150],
            'firstTraded'     => [1617197400],
            'dte'             => [30],
            'ask'             => [5.50],
            'askSize'         => [100],
            'bid'             => [5.40],
            'bidSize'         => [100],
            'mid'             => [5.45],
            'last'            => [5.45],
            'openInterest'    => [1000],
            'volume'          => [500],
            'inTheMoney'      => [false],
            'underlyingPrice' => [145.00],
            'iv'              => [0.25],
            'delta'           => [0.50],
            'gamma'           => [0.05],
            'theta'           => [-0.02],
            'vega'            => [0.10],
            'intrinsicValue'  => [0.00],
            'extrinsicValue'  => [5.45],
            'updated'         => [1684702875],
        ];

        // Set up mock: first succeeds, second returns 400 error
        $this->setMockResponses([
            new Response(200, [], json_encode($successResponse)),
            new Response(400, [], json_encode(['s' => 'error', 'errmsg' => 'Invalid option symbol'])),
        ]);

        $response = $this->client->options->quotes([
            'AAPL250117C00150000',
            'INVALID_SYMBOL',
        ]);

        // Should return the successful data
        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->quotes);
        $this->assertEquals('AAPL250117C00150000', $response->quotes[0]->option_symbol);

        // Should have error for the failed symbol
        $this->assertNotEmpty($response->errors);
        $this->assertArrayHasKey('INVALID_SYMBOL', $response->errors);
    }

    /**
     * Test quotes endpoint errors property is empty when all succeed.
     */
    public function testQuotes_allSuccess_errorsEmpty(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $response1 = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL250117C00150000'],
            'underlying'      => ['AAPL'],
            'expiration'      => [1737072000],
            'side'            => ['call'],
            'strike'          => [150],
            'firstTraded'     => [1617197400],
            'dte'             => [30],
            'ask'             => [5.50],
            'askSize'         => [100],
            'bid'             => [5.40],
            'bidSize'         => [100],
            'mid'             => [5.45],
            'last'            => [5.45],
            'openInterest'    => [1000],
            'volume'          => [500],
            'inTheMoney'      => [false],
            'underlyingPrice' => [145.00],
            'iv'              => [0.25],
            'delta'           => [0.50],
            'gamma'           => [0.05],
            'theta'           => [-0.02],
            'vega'            => [0.10],
            'intrinsicValue'  => [0.00],
            'extrinsicValue'  => [5.45],
            'updated'         => [1684702875],
        ];

        $response2 = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL250117P00150000'],
            'underlying'      => ['AAPL'],
            'expiration'      => [1737072000],
            'side'            => ['put'],
            'strike'          => [150],
            'firstTraded'     => [1617197400],
            'dte'             => [30],
            'ask'             => [4.20],
            'askSize'         => [50],
            'bid'             => [4.10],
            'bidSize'         => [50],
            'mid'             => [4.15],
            'last'            => [4.15],
            'openInterest'    => [800],
            'volume'          => [300],
            'inTheMoney'      => [true],
            'underlyingPrice' => [145.00],
            'iv'              => [0.28],
            'delta'           => [-0.45],
            'gamma'           => [0.04],
            'theta'           => [-0.01],
            'vega'            => [0.08],
            'intrinsicValue'  => [5.00],
            'extrinsicValue'  => [-0.85],
            'updated'         => [1684702880],
        ];

        $this->setMockResponses([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);

        $response = $this->client->options->quotes([
            'AAPL250117C00150000',
            'AAPL250117P00150000',
        ]);

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(2, $response->quotes);
        $this->assertEmpty($response->errors);
    }

    /**
     * Test quotes endpoint throws when ALL symbols fail.
     */
    public function testQuotes_allFail_throwsException(): void
    {
        // Set up mock: both return 400 error
        $this->setMockResponses([
            new Response(400, [], json_encode(['s' => 'error', 'errmsg' => 'Invalid option symbol'])),
            new Response(400, [], json_encode(['s' => 'error', 'errmsg' => 'Invalid option symbol'])),
        ]);

        $this->expectException(\MarketDataApp\Exceptions\BadStatusCodeError::class);

        $this->client->options->quotes([
            'INVALID_SYMBOL_1',
            'INVALID_SYMBOL_2',
        ]);
    }

    /**
     * Test single symbol request still throws on error (backward compatible).
     */
    public function testQuotes_singleSymbol_error_throwsException(): void
    {
        // Set up mock: returns 400 error
        $this->setMockResponses([
            new Response(400, [], json_encode(['s' => 'error', 'errmsg' => 'Invalid option symbol'])),
        ]);

        $this->expectException(\MarketDataApp\Exceptions\BadStatusCodeError::class);

        $this->client->options->quotes('INVALID_SYMBOL');
    }

    /**
     * Test createMerged factory method with errors.
     */
    public function testQuotes_createMerged_withErrors(): void
    {
        $quote1 = new OptionQuote(
            option_symbol: 'AAPL250117C00150000',
            underlying: 'AAPL',
            expiration: Carbon::parse('2025-01-17'),
            side: Side::CALL,
            strike: 150.00,
            first_traded: Carbon::parse('2021-03-31'),
            dte: 30,
            ask: 5.50,
            ask_size: 100,
            bid: 5.40,
            bid_size: 100,
            mid: 5.45,
            last: 5.45,
            volume: 500,
            open_interest: 1000,
            underlying_price: 145.00,
            in_the_money: false,
            intrinsic_value: 0.00,
            extrinsic_value: 5.45,
            implied_volatility: 0.25,
            delta: 0.50,
            gamma: 0.05,
            theta: -0.02,
            vega: 0.10,
            updated: Carbon::now()
        );

        $errors = [
            'INVALID_SYMBOL' => 'Invalid option symbol',
        ];

        $merged = Quotes::createMerged('ok', [$quote1], null, null, $errors);

        $this->assertInstanceOf(Quotes::class, $merged);
        $this->assertEquals('ok', $merged->status);
        $this->assertCount(1, $merged->quotes);
        $this->assertNotEmpty($merged->errors);
        $this->assertEquals('Invalid option symbol', $merged->errors['INVALID_SYMBOL']);
    }

    /**
     * Test errors property defaults to empty array for single requests.
     */
    public function testQuotes_singleSymbol_errorsPropertyEmpty(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'optionSymbol'    => ['AAPL250117C00150000'],
            'underlying'      => ['AAPL'],
            'expiration'      => [1737072000],
            'side'            => ['call'],
            'strike'          => [150],
            'firstTraded'     => [1617197400],
            'dte'             => [30],
            'ask'             => [5.50],
            'askSize'         => [100],
            'bid'             => [5.40],
            'bidSize'         => [100],
            'mid'             => [5.45],
            'last'            => [5.45],
            'openInterest'    => [1000],
            'volume'          => [500],
            'inTheMoney'      => [false],
            'underlyingPrice' => [145.00],
            'iv'              => [0.25],
            'delta'           => [0.50],
            'gamma'           => [0.05],
            'theta'           => [-0.02],
            'vega'            => [0.10],
            'intrinsicValue'  => [0.00],
            'extrinsicValue'  => [5.45],
            'updated'         => [1684702875],
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->quotes('AAPL250117C00150000');

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEmpty($response->errors);
    }

    // =========================================================================
    // Multi-Symbol CSV/HTML Format Tests (Bug #015)
    // =========================================================================

    /**
     * Test that HTML format throws exception for multi-symbol requests.
     */
    public function testQuotes_multipleSymbols_htmlFormat_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HTML format is not supported for multi-symbol options quotes');

        $this->client->options->quotes(
            option_symbols: ['AAPL250117C00150000', 'AAPL250117P00150000'],
            parameters: new Parameters(format: Format::HTML)
        );
    }

    /**
     * Test that single-symbol HTML still works (not affected by multi-symbol restriction).
     */
    public function testQuotes_singleSymbol_htmlFormat_success(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "<html><body>data</body></html>";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->quotes(
            option_symbols: 'AAPL250117C00150000',
            parameters: new Parameters(format: Format::HTML)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertTrue($response->isHtml());
        $this->assertEquals($mocked_response, $response->getHtml());
    }

    /**
     * Test CSV multi-symbol combines responses with headers on first request only.
     */
    public function testQuotes_multipleSymbols_csvFormat_combinesWithHeaders(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        // First response should have headers, second should not
        $csv1 = "symbol,ask,bid\r\nAAPL250117C00150000,5.50,5.40";
        $csv2 = "AAPL250117P00150000,4.20,4.10";

        $this->setMockResponses([
            new Response(200, [], $csv1),
            new Response(200, [], $csv2),
        ]);

        $response = $this->client->options->quotes(
            option_symbols: ['AAPL250117C00150000', 'AAPL250117P00150000'],
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertTrue($response->isCsv());

        // Combined CSV should have both data rows
        $combinedCsv = $response->getCsv();
        $this->assertStringContainsString('AAPL250117C00150000', $combinedCsv);
        $this->assertStringContainsString('AAPL250117P00150000', $combinedCsv);
    }

    /**
     * Test CSV multi-symbol respects user's add_headers=false setting.
     */
    public function testQuotes_multipleSymbols_csvFormat_respectsNoHeaders(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        // Both responses should have no headers when user requests add_headers=false
        $csv1 = "AAPL250117C00150000,5.50,5.40";
        $csv2 = "AAPL250117P00150000,4.20,4.10";

        $this->setMockResponses([
            new Response(200, [], $csv1),
            new Response(200, [], $csv2),
        ]);

        $response = $this->client->options->quotes(
            option_symbols: ['AAPL250117C00150000', 'AAPL250117P00150000'],
            parameters: new Parameters(format: Format::CSV, add_headers: false)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertTrue($response->isCsv());

        // Combined CSV should have both data rows without headers
        $combinedCsv = $response->getCsv();
        $this->assertStringContainsString('AAPL250117C00150000', $combinedCsv);
        $this->assertStringContainsString('AAPL250117P00150000', $combinedCsv);
    }

    /**
     * Test CSV multi-symbol sends correct headers parameter to API.
     */
    public function testQuotes_multipleSymbols_csvFormat_sendsCorrectHeadersParam(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $csv1 = "symbol,ask,bid\r\nAAPL250117C00150000,5.50,5.40";
        $csv2 = "AAPL250117P00150000,4.20,4.10";

        $history = [];
        $this->setMockResponsesWithHistory([
            new Response(200, [], $csv1),
            new Response(200, [], $csv2),
        ], $history);

        $this->client->options->quotes(
            option_symbols: ['AAPL250117C00150000', 'AAPL250117P00150000'],
            parameters: new Parameters(format: Format::CSV)
        );

        // Verify first request has headers=true, second has headers=false
        $this->assertCount(2, $history);

        // First request should have headers=true
        $firstRequest = $history[0]['request'];
        $firstQuery = [];
        parse_str($firstRequest->getUri()->getQuery(), $firstQuery);
        $this->assertEquals('true', $firstQuery['headers']);

        // Second request should have headers=false
        $secondRequest = $history[1]['request'];
        $secondQuery = [];
        parse_str($secondRequest->getUri()->getQuery(), $secondQuery);
        $this->assertEquals('false', $secondQuery['headers']);
    }

    /**
     * Test CSV multi-symbol with user-specified add_headers=false sends headers=false for all.
     */
    public function testQuotes_multipleSymbols_csvFormat_userNoHeaders_sendsAllFalse(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $csv1 = "AAPL250117C00150000,5.50,5.40";
        $csv2 = "AAPL250117P00150000,4.20,4.10";

        $history = [];
        $this->setMockResponsesWithHistory([
            new Response(200, [], $csv1),
            new Response(200, [], $csv2),
        ], $history);

        $this->client->options->quotes(
            option_symbols: ['AAPL250117C00150000', 'AAPL250117P00150000'],
            parameters: new Parameters(format: Format::CSV, add_headers: false)
        );

        // Verify both requests have headers=false
        $this->assertCount(2, $history);

        $firstRequest = $history[0]['request'];
        $firstQuery = [];
        parse_str($firstRequest->getUri()->getQuery(), $firstQuery);
        $this->assertEquals('false', $firstQuery['headers']);

        $secondRequest = $history[1]['request'];
        $secondQuery = [];
        parse_str($secondRequest->getUri()->getQuery(), $secondQuery);
        $this->assertEquals('false', $secondQuery['headers']);
    }

    /**
     * Test CSV multi-symbol handles empty responses gracefully.
     */
    public function testQuotes_multipleSymbols_csvFormat_handlesEmptyResponse(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $csv1 = "symbol,ask,bid\r\nAAPL250117C00150000,5.50,5.40";
        $csv2 = ""; // Empty response for second symbol

        $this->setMockResponses([
            new Response(200, [], $csv1),
            new Response(200, [], $csv2),
        ]);

        $response = $this->client->options->quotes(
            option_symbols: ['AAPL250117C00150000', 'AAPL250117P00150000'],
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertTrue($response->isCsv());

        // Should still have the first symbol's data
        $combinedCsv = $response->getCsv();
        $this->assertStringContainsString('AAPL250117C00150000', $combinedCsv);
    }

    /**
     * Test that single-symbol array with CSV still works normally (delegates to single path).
     */
    public function testQuotes_singleSymbolArray_csvFormat_delegatesToSingle(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "symbol,ask,bid\r\nAAPL250117C00150000,5.50,5.40";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->quotes(
            option_symbols: ['AAPL250117C00150000'],
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertTrue($response->isCsv());
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test that CSV format throws exception when ALL symbol requests fail.
     *
     * This test covers line 591 in Options.php where an exception is thrown
     * when all requests fail in quotesMultipleCsv().
     */
    public function testQuotes_multipleSymbols_csvFormat_allFailures_throwsException(): void
    {
        $request1 = new \GuzzleHttp\Psr7\Request('GET', 'https://api.marketdata.app/v1/options/quotes/AAPL250117C00150000/');
        $request2 = new \GuzzleHttp\Psr7\Request('GET', 'https://api.marketdata.app/v1/options/quotes/AAPL250117P00150000/');
        $response404 = new Response(404, [], json_encode(['s' => 'error', 'errmsg' => 'No data available']));

        $this->setMockResponses([
            new \GuzzleHttp\Exception\RequestException('Not Found', $request1, $response404),
            new \GuzzleHttp\Exception\RequestException('Not Found', $request2, $response404),
        ]);

        $this->expectException(\Throwable::class);

        $this->client->options->quotes(
            option_symbols: ['AAPL250117C00150000', 'AAPL250117P00150000'],
            parameters: new Parameters(format: Format::CSV)
        );
    }

}
