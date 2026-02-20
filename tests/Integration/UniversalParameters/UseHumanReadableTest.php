<?php

namespace MarketDataApp\Tests\Integration\UniversalParameters;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Candle;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Endpoints\Responses\Stocks\Earnings;
use MarketDataApp\Endpoints\Responses\Stocks\News;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Endpoints\Responses\Stocks\Quotes;

/**
 * Integration tests for the UseHumanReadable universal parameter.
 *
 * Tests that the use_human_readable parameter works correctly with the actual API
 * to return human-readable JSON keys with spaces.
 */
class UseHumanReadableTest extends UniversalParametersTestCase
{
    public function testUseHumanReadable_quote_returnsValidData(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(use_human_readable: true)
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
        $this->assertEquals('integer', gettype($response->volume));
        $this->assertInstanceOf(Carbon::class, $response->updated);
    }

    public function testUseHumanReadable_false_returnsValidData(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            fifty_two_week: false,
            parameters: new Parameters(use_human_readable: false)
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('string', gettype($response->symbol));
        $this->assertEquals('double', gettype($response->ask));
    }

    public function testUseHumanReadable_quotes_returnsValidData(): void
    {
        $response = $this->client->stocks->quotes(
            symbols: ['AAPL'],
            fifty_two_week: false,
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertNotEmpty($response->quotes);
        $this->assertInstanceOf(Quote::class, $response->quotes[0]);
        $this->assertEquals('ok', $response->quotes[0]->status);
    }

    public function testUseHumanReadable_candles_returnsValidData(): void
    {
        $response = $this->client->stocks->candles(
            symbol: 'AAPL',
            from: '2024-01-02',
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
    }

    public function testUseHumanReadable_earnings_returnsValidData(): void
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

    public function testUseHumanReadable_news_returnsValidData(): void
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
}
