<?php

namespace MarketDataApp\Tests\Unit\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Article;
use MarketDataApp\Endpoints\Responses\Stocks\News;
use MarketDataApp\Enums\Format;

/**
 * Test case for the News endpoint of the Stocks API.
 */
class NewsTest extends StocksTestCase
{
    /**
     * Test the news endpoint for a successful response.
     *
     * @return void
     */
    public function testNews_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'symbol'          => ['AAPL'],
            'headline'        => ['Whoa, There! Let Apple Stock Take a Breather Before Jumping in Headfirst.'],
            'content'         => ["Apple is a rock-solid company, but this doesn't mean prudent investors need to buy AAPL stock at any price."],
            'source'          => ['https://investorplace.com/2023/12/whoa-there-let-apple-stock-take-a-breather-before-jumping-in-headfirst/'],
            'publicationDate' => [1703041200]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);
        $news = $this->client->stocks->news(symbol: 'AAPL', from: '2023-01-01');

        $this->assertInstanceOf(News::class, $news);
        $this->assertEquals('ok', $news->status);
        $this->assertCount(1, $news->articles);
        $this->assertInstanceOf(Article::class, $news->articles[0]);
        $this->assertEquals($mocked_response['symbol'][0], $news->articles[0]->symbol);
        $this->assertEquals($mocked_response['headline'][0], $news->articles[0]->headline);
        $this->assertEquals($mocked_response['content'][0], $news->articles[0]->content);
        $this->assertEquals($mocked_response['source'][0], $news->articles[0]->source);
        $this->assertEquals(Carbon::parse($mocked_response['publicationDate'][0]), $news->articles[0]->publication_date);
    }

    /**
     * Test the news endpoint with multiple articles.
     *
     * @return void
     */
    public function testNews_multipleArticles_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'symbol'          => ['AAPL', 'AAPL', 'AAPL'],
            'headline'        => [
                'First Apple Headline',
                'Second Apple Headline',
                'Third Apple Headline'
            ],
            'content'         => [
                'Content for the first article.',
                'Content for the second article.',
                'Content for the third article.'
            ],
            'source'          => [
                'https://example.com/article1',
                'https://example.com/article2',
                'https://example.com/article3'
            ],
            'publicationDate' => [1703041200, 1703127600, 1703214000]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);
        $news = $this->client->stocks->news(symbol: 'AAPL', from: '2023-01-01');

        $this->assertInstanceOf(News::class, $news);
        $this->assertEquals('ok', $news->status);
        $this->assertCount(3, $news->articles);

        // Verify each article
        for ($i = 0; $i < 3; $i++) {
            $this->assertInstanceOf(Article::class, $news->articles[$i]);
            $this->assertEquals($mocked_response['symbol'][$i], $news->articles[$i]->symbol);
            $this->assertEquals($mocked_response['headline'][$i], $news->articles[$i]->headline);
            $this->assertEquals($mocked_response['content'][$i], $news->articles[$i]->content);
            $this->assertEquals($mocked_response['source'][$i], $news->articles[$i]->source);
            $this->assertEquals(Carbon::parse($mocked_response['publicationDate'][$i]), $news->articles[$i]->publication_date);
        }
    }

    /**
     * Test the news endpoint for a successful CSV response.
     *
     * @return void
     */
    public function testNews_csv_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-22)
        // Note: Using header and first line only due to very long content field
        $mocked_response = "symbol,headline,content,source,publicationDate\nAAPL,How Apple's Gemini-Powered Siri Deal Will Impact Alphabet (GOOGL) Investors,\"Earlier in January 2026, Apple announced a multi-year partnership with Google to base its next generation of Apple Foundation Models on Google's Gemini AI and cloud technology, bringing more personalized, AI-powered Siri features while keeping Apple Intelligence workloads on-device and within its Private Cloud Compute framework. The deal effectively places Google's Gemini at the heart of Apple's core AI experience, turning a long-time ecosystem rival into a large-scale customer for Alphabet's models and infrastructure. With Gemini becoming the backbone of Siri and Apple Intelligence, we'll now explore how this deep integration could reshape Alphabet's investment narrative.\",https://finance.yahoo.com/news/apple-gemini-powered-siri-deal-231107182.html,1768971600";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);
        $news = $this->client->stocks->news(
            symbol: 'AAPL',
            from: '2023-01-01',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertInstanceOf(News::class, $news);
        $this->assertEquals($mocked_response, $news->getCsv());
    }

    /**
     * Test the news endpoint with human-readable format.
     *
     * @return void
     */
    public function testNews_humanReadable_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'headline' => ['Test Headline'],
            'content' => ['Test Content'],
            'source' => ['https://example.com'],
            'publicationDate' => [1703041200],
            'Symbol' => ['AAPL'],
            'Date' => [1703041200]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);
        $news = $this->client->stocks->news(
            symbol: 'AAPL',
            from: '2023-01-01',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(News::class, $news);
        $this->assertEquals('ok', $news->status);
        $this->assertCount(1, $news->articles);
        $this->assertEquals($mocked_response['Symbol'][0], $news->articles[0]->symbol);
        $this->assertEquals($mocked_response['headline'][0], $news->articles[0]->headline);
        $this->assertEquals($mocked_response['content'][0], $news->articles[0]->content);
        $this->assertEquals($mocked_response['source'][0], $news->articles[0]->source);
        $this->assertEquals(Carbon::parse($mocked_response['publicationDate'][0]), $news->articles[0]->publication_date);
    }

    /**
     * Test the news endpoint works without date parameters.
     *
     * The API returns recent news when no date parameters are provided.
     *
     * @return void
     */
    public function testNews_withoutDateParams_success()
    {
        // Mock response: FROM real API output (captured on 2026-01-25)
        $mocked_response = [
            's'               => 'ok',
            'symbol'          => ['AAPL'],
            'headline'        => ['Dow Jones Futures Due With Trump Tariffs, Government Shutdown, Big Earnings In Focus'],
            'content'         => ['President Donald Trump threatened a 100% tariff on Canada. Government shutdown risks soared.'],
            'source'          => ['https://www.investors.com/market-trend/stock-market-today/dow-jones-futures-trump-tariffs-tesla-microsoft-apple-earnings/'],
            'publicationDate' => [1737856800]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        // Call without any date parameters - should work fine
        $news = $this->client->stocks->news(symbol: 'AAPL');

        $this->assertInstanceOf(News::class, $news);
        $this->assertEquals('ok', $news->status);
        $this->assertCount(1, $news->articles);
        $this->assertEquals('AAPL', $news->articles[0]->symbol);
        $this->assertEquals('Dow Jones Futures Due With Trump Tariffs, Government Shutdown, Big Earnings In Focus', $news->articles[0]->headline);
    }

    /**
     * Test news endpoint with invalid date range.
     */
    public function testNews_invalidDateRange_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`from` date must be before `to` date');

        $this->client->stocks->news(
            symbol: 'AAPL',
            from: '2024-01-31',
            to: '2024-01-01'
        );
    }

    /**
     * Test that news properties are accessible for CSV responses (BUG-013 fix).
     *
     * CSV responses trigger an early return in the constructor. Properties should
     * have default values to prevent "uninitialized property" errors.
     *
     * @return void
     */
    public function testNews_csv_propertiesAccessible(): void
    {
        // Mock response: NOT from real API output (uses synthetic CSV data)
        $csvResponse = "symbol,headline,content,source,publicationDate\nAAPL,Test Headline,Test Content,https://example.com,1703041200";
        $this->setMockResponses([new Response(200, [], $csvResponse)]);

        $news = $this->client->stocks->news(
            symbol: 'AAPL',
            from: '2024-01-01',
            parameters: new Parameters(format: Format::CSV)
        );

        // These should NOT throw "uninitialized property" errors
        $this->assertEquals('no_data', $news->status);
        $this->assertIsArray($news->articles);
        $this->assertCount(0, $news->articles);
    }

    /**
     * Test that news properties are accessible for no_data responses (BUG-013 fix).
     *
     * no_data responses skip property initialization. Properties should
     * have default values to prevent "uninitialized property" errors.
     *
     * @return void
     */
    public function testNews_noData_propertiesAccessible(): void
    {
        // Mock response: NOT from real API output (uses synthetic no_data response)
        $noDataResponse = ['s' => 'no_data'];
        $this->setMockResponses([new Response(200, [], json_encode($noDataResponse))]);

        $news = $this->client->stocks->news(
            symbol: 'INVALID',
            from: '2099-01-01',
            to: '2099-12-31'
        );

        // These should NOT throw "uninitialized property" errors
        $this->assertEquals('no_data', $news->status);
        $this->assertIsArray($news->articles);
        $this->assertCount(0, $news->articles);
    }

    /**
     * Test that news handles empty arrays with ok status (BUG-046 fix).
     *
     * When the API returns 'ok' status but empty arrays, the code should
     * handle this gracefully instead of throwing "Undefined array key 0".
     *
     * @return void
     */
    public function testNews_emptyArraysWithOkStatus_handledGracefully(): void
    {
        // Mock response: NOT from real API output (synthetic malformed response)
        $emptyArrayResponse = [
            's' => 'ok',
            'symbol' => [],
            'headline' => [],
            'content' => [],
            'source' => [],
            'publicationDate' => [],
        ];
        $this->setMockResponses([new Response(200, [], json_encode($emptyArrayResponse))]);

        $news = $this->client->stocks->news(
            symbol: 'AAPL',
            from: '2024-01-01'
        );

        $this->assertInstanceOf(News::class, $news);
        $this->assertEquals('no_data', $news->status);
        $this->assertCount(0, $news->articles);
    }

    /**
     * Test that news handles missing 's' status field (BUG-049 fix).
     *
     * When the API returns a malformed response without the 's' status field,
     * the code should handle this gracefully by defaulting to 'no_data'.
     *
     * @return void
     */
    public function testNews_missingStatusField_handledGracefully(): void
    {
        // Mock response: NOT from real API output (synthetic malformed response)
        $malformedResponse = [
            'symbol' => ['AAPL'],
            'headline' => ['Test Headline'],
            'content' => ['Test Content'],
            'source' => ['https://example.com'],
            'publicationDate' => [1703041200],
            // Note: 's' status field intentionally omitted
        ];
        $this->setMockResponses([new Response(200, [], json_encode($malformedResponse))]);

        $news = $this->client->stocks->news(
            symbol: 'AAPL',
            from: '2024-01-01'
        );

        $this->assertInstanceOf(News::class, $news);
        $this->assertEquals('no_data', $news->status);
    }

    /**
     * Test that human-readable news handles empty arrays gracefully.
     *
     * When the API returns human-readable format with empty Symbol array,
     * the code should handle this gracefully by returning defaults.
     *
     * @return void
     */
    public function testNews_humanReadable_emptyArrays_handledGracefully(): void
    {
        // Mock response: NOT from real API output (synthetic malformed response)
        // Human-readable format is detected by presence of 'Symbol' key
        $emptyHumanReadableResponse = [
            'Symbol' => [],
            'headline' => [],
            'content' => [],
            'source' => [],
            'publicationDate' => [],
            'Date' => [],
        ];
        $this->setMockResponses([new Response(200, [], json_encode($emptyHumanReadableResponse))]);

        $news = $this->client->stocks->news(
            symbol: 'AAPL',
            from: '2024-01-01',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(News::class, $news);
        // Should return defaults since Symbol array is empty
        $this->assertEquals('no_data', $news->status);
        $this->assertCount(0, $news->articles);
    }

    /**
     * Test that Article object has correct __toString output.
     *
     * @return void
     */
    public function testArticle_toString_returnsFormattedString(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'               => 'ok',
            'symbol'          => ['AAPL'],
            'headline'        => ['Test Headline for Apple Stock'],
            'content'         => ['This is the content of the article.'],
            'source'          => ['https://example.com/article'],
            'publicationDate' => [1703041200]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);
        $news = $this->client->stocks->news(symbol: 'AAPL', from: '2023-01-01');

        $article = $news->articles[0];
        $string = (string) $article;

        $this->assertStringContainsString('AAPL', $string);
        $this->assertStringContainsString('Test Headline for Apple Stock', $string);
        $this->assertStringContainsString('https://example.com/article', $string);
        $this->assertStringContainsString('Content:', $string);
    }
}
