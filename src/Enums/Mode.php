<?php

namespace MarketDataApp\Enums;

/**
 * Enum Mode
 *
 * Represents the available data feed modes for market data requests.
 */
enum Mode: string
{

    /**
     * Represents live market data.
     */
    case LIVE = 'live';

    /**
     * Represents cached data.
     */
    case CACHED = 'cached';

    /**
     * Represents delayed data.
     */
    case DELAYED = 'delayed';
}
