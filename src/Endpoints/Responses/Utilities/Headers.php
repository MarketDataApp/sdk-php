<?php

namespace MarketDataApp\Endpoints\Responses\Utilities;

/**
 * Represents the headers of an API response.
 */
class Headers
{

    /**
     * Headers constructor.
     *
     * @param object $response The response object containing header information.
     */
    public function __construct(object $response)
    {
        // Set the headers based on response object.
        foreach ($response as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
