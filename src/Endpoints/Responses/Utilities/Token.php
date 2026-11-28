<?php

namespace MarketDataApp\Endpoints\Responses\Utilities;

/**
 * Represents the result of revoking the current API token.
 */
class Token
{

    /**
     * Status of the delete token request. ok when the token was revoked.
     *
     * @var string
     */
    public string $status;

    /**
     * Token constructor.
     *
     * @param object $response The response object containing the delete token result.
     */
    public function __construct(object $response)
    {
        $this->status = $response->s;
    }

    /**
     * Returns a string representation of the delete token result.
     *
     * @return string Human-readable delete token result.
     */
    public function __toString(): string
    {
        return "Token: " . $this->status;
    }
}
