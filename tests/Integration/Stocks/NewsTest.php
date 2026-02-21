<?php

namespace MarketDataApp\Tests\Integration\Stocks;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Article;
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
        $this->assertNotEmpty($response->articles);
        $this->assertInstanceOf(Article::class, $response->articles[0]);
        $this->assertEquals('string', gettype($response->articles[0]->symbol));
        $this->assertEquals('string', gettype($response->articles[0]->headline));
        $this->assertEquals('string', gettype($response->articles[0]->content));
        $this->assertEquals('string', gettype($response->articles[0]->source));
        $this->assertInstanceOf(Carbon::class, $response->articles[0]->publication_date);
    }

    /**
     * Test that news endpoint returns multiple articles.
     * Verifies that the API can return more than one article.
     */
    public function testNews_returnsMultipleArticles()
    {
        // Use a broader date range to ensure we get multiple articles
        $response = $this->client->stocks->news(
            symbol: 'AAPL',
            from: '2024-01-01'
        );

        $this->assertInstanceOf(News::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->articles);

        // Verify that all items are Article objects
        foreach ($response->articles as $article) {
            $this->assertInstanceOf(Article::class, $article);
            $this->assertNotEmpty($article->symbol);
            $this->assertNotEmpty($article->headline);
            $this->assertInstanceOf(Carbon::class, $article->publication_date);
        }
    }
}
