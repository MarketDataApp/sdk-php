<?php

namespace MarketDataApp\Enums;

/**
 * Enum representing the status result of an API service.
 *
 * Matches the Python SDK's APIStatusResult enum.
 */
enum ApiStatusResult: string
{
    case ONLINE = "online";
    case OFFLINE = "offline";
    case UNKNOWN = "unknown";
}
