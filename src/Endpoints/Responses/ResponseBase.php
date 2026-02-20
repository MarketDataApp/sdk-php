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

    /** @var string|null The filename where the response was saved (if filename parameter was used). */
    public ?string $_saved_filename = null;

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

        // Copy _saved_filename if it exists (only set when filename parameter was used)
        if (isset($response->_saved_filename) && $response->_saved_filename !== null) {
            $this->_saved_filename = $response->_saved_filename;
        }
    }

    /**
     * Get the CSV content of the response.
     *
     * @return string The CSV content.
     * @throws \InvalidArgumentException If the response is not in CSV format.
     */
    public function getCsv(): string
    {
        if (!$this->isCsv()) {
            throw new \InvalidArgumentException(
                'getCsv() can only be called on CSV responses. ' .
                'Use isCsv() to check the format before calling.'
            );
        }
        return $this->csv;
    }

    /**
     * Get the HTML content of the response.
     *
     * @return string The HTML content.
     * @throws \InvalidArgumentException If the response is not in HTML format.
     */
    public function getHtml(): string
    {
        if (!$this->isHtml()) {
            throw new \InvalidArgumentException(
                'getHtml() can only be called on HTML responses. ' .
                'Use isHtml() to check the format before calling.'
            );
        }
        return $this->html;
    }

    /**
     * Check if the response is in JSON format.
     *
     * @return bool True if the response is in JSON format, false otherwise.
     */
    public function isJson(): bool
    {
        // Use isset() instead of empty() because empty('') returns true,
        // which would misclassify empty CSV/HTML responses as JSON.
        return !isset($this->csv) && !isset($this->html);
    }

    /**
     * Check if the response is in HTML format.
     *
     * @return bool True if the response is in HTML format, false otherwise.
     */
    public function isHtml(): bool
    {
        return isset($this->html);
    }

    /**
     * Check if the response is in CSV format.
     *
     * @return bool True if the response is in CSV format, false otherwise.
     */
    public function isCsv(): bool
    {
        return isset($this->csv);
    }

    /**
     * Save CSV/HTML content to a file.
     *
     * @param string $filename The file path to save to.
     * @return string The absolute path of the saved file.
     * @throws \InvalidArgumentException If filename is invalid (wrong extension, etc.).
     * @throws \RuntimeException If file writing fails.
     */
    public function saveToFile(string $filename): string
    {
        // Determine content and expected extension
        if ($this->isCsv()) {
            $content = $this->getCsv();
            $expectedExtension = '.csv';
        } elseif ($this->isHtml()) {
            $content = $this->getHtml();
            $expectedExtension = '.html';
        } else {
            throw new \InvalidArgumentException(
                'saveToFile() can only be used with CSV or HTML responses. ' .
                'Current response is in JSON format.'
            );
        }

        // Validate filename extension
        if (!str_ends_with($filename, $expectedExtension)) {
            throw new \InvalidArgumentException(
                "filename must end with {$expectedExtension}. Got: {$filename}"
            );
        }

        // Create directory if needed
        $directory = dirname($filename);
        if ($directory !== '.' && $directory !== '' && !is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new \RuntimeException("Failed to create directory: {$directory}");
            }
        }

        // Write file
        $bytesWritten = file_put_contents($filename, $content);
        if ($bytesWritten === false) {
            throw new \RuntimeException("Failed to write file: {$filename}");
        }

        // Return absolute path
        $absolutePath = realpath($filename);
        return $absolutePath ?: $filename;
    }
}
