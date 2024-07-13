<?php

namespace MarketDataApp\Endpoints\Responses\Markets;

use Carbon\Carbon;

class Status
{
    public Carbon $date;
    public string $status;
    public function __construct(Carbon $date, string $status)
    {
        $this->date = $date;
        $this->status = $status;
    }
}
