<?php

namespace MarketDataApp\Tests\Unit\Options;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\Quote;
use MarketDataApp\Endpoints\Responses\Options\Quotes;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;

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
            $this->assertInstanceOf(Quote::class, $response->quotes[$i]);
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
            option_symbol: 'AAPL250117C00150000',
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
            option_symbol: 'AAPL281215C00400000',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertCount(1, $response->quotes);
        $this->assertInstanceOf(Quote::class, $response->quotes[0]);
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
            option_symbol: 'AAPL250117C00150000',
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
            option_symbol: 'AAPL250117C00150000',
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
            option_symbol: 'AAPL250117C00150000',
            from: '2024-01-31',
            to: '2024-01-01'
        );
    }
}
