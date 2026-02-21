<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;
use MarketDataApp\Traits\FormatsForDisplay;

/**
 * Class News
 *
 * Represents news data for a stock and handles the response parsing.
 */
class News extends ResponseBase
{
    use FormatsForDisplay;

    /**
     * The status of the response. Will always be "ok" when there is data for the symbol requested.
     *
     * @var string
     */
    public string $status = 'no_data';

    /**
     * Array of Article objects containing individual news article data.
     *
     * @var Article[]
     */
    public array $articles = [];

    /**
     * Constructs a new News object and parses the response data.
     *
     * @param object $response The raw response object to be parsed.
     */
    public function __construct(object $response)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }

        // Convert to array for easier access to keys with spaces (human-readable format)
        $responseArray = (array) $response;

        // Determine if this is human-readable format (has "Symbol" key) or regular format (has "s" status)
        // Note: News human-readable format has mixed keys - some lowercase (headline, content, source, publicationDate) and some capitalized (Symbol, Date)
        $isHumanReadable = isset($responseArray['Symbol']);

        if ($isHumanReadable) {
            // Human-readable format - no "s" status field
            // Note: News endpoint returns arrays for all fields
            $symbols = is_array($responseArray['Symbol']) ? $responseArray['Symbol'] : [$responseArray['Symbol']];

            if (empty($symbols)) {
                return;
            }

            $this->status = 'ok';

            $headlines = is_array($responseArray['headline']) ? $responseArray['headline'] : [$responseArray['headline']];
            $contents = is_array($responseArray['content']) ? $responseArray['content'] : [$responseArray['content']];
            $sources = is_array($responseArray['source']) ? $responseArray['source'] : [$responseArray['source']];
            $publicationDates = is_array($responseArray['publicationDate']) ? $responseArray['publicationDate'] : [$responseArray['publicationDate']];

            // Create Article objects for each item
            $count = count($symbols);
            for ($i = 0; $i < $count; $i++) {
                $this->articles[] = new Article(
                    symbol: $symbols[$i] ?? '',
                    headline: $headlines[$i] ?? '',
                    content: $contents[$i] ?? '',
                    source: $sources[$i] ?? '',
                    publication_date: Carbon::parse($publicationDates[$i] ?? 0)
                );
            }
        } else {
            // Regular format
            // Note: News endpoint returns arrays for all fields, even for single items
            $this->status = $response->s ?? 'no_data';

            if ($this->status !== 'ok') {
                return;
            }

            $symbols = is_array($response->symbol) ? $response->symbol : [$response->symbol];

            // Check if arrays have data
            if (empty($symbols)) {
                $this->status = 'no_data';
                return;
            }

            $headlines = is_array($response->headline) ? $response->headline : [$response->headline];
            $contents = is_array($response->content) ? $response->content : [$response->content];
            $sources = is_array($response->source) ? $response->source : [$response->source];
            $publicationDates = is_array($response->publicationDate) ? $response->publicationDate : [$response->publicationDate];

            // Create Article objects for each item
            $count = count($symbols);
            for ($i = 0; $i < $count; $i++) {
                $this->articles[] = new Article(
                    symbol: $symbols[$i] ?? '',
                    headline: $headlines[$i] ?? '',
                    content: $contents[$i] ?? '',
                    source: $sources[$i] ?? '',
                    publication_date: Carbon::parse($publicationDates[$i] ?? 0)
                );
            }
        }
    }

    /**
     * Returns a string representation of the news collection.
     *
     * @return string Human-readable news summary.
     */
    public function __toString(): string
    {
        if (!$this->isJson()) {
            return "News - Non-JSON format, use getCsv() or getHtml()";
        }

        $count = count($this->articles);
        $lines = [sprintf("News: %d article%s (status: %s)", $count, $count === 1 ? '' : 's', $this->status)];

        foreach ($this->articles as $article) {
            $lines[] = sprintf("%s: %s", $article->symbol, $article->headline);
            $lines[] = sprintf(
                "  Published: %s  Source: %s",
                $this->formatDateTime($article->publication_date),
                $article->source
            );

            // Include content preview (first 200 chars if longer)
            if (!empty($article->content)) {
                $contentPreview = strlen($article->content) > 200
                    ? substr($article->content, 0, 197) . '...'
                    : $article->content;
                $lines[] = sprintf("  Content: %s", $contentPreview);
            }

            $lines[] = ''; // Blank line between articles
        }

        return implode("\n", array_filter($lines, fn($line) => $line !== '' || $count > 0));
    }
}
