<?php

namespace MarketDataApp\Enums;

/**
 * Enum Range
 *
 * Represents the range options for market data queries.
 */
enum Range: string
{

    /**
     * Represents options that are "in the money".
     */
    case IN_THE_MONEY = 'itm';

    /**
     * Represents options that are "out of the money".
     */
    case OUT_OF_THE_MONEY = 'otm';

    /**
     * Represents all options.
     */
    case ALL = 'all';
}
