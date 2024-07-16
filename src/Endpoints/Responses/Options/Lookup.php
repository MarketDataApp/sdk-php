<?php

namespace MarketDataApp\Endpoints\Responses\Options;

class Lookup
{
    // Status will always be ok when the OCC option symbol is successfully generated.
    public string $status;


    // The generated OCC option symbol based on the user's input.
    public string $option_symbol;

    public function __construct(object $response)
    {
        // Convert the response to this object.
        $this->status = $response->s;
        $this->option_symbol = $response->optionSymbol;}
}
