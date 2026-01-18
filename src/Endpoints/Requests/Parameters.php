<?php

namespace MarketDataApp\Endpoints\Requests;

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
     */
    public function __construct(
        // Open price.
        public Format $format = Format::JSON,
        public ?bool $use_human_readable = null,
        public ?Mode $mode = null,
    ) {
    }
}
