<?php

namespace MarketDataApp\Enums;

enum Range: string
{

    case IN_THE_MONEY = 'itm';
    case OUT_THE_MONEY = 'otm';
    case ALL = 'all';
}
