<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Class News
 *
 * Represents news data for a stock and handles the response parsing.
 */
class News extends ResponseBase
{

    /**
     * The status of the response. Will always be "ok" when there is data for the symbol requested.
     *
     * @var string
     */
    public string $status;

    /**
     * The symbol of the stock.
     *
     * @var string
     */
    public string $symbol;

    /**
     * The headline of the news article.
     *
     * @var string
     */
    public string $headline;

    /**
     * The content of the article, if available.
     *
     * TIP: Please be aware that this may or may not include the full content of the news article. Additionally, it may
     * include captions of images, copyright notices, syndication information, and other elements that may not be
     * suitable for reproduction without additional filtering.
     *
     * @var string
     */
    public string $content;

    /**
     * The source URL where the news appeared.
     *
     * @var string
     */
    public string $source;

    /**
     * The date the news was published on the source website.
     *
     * @var Carbon
     */
    public Carbon $publication_date;

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

        // Convert the response to this object.
        $this->status = $response->s;

        if ($this->status === 'ok') {
            $this->symbol = $response->symbol;
            $this->headline = $response->headline;
            $this->content = $response->content;
            $this->source = $response->source;
            $this->publication_date = Carbon::parse($response->publicationDate);
        }
    }
}
