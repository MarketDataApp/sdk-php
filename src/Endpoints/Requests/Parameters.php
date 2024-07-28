<?php
namespace MarketDataApp\Endpoints\Requests;

use MarketDataApp\Enums\Format;

class Parameters {

    public function __construct(
        // Open price.
        public Format $format = Format::JSON,
    ) {
    }
}
