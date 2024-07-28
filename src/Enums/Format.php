<?php

namespace MarketDataApp\Enums;

enum Format: string
{

    case JSON = 'json';
    case CSV = 'csv';
    case HTML = 'html'; // In beta, use at your own risk
}
