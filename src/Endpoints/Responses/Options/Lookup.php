<?php

namespace MarketDataApp\Endpoints\Responses\Options;

use MarketDataApp\Endpoints\Responses\ResponseBase;

/**
 * Represents a lookup response for generating OCC option symbols.
 */
class Lookup extends ResponseBase
{

    /**
     * Status of the lookup request. Will always be ok when the OCC option symbol is successfully generated.
     *
     * @var string
     */
    public string $status;

    /**
     * The generated OCC option symbol based on the user's input.
     *
     * @var string
     */
    public string $option_symbol;

    /**
     * Constructs a new Lookup instance from the given response object.
     *
     * @param object $response The response object containing lookup data.
     */
    public function __construct(object $response)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }

        // Convert the response to this object.
        $this->status = $response->s;
        $this->option_symbol = $response->optionSymbol;
    }
}
