<?php

namespace MarketDataApp\Tests\Unit\Stocks;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Endpoints\Requests\Parameters;
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
            'symbol'          => 'AAPL',
            'headline'        => 'Whoa, There! Let Apple Stock Take a Breather Before Jumping in Headfirst.',
            'content'         => "Apple is a rock-solid company, but this doesn't mean prudent investors need to buy AAPL stock at any price.",
            'source'          => 'https=>//investorplace.com/2023/12/whoa-there-let-apple-stock-take-a-breather-before-jumping-in-headfirst/',
            'publicationDate' => 1703041200
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);
        $news = $this->client->stocks->news(symbol: 'AAPL', from: '2023-01-01');

        $this->assertInstanceOf(News::class, $news);
        $this->assertEquals($mocked_response['s'], $news->status);
        $this->assertEquals($mocked_response['symbol'], $news->symbol);
        $this->assertEquals($mocked_response['headline'], $news->headline);
        $this->assertEquals($mocked_response['content'], $news->content);
        $this->assertEquals($mocked_response['source'], $news->source);
        $this->assertEquals(Carbon::parse($mocked_response['publicationDate']), $news->publication_date);
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
            'headline' => 'Test Headline',
            'content' => 'Test Content',
            'source' => 'https://example.com',
            'publicationDate' => 1703041200,
            'Symbol' => 'AAPL',
            'Date' => 1703041200
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);
        $news = $this->client->stocks->news(
            symbol: 'AAPL',
            from: '2023-01-01',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(News::class, $news);
        $this->assertEquals('ok', $news->status);
        $this->assertEquals($mocked_response['Symbol'], $news->symbol);
        $this->assertEquals($mocked_response['headline'], $news->headline);
        $this->assertEquals($mocked_response['content'], $news->content);
        $this->assertEquals($mocked_response['source'], $news->source);
        $this->assertEquals(Carbon::parse($mocked_response['publicationDate']), $news->publication_date);
    }

    /**
     * Test the news endpoint for an exception when neither 'from' nor 'countback' is provided.
     *
     * @return void
     */
    public function testNews_noFromOrCountback_throwsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->stocks->news('AAPL');
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
}
