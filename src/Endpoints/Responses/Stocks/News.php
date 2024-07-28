<?php

namespace MarketDataApp\Endpoints\Responses\Stocks;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;

class News extends ResponseBase
{

    // Will always be ok when there is data for the symbol requested.
    public string $status;

    // The symbol of the stock.
    public string $symbol;

    // The headline of the news article.
    public string $headline;

    /**
     * The content of the article, if available.
     *
     * TIP: Please be aware that this may or may not include the full content of the news article. Additionally, it may
     * include captions of images, copyright notices, syndication information, and other elements that may not be
     * suitable for reproduction without additional filtering.
     */
    public string $content;

    // The source URL where the news appeared.
    public string $source;

    // The date the news was published on the source website.
    public Carbon $publication_date;

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
