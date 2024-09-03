<?php

namespace MarketDataApp\Enums;

/**
 * Enum Side
 *
 * Represents the types of options in options trading.
 */
enum Side: string
{

    /**
     * Represents a put option.
     *
     * A put option gives the holder the right to sell the underlying asset at a specified price within a specific time
     * period.
     */
    case PUT = 'put';

    /**
     * Represents a call option.
     *
     * A call option gives the holder the right to buy the underlying asset at a specified price within a specific time
     * period.
     */
    case CALL = 'call';
}
