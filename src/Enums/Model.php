<?php

namespace MarketDataApp\Enums;

/**
 * Enum Model
 *
 * Represents the pricing model used for `iv` and the greeks on a historical options request.
 */
enum Model: string
{

    /**
     * Black-Scholes-Merton. Prices every contract, American and European alike.
     */
    case BSM = 'bsm';

    /**
     * Bjerksund-Stensland 2002. Prices American contracts; European contracts keep `bsm`.
     */
    case BJS = 'bjs';

    /**
     * Cox-Ross-Rubinstein binomial tree. Prices American contracts; European contracts keep `bsm`.
     */
    case CRR = 'crr';

    /**
     * Barone-Adesi-Whaley. Prices American contracts; European contracts keep `bsm`.
     */
    case BAW = 'baw';
}
