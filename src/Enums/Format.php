<?php

namespace MarketDataApp\Enums;

/**
 * Enum Format
 *
 * Represents the available output formats for market data responses.
 */
enum Format: string
{

    /**
     * Represents JSON format output.
     */
    case JSON = 'json';

    /**
     * Represents CSV format output.
     */
    case CSV = 'csv';

    /**
     * Represents HTML format output.
     *
     * @note This format is in beta and should be used at your own risk.
     */
    case HTML = 'html';
}
