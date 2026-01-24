<?php

namespace MarketDataApp\Endpoints\Responses\Utilities;

/**
 * Represents the headers of an API response.
 */
#[\AllowDynamicProperties]
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

    /**
     * Returns a string representation of the headers.
     *
     * @return string Human-readable headers list.
     */
    public function __toString(): string
    {
        $lines = ['Headers:'];
        foreach (get_object_vars($this) as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $lines[] = sprintf("  %s: %s", $key, $value);
        }

        return implode("\n", $lines);
    }
}
