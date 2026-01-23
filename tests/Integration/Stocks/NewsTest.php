<?php

namespace MarketDataApp\Tests\Integration\Stocks;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\News;

/**
 * Integration tests for the Stocks News endpoint.
 */
class NewsTest extends StocksTestCase
{
    /**
     * Test stocks news with human-readable format.
     * Verifies that the API returns human-readable JSON keys (mixed format).
     */
    public function testNews_humanReadable_returnsHumanReadableKeys()
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
