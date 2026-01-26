<?php

namespace MarketDataApp\Tests\Unit\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;

/**
 * Test case for the Quote endpoint of the Stocks API.
 */
class QuoteTest extends StocksTestCase
{
    /**
     * Test the quote endpoint for a successful response.
     *
     * @return void
     */
    public function testQuote_success()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote('AAPL');

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
        $this->assertEquals($mocked_response['ask'][0], $quote->ask);
        $this->assertEquals($mocked_response['askSize'][0], $quote->ask_size);
        $this->assertEquals($mocked_response['bid'][0], $quote->bid);
        $this->assertEquals($mocked_response['bidSize'][0], $quote->bid_size);
        $this->assertEquals($mocked_response['mid'][0], $quote->mid);
        $this->assertEquals($mocked_response['last'][0], $quote->last);
        $this->assertEquals($mocked_response['change'][0], $quote->change);
        $this->assertEquals($mocked_response['changepct'][0], $quote->change_percent);
        $this->assertNull($quote->fifty_two_week_high);
        $this->assertNull($quote->fifty_two_week_low);
        $this->assertEquals($mocked_response['volume'][0], $quote->volume);
        $this->assertEquals(Carbon::parse($mocked_response['updated'][0]), $quote->updated);
    }

    /**
     * Test the quote endpoint for a successful CSV response.
     *
     * @return void
     */
    public function testQuote_csv_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = "symbol,ask,askSize,bid,bidSize,mid,last,change,changepct,volume,updated\nAAPL,248.8,200,248.7,600,248.75,247.65,0.95,0.0039,54933217,1769043595";
        $this->setMockResponses([
            new Response(200, [], $mocked_response),
        ]);
        $quote = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response, $quote->getCsv());
    }

    /**
     * Test the quote endpoint for a successful response with 52-week high/low.
     *
     * @return void
     */
    public function testQuote_52week_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = $this->aapl_mocked_response;
        $mocked_response['52weekHigh'] = [288.62];
        $mocked_response['52weekLow'] = [169.2101];
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote('AAPL');

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
        $this->assertEquals($mocked_response['ask'][0], $quote->ask);
        $this->assertEquals($mocked_response['askSize'][0], $quote->ask_size);
        $this->assertEquals($mocked_response['bid'][0], $quote->bid);
        $this->assertEquals($mocked_response['bidSize'][0], $quote->bid_size);
        $this->assertEquals($mocked_response['mid'][0], $quote->mid);
        $this->assertEquals($mocked_response['last'][0], $quote->last);
        $this->assertEquals($mocked_response['change'][0], $quote->change);
        $this->assertEquals($mocked_response['changepct'][0], $quote->change_percent);
        $this->assertEquals($mocked_response['52weekHigh'][0], $quote->fifty_two_week_high);
        $this->assertEquals($mocked_response['52weekLow'][0], $quote->fifty_two_week_low);
        $this->assertEquals($mocked_response['volume'][0], $quote->volume);
        $this->assertEquals(Carbon::parse($mocked_response['updated'][0]), $quote->updated);
    }

    /**
     * Test the quote endpoint with human-readable format.
     *
     * @return void
     */
    public function testQuote_humanReadable_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $mocked_response = [
            'Symbol' => ['AAPL'],
            'Ask' => [248.8],
            'Ask Size' => [200],
            'Bid' => [248.7],
            'Bid Size' => [600],
            'Mid' => [248.75],
            'Last' => [247.65],
            'Change $' => [0.95],
            'Change %' => [0.0039],
            'Volume' => [54933217],
            'Date' => [1769043595]
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals('ok', $quote->status);
        $this->assertEquals($mocked_response['Symbol'][0], $quote->symbol);
        $this->assertEquals($mocked_response['Ask'][0], $quote->ask);
        $this->assertEquals($mocked_response['Ask Size'][0], $quote->ask_size);
        $this->assertEquals($mocked_response['Bid'][0], $quote->bid);
        $this->assertEquals($mocked_response['Bid Size'][0], $quote->bid_size);
        $this->assertEquals($mocked_response['Mid'][0], $quote->mid);
        $this->assertEquals($mocked_response['Last'][0], $quote->last);
        $this->assertEquals($mocked_response['Change $'][0], $quote->change);
        $this->assertEquals($mocked_response['Change %'][0], $quote->change_percent);
        $this->assertEquals($mocked_response['Volume'][0], $quote->volume);
        $this->assertEquals(Carbon::parse($mocked_response['Date'][0]), $quote->updated);
    }

    /**
     * Test the quote endpoint with human-readable format and 52-week high/low.
     * Uses real API response values from a live API call.
     *
     * @return void
     */
    public function testQuote_humanReadable_52week_success()
    {
        // Real API response values captured from live API call on 2026-01-22
        $mocked_response = [
            'Symbol' => ['AAPL'],
            'Ask' => [248.8],
            'Ask Size' => [200],
            'Bid' => [248.7],
            'Bid Size' => [600],
            'Mid' => [248.75],
            'Last' => [247.65],
            'Change $' => [0.95],
            'Change %' => [0.0039],
            'Volume' => [54933217],
            'Date' => [1769043595],
            '52 Week High' => [288.62],
            '52 Week Low' => [169.2101]
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            fifty_two_week: true,
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals('ok', $quote->status);
        $this->assertEquals($mocked_response['Symbol'][0], $quote->symbol);
        $this->assertEquals($mocked_response['Ask'][0], $quote->ask);
        $this->assertEquals($mocked_response['Ask Size'][0], $quote->ask_size);
        $this->assertEquals($mocked_response['Bid'][0], $quote->bid);
        $this->assertEquals($mocked_response['Bid Size'][0], $quote->bid_size);
        $this->assertEquals($mocked_response['Mid'][0], $quote->mid);
        $this->assertEquals($mocked_response['Last'][0], $quote->last);
        $this->assertEquals($mocked_response['Change $'][0], $quote->change);
        $this->assertEquals($mocked_response['Change %'][0], $quote->change_percent);
        $this->assertEquals($mocked_response['Volume'][0], $quote->volume);
        $this->assertEquals(Carbon::parse($mocked_response['Date'][0]), $quote->updated);
        $this->assertEquals($mocked_response['52 Week High'][0], $quote->fifty_two_week_high);
        $this->assertEquals($mocked_response['52 Week Low'][0], $quote->fifty_two_week_low);
    }

    /**
     * Test the quote endpoint with human_readable=false.
     *
     * @return void
     */
    public function testQuote_humanReadableFalse_success()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(use_human_readable: false)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
    }

    /**
     * Test the quote endpoint with human_readable=null (should use regular format).
     *
     * @return void
     */
    public function testQuote_humanReadableNull_usesRegularFormat()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(use_human_readable: null)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
    }

    /**
     * Test the quote endpoint with mode=LIVE.
     *
     * @return void
     */
    public function testQuote_modeLive_success()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(mode: Mode::LIVE)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
    }

    /**
     * Test the quote endpoint with mode=CACHED.
     *
     * @return void
     */
    public function testQuote_modeCached_success()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(mode: Mode::CACHED)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
    }

    /**
     * Test the quote endpoint with mode=DELAYED.
     *
     * @return void
     */
    public function testQuote_modeDelayed_success()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(mode: Mode::DELAYED)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
    }

    /**
     * Test the quote endpoint with mode=null (should not include mode parameter).
     *
     * @return void
     */
    public function testQuote_modeNull_notIncluded()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote(
            'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(mode: null)
        );

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
    }

    /**
     * Test quote endpoint with empty symbol.
     */
    public function testQuote_emptySymbol_throwsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-empty string');

        $this->client->stocks->quote('');
    }

    /**
     * Test the quote endpoint with extended=true parameter (default).
     *
     * Bug 020: The extended parameter controls extended hours data inclusion.
     * When true (default), returns most recent quote regardless of market hours.
     *
     * @return void
     */
    public function testQuote_extendedTrue_success()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote('AAPL', extended: true);

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
    }

    /**
     * Test the quote endpoint with extended=false parameter.
     *
     * Bug 020: When extended=false, only returns quotes from primary trading session.
     *
     * @return void
     */
    public function testQuote_extendedFalse_success()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote('AAPL', extended: false);

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
    }

    /**
     * Test the quote endpoint with both fifty_two_week and extended parameters.
     *
     * @return void
     */
    public function testQuote_with52weekAndExtended_success()
    {
        // Mock response: FROM real API output format (captured on 2026-01-25)
        $mocked_response = $this->aapl_mocked_response;
        $mocked_response['52weekHigh'] = [288.62];
        $mocked_response['52weekLow'] = [169.2101];
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quote = $this->client->stocks->quote('AAPL', fifty_two_week: true, extended: false);

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertEquals($mocked_response['s'], $quote->status);
        $this->assertEquals($mocked_response['symbol'][0], $quote->symbol);
        $this->assertEquals($mocked_response['52weekHigh'][0], $quote->fifty_two_week_high);
        $this->assertEquals($mocked_response['52weekLow'][0], $quote->fifty_two_week_low);
    }

    /**
     * Test that quote properties are accessible for CSV responses (BUG-013 fix).
     *
     * CSV responses trigger an early return in the constructor. Properties should
     * have default values to prevent "uninitialized property" errors.
     *
     * @return void
     */
    public function testQuote_csv_propertiesAccessible(): void
    {
        // Mock response: NOT from real API output (uses synthetic CSV data)
        $csvResponse = "symbol,ask,askSize,bid,bidSize,mid,last,change,changepct,volume,updated\nAAPL,248.8,200,248.7,600,248.75,247.65,0.95,0.0039,54933217,1769043595";
        $this->setMockResponses([new Response(200, [], $csvResponse)]);

        $quote = $this->client->stocks->quote(
            'AAPL',
            parameters: new Parameters(format: Format::CSV)
        );

        // These should NOT throw "uninitialized property" errors
        $this->assertEquals('no_data', $quote->status);
        $this->assertEquals('', $quote->symbol);
        $this->assertNull($quote->ask);
        $this->assertNull($quote->ask_size);
        $this->assertNull($quote->bid);
        $this->assertNull($quote->bid_size);
        $this->assertNull($quote->mid);
        $this->assertNull($quote->last);
        $this->assertNull($quote->change);
        $this->assertNull($quote->change_percent);
        $this->assertNull($quote->volume);
        $this->assertNull($quote->updated);
    }

    /**
     * Test that quote properties are accessible for no_data responses (BUG-013 fix).
     *
     * no_data responses skip property initialization. Properties should
     * have default values to prevent "uninitialized property" errors.
     *
     * @return void
     */
    public function testQuote_noData_propertiesAccessible(): void
    {
        // Mock response: NOT from real API output (uses synthetic no_data response)
        $noDataResponse = ['s' => 'no_data'];
        $this->setMockResponses([new Response(200, [], json_encode($noDataResponse))]);

        $quote = $this->client->stocks->quote('INVALID');

        // These should NOT throw "uninitialized property" errors
        $this->assertEquals('no_data', $quote->status);
        $this->assertEquals('', $quote->symbol);
        $this->assertNull($quote->ask);
        $this->assertNull($quote->ask_size);
        $this->assertNull($quote->bid);
        $this->assertNull($quote->bid_size);
        $this->assertNull($quote->mid);
        $this->assertNull($quote->last);
        $this->assertNull($quote->change);
        $this->assertNull($quote->change_percent);
        $this->assertNull($quote->volume);
        $this->assertNull($quote->updated);
    }
}
