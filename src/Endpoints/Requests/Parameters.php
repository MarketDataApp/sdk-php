<?php

namespace MarketDataApp\Endpoints\Requests;

use MarketDataApp\Enums\Format;

/**
 * Represents parameters for API requests.
 */
class Parameters
{

    /**
     * Parameters constructor.
     *
     * @param Format $format The format of the response. Defaults to JSON.
     */
    public function __construct(
        // Open price.
        public Format $format = Format::JSON,
    ) {
    }
}
