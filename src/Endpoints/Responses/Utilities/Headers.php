<?php

namespace MarketDataApp\Endpoints\Responses\Utilities;

class Headers
{
    public function __construct(object $response)
    {
        // Set the headers based on response object.
        foreach ($response as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
