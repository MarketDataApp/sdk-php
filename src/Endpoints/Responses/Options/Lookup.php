<?php

namespace MarketDataApp\Endpoints\Responses\Options;

use MarketDataApp\Endpoints\Responses\ResponseBase;

class Lookup extends ResponseBase
{
    // Status will always be ok when the OCC option symbol is successfully generated.
    public string $status;


    // The generated OCC option symbol based on the user's input.
    public string $option_symbol;

    public function __construct(object $response)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }

        // Convert the response to this object.
        $this->status = $response->s;
        $this->option_symbol = $response->optionSymbol;}
}
