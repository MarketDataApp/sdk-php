<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Traits\FormatsForDisplay;

/**
 * Represents a single news article with headline, content, and publication data.
 */
class Article
{
    use FormatsForDisplay;

    /**
     * Constructs a new Article instance.
     *
     * @param string $symbol           The ticker symbol this article relates to.
     * @param string $headline         The headline of the news article.
     * @param string $content          The content of the article, if available.
     * @param string $source           The source URL where the news appeared.
     * @param Carbon $publication_date The date the news was published on the source website.
     */
    public function __construct(
        public string $symbol,
        public string $headline,
        public string $content,
        public string $source,
        public Carbon $publication_date,
    ) {
    }

    /**
     * Returns a string representation of the article.
     *
     * @return string Human-readable article data.
     */
    public function __toString(): string
    {
        $lines = [];
        $lines[] = sprintf("%s: %s", $this->symbol, $this->headline);
        $lines[] = sprintf(
            "  Published: %s  Source: %s",
            $this->formatDateTime($this->publication_date),
            $this->source
        );

        // Include content preview (first 200 chars if longer)
        if (!empty($this->content)) {
            $contentPreview = strlen($this->content) > 200
                ? substr($this->content, 0, 197) . '...'
                : $this->content;
            $lines[] = sprintf("  Content: %s", $contentPreview);
        }

        return implode("\n", $lines);
    }
}
