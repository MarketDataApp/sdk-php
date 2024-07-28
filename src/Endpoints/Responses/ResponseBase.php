<?php

namespace MarketDataApp\Endpoints\Responses;

class ResponseBase
{

    protected string $csv;
    protected string $html;

    public function __construct($response)
    {
        if (isset($response->csv)) {
            $this->csv = $response->csv;
        }

        if (isset($response->html)) {
            $this->html = $response->html;
        }
    }

    public function getCsv(): string
    {
        return $this->csv;
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function isJson(): bool
    {
        return empty($this->csv) && empty($this->html);
    }

    public function isHtml(): bool
    {
        return !empty($this->html);
    }

    public function isCsv(): bool
    {
        return !empty($this->csv);
    }
}
