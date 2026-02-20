<?php

namespace MarketDataApp\Enums;

/**
 * Enum DateFormat
 *
 * Represents the available date formats for CSV API responses.
 * Note: This parameter can only be used when format=CSV.
 */
enum DateFormat: string
{

    /**
     * ISO timestamp format (e.g., "2023-01-20T10:30:00Z").
     */
    case TIMESTAMP = 'timestamp';

    /**
     * Unix timestamp format (seconds since epoch, e.g., 1674210600).
     */
    case UNIX = 'unix';

    /**
     * Spreadsheet-compatible format (Excel serial date numbers).
     */
    case SPREADSHEET = 'spreadsheet';
}
