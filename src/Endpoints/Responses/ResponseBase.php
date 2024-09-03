<?php

namespace MarketDataApp\Endpoints\Responses;

/**
 * Base class for API responses.
 *
 * This class provides common functionality for handling different response formats (CSV, HTML, JSON).
 */
class ResponseBase
{

    /** @var string The CSV content of the response. */
    protected string $csv;

    /** @var string The HTML content of the response. */
    protected string $html;

    /**
     * ResponseBase constructor.
     *
     * @param object $response The raw response object from the API.
     */
    public function __construct($response)
    {
        if (isset($response->csv)) {
            $this->csv = $response->csv;
        }

        if (isset($response->html)) {
            $this->html = $response->html;
        }
    }

    /**
     * Get the CSV content of the response.
     *
     * @return string The CSV content.
     */
    public function getCsv(): string
    {
        return $this->csv;
    }

    /**
     * Get the HTML content of the response.
     *
     * @return string The HTML content.
     */
    public function getHtml(): string
    {
        return $this->html;
    }

    /**
     * Check if the response is in JSON format.
     *
     * @return bool True if the response is in JSON format, false otherwise.
     */
    public function isJson(): bool
    {
        return empty($this->csv) && empty($this->html);
    }

    /**
     * Check if the response is in HTML format.
     *
     * @return bool True if the response is in HTML format, false otherwise.
     */
    public function isHtml(): bool
    {
        return !empty($this->html);
    }

    /**
     * Check if the response is in CSV format.
     *
     * @return bool True if the response is in CSV format, false otherwise.
     */
    public function isCsv(): bool
    {
        return !empty($this->csv);
    }
}
