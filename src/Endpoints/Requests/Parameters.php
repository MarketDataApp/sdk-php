<?php

namespace MarketDataApp\Endpoints\Requests;

use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;

/**
 * Represents parameters for API requests.
 */
class Parameters
{

    /**
     * Parameters constructor.
     *
     * @param Format $format The format of the response. Defaults to JSON.
     * @param bool|null $use_human_readable Whether to use human-readable format for values. Defaults to null.
     * @param Mode|null $mode The data feed mode to use. Defaults to null.
     * @param DateFormat|null $date_format The date format for CSV and HTML responses. Can only be used when format=CSV or format=HTML. Defaults to null.
     * @throws \InvalidArgumentException If date_format is set but format is not CSV or HTML.
     */
    public function __construct(
        // Open price.
        public Format $format = Format::JSON,
        public ?bool $use_human_readable = null,
        public ?Mode $mode = null,
        public ?DateFormat $date_format = null,
    ) {
        // Validate that date_format can only be used with CSV or HTML format
        if ($date_format !== null && $format !== Format::CSV && $format !== Format::HTML) {
            throw new \InvalidArgumentException(
                'date_format parameter can only be used with CSV or HTML format. ' .
                'Current format: ' . $format->value
            );
        }
    }
}
