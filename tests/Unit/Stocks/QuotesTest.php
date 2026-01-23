<?php

namespace MarketDataApp\Tests\Unit\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Endpoints\Responses\Stocks\Quotes;
use MarketDataApp\Enums\Mode;

/**
 * Test case for the Quotes endpoint (multi-symbol) of the Stocks API.
 */
class QuotesTest extends StocksTestCase
{
    /**
     * Test the quotes endpoint for a successful multi-symbol response.
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \MarketDataApp\Exceptions\ApiException
     */
    public function testQuotes_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        // Multi-symbol response with AAPL and NFLX data in single response
        $multi_symbol_response = [
            's'         => 'ok',
            'symbol'    => ['AAPL', 'NFLX'],
            'ask'       => [248.8, 85.6],
            'askSize'   => [200, 10],
            'bid'       => [248.7, 85.58],
            'bidSize'   => [600, 150],
            'mid'       => [248.75, 85.59],
            'last'      => [247.65, 85.36],
            'change'    => [0.95, -1.9],
            'changepct' => [0.0039, -0.0218],
            'volume'    => [54933217, 127578915],
            'updated'   => [1769043595, 1769043596]
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($multi_symbol_response)),
        ]);

        $quotes = $this->client->stocks->quotes(['AAPL', 'NFLX']);
        $this->assertInstanceOf(Quotes::class, $quotes);
        $this->assertCount(2, $quotes->quotes);

        // Verify AAPL quote (index 0)
        $aaplQuote = $quotes->quotes[0];
        $this->assertInstanceOf(Quote::class, $aaplQuote);
        $this->assertEquals('ok', $aaplQuote->status);
        $this->assertEquals('AAPL', $aaplQuote->symbol);
        $this->assertEquals(248.8, $aaplQuote->ask);
        $this->assertEquals(200, $aaplQuote->ask_size);
        $this->assertEquals(248.7, $aaplQuote->bid);
        $this->assertEquals(600, $aaplQuote->bid_size);
        $this->assertEquals(248.75, $aaplQuote->mid);
        $this->assertEquals(247.65, $aaplQuote->last);
        $this->assertEquals(0.95, $aaplQuote->change);
        $this->assertEquals(0.0039, $aaplQuote->change_percent);
        $this->assertEquals(54933217, $aaplQuote->volume);
        $this->assertEquals(Carbon::parse(1769043595), $aaplQuote->updated);

        // Verify NFLX quote (index 1)
        $nflxQuote = $quotes->quotes[1];
        $this->assertInstanceOf(Quote::class, $nflxQuote);
        $this->assertEquals('ok', $nflxQuote->status);
        $this->assertEquals('NFLX', $nflxQuote->symbol);
        $this->assertEquals(85.6, $nflxQuote->ask);
        $this->assertEquals(10, $nflxQuote->ask_size);
        $this->assertEquals(85.58, $nflxQuote->bid);
        $this->assertEquals(150, $nflxQuote->bid_size);
        $this->assertEquals(85.59, $nflxQuote->mid);
        $this->assertEquals(85.36, $nflxQuote->last);
        $this->assertEquals(-1.9, $nflxQuote->change);
        $this->assertEquals(-0.0218, $nflxQuote->change_percent);
        $this->assertEquals(127578915, $nflxQuote->volume);
        $this->assertEquals(Carbon::parse(1769043596), $nflxQuote->updated);
    }

    /**
     * Test the quotes endpoint with human-readable format.
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \MarketDataApp\Exceptions\ApiException
     */
    public function testQuotes_humanReadable_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        // Human-readable format with multiple symbols
        $human_readable_response = [
            'Symbol'     => ['AAPL', 'MSFT'],
            'Ask'        => [248.8, 465.94],
            'Ask Size'   => [200, 40],
            'Bid'        => [248.7, 465.93],
            'Bid Size'   => [600, 40],
            'Mid'        => [248.75, 465.935],
            'Last'       => [247.65, 465.93],
            'Change $'   => [0.95, 14.79],
            'Change %'   => [0.0039, 0.0328],
            'Volume'     => [54933217, 26896268],
            'Date'       => [1769043595, 1769043596]
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($human_readable_response)),
        ]);
        $quotes = $this->client->stocks->quotes(
            ['AAPL', 'MSFT'],
            false,
            new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quotes::class, $quotes);
        $this->assertCount(2, $quotes->quotes);

        // Verify AAPL quote
        $this->assertInstanceOf(Quote::class, $quotes->quotes[0]);
        $this->assertEquals('ok', $quotes->quotes[0]->status);
        $this->assertEquals('AAPL', $quotes->quotes[0]->symbol);
        $this->assertEquals(248.8, $quotes->quotes[0]->ask);

        // Verify MSFT quote
        $this->assertInstanceOf(Quote::class, $quotes->quotes[1]);
        $this->assertEquals('ok', $quotes->quotes[1]->status);
        $this->assertEquals('MSFT', $quotes->quotes[1]->symbol);
        $this->assertEquals(465.94, $quotes->quotes[1]->ask);
    }

    /**
     * Test the quotes endpoint with mode parameter.
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \MarketDataApp\Exceptions\ApiException
     */
    public function testQuotes_mode_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-23)
        $mocked_response = $this->aapl_mocked_response;
        $this->setMockResponses([
            new Response(200, [], json_encode($mocked_response)),
        ]);
        $quotes = $this->client->stocks->quotes(
            ['AAPL'],
            false,
            new Parameters(mode: Mode::LIVE)
        );

        $this->assertInstanceOf(Quotes::class, $quotes);
        $this->assertCount(1, $quotes->quotes);
        $this->assertInstanceOf(Quote::class, $quotes->quotes[0]);
        $this->assertEquals('ok', $quotes->quotes[0]->status);
        $this->assertEquals($mocked_response['symbol'][0], $quotes->quotes[0]->symbol);
    }

    /**
     * Test quotes endpoint with empty array.
     */
    public function testQuotes_emptyArray_throwsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-empty array');

        $this->client->stocks->quotes([]);
    }

    /**
     * Test the quotes endpoint with 52-week high/low data.
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \MarketDataApp\Exceptions\ApiException
     */
    public function testQuotes_with52Week_success()
    {
        // Mock response: FROM real API output format (captured on 2026-01-23)
        $response_with_52week = [
            's'          => 'ok',
            'symbol'     => ['AAPL'],
            'ask'        => [248.8],
            'askSize'    => [200],
            'bid'        => [248.7],
            'bidSize'    => [600],
            'mid'        => [248.75],
            'last'       => [247.65],
            'change'     => [0.95],
            'changepct'  => [0.0039],
            'volume'     => [54933217],
            'updated'    => [1769043595],
            '52weekHigh' => [260.10],
            '52weekLow'  => [164.08]
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($response_with_52week)),
        ]);

        $quotes = $this->client->stocks->quotes(['AAPL'], true);

        $this->assertInstanceOf(Quotes::class, $quotes);
        $this->assertCount(1, $quotes->quotes);
        $this->assertEquals(260.10, $quotes->quotes[0]->fifty_two_week_high);
        $this->assertEquals(164.08, $quotes->quotes[0]->fifty_two_week_low);
    }
}
