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

        // Convert to array for easier access to keys with spaces (human-readable format)
        $responseArray = (array) $response;

        // Determine if this is human-readable format (has "Symbol" key) or regular format (has "s" status)
        $isHumanReadable = isset($responseArray['Symbol']);

        if ($isHumanReadable) {
            // Human-readable format - no "s" status field
            $this->status = 'ok';
            $this->option_symbol = $responseArray['Symbol'];
        } else {
            // Regular format
            $this->status = $response->s;
            $this->option_symbol = $response->optionSymbol;
        }
    }

    /**
     * Returns a string representation of the lookup result.
     *
     * @return string Human-readable lookup result.
     */
    public function __toString(): string
    {
        if (!$this->isJson()) {
            return "Lookup - Non-JSON format, use getCsv() or getHtml()";
        }

        return sprintf("Lookup: %s", $this->option_symbol);
    }
}
