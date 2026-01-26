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
     * The symbol of the stock.
     *
     * @var string
     */
    public string $symbol = '';

    /**
     * The headline of the news article.
     *
     * @var string
     */
    public string $headline = '';

    /**
     * The content of the article, if available.
     *
     * TIP: Please be aware that this may or may not include the full content of the news article. Additionally, it may
     * include captions of images, copyright notices, syndication information, and other elements that may not be
     * suitable for reproduction without additional filtering.
     *
     * @var string
     */
    public string $content = '';

    /**
     * The source URL where the news appeared.
     *
     * @var string
     */
    public string $source = '';

    /**
     * The date the news was published on the source website.
     *
     * @var Carbon|null
     */
    public ?Carbon $publication_date = null;

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
            // Note: News endpoint returns arrays for all fields, even for single items
            $this->status = 'ok';
            $this->symbol = is_array($responseArray['Symbol']) ? $responseArray['Symbol'][0] : $responseArray['Symbol'];
            $this->headline = is_array($responseArray['headline']) ? $responseArray['headline'][0] : $responseArray['headline'];
            $this->content = is_array($responseArray['content']) ? $responseArray['content'][0] : $responseArray['content'];
            $this->source = is_array($responseArray['source']) ? $responseArray['source'][0] : $responseArray['source'];
            $publicationDate = is_array($responseArray['publicationDate']) ? $responseArray['publicationDate'][0] : $responseArray['publicationDate'];
            $this->publication_date = Carbon::parse($publicationDate);
        } else {
            // Regular format
            // Note: News endpoint returns arrays for all fields, even for single items
            $this->status = $response->s;

            if ($this->status === 'ok') {
                $this->symbol = is_array($response->symbol) ? $response->symbol[0] : $response->symbol;
                $this->headline = is_array($response->headline) ? $response->headline[0] : $response->headline;
                $this->content = is_array($response->content) ? $response->content[0] : $response->content;
                $this->source = is_array($response->source) ? $response->source[0] : $response->source;
                $publicationDate = is_array($response->publicationDate) ? $response->publicationDate[0] : $response->publicationDate;
                $this->publication_date = Carbon::parse($publicationDate);
            }
        }
    }

    /**
     * Returns a string representation of the news article.
     *
     * @return string Human-readable news summary.
     */
    public function __toString(): string
    {
        if (!$this->isJson()) {
            return "News - Non-JSON format, use getCsv() or getHtml()";
        }

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
