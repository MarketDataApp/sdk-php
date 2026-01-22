<?php

namespace MarketDataApp\Tests\Unit\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Endpoints\Responses\Stocks\Quotes;
use MarketDataApp\Enums\Mode;

/**
 * Test case for the Quotes endpoint (parallel) of the Stocks API.
 */
class QuotesTest extends StocksTestCase
{
    /**
     * Test the quotes endpoint for a successful response.
     *
     * @return void
     * @throws \Throwable
     */
    public function testQuotes_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $nflx_mocked_response = [
            's'         => 'ok',
            'symbol'    => ['NFLX'],
            'ask'       => [85.6],
            'askSize'   => [10],
            'bid'       => [85.58],
            'bidSize'   => [150],
            'mid'       => [85.59],
            'last'      => [85.36],
            'change'    => [-1.9],
            'changepct' => [-0.0218],
            'volume'    => [127578915],
            'updated'   => [1769043596]
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($this->aapl_mocked_response)),
            new Response(200, [], json_encode($nflx_mocked_response)),
        ]);

        $quotes = $this->client->stocks->quotes(['AAPL', 'NFLX']);
        $this->assertInstanceOf(Quotes::class, $quotes);
        foreach ($quotes->quotes as $quote) {
            $this->assertInstanceOf(Quote::class, $quote);
            $mocked_response = $quote->symbol === "AAPL" ? $this->aapl_mocked_response : $nflx_mocked_response;

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
            $this->assertEquals($mocked_response['volume'][0], $quote->volume);
            $this->assertEquals(Carbon::parse($mocked_response['updated'][0]), $quote->updated);
        }
    }

    /**
     * Test the quotes endpoint (parallel) with human-readable format.
     *
     * @return void
     * @throws \Throwable
     */
    public function testQuotes_humanReadable_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        $human_readable_response = [
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
            new Response(200, [], json_encode($human_readable_response)),
        ]);
        $quotes = $this->client->stocks->quotes(
            ['AAPL'],
            false,
            new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quotes::class, $quotes);
        $this->assertCount(1, $quotes->quotes);
        $this->assertInstanceOf(Quote::class, $quotes->quotes[0]);
        $this->assertEquals('ok', $quotes->quotes[0]->status);
        $this->assertEquals($human_readable_response['Symbol'][0], $quotes->quotes[0]->symbol);
    }

    /**
     * Test the quotes endpoint (parallel) with mode parameter.
     *
     * @return void
     * @throws \Throwable
     */
    public function testQuotes_mode_success()
    {
        // Mock response: NOT from real API output (uses class property with synthetic/test data)
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
}
