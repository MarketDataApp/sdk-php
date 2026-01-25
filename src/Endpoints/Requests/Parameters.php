<?php

namespace MarketDataApp\Endpoints\Requests;

use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;

/**
 * Represents parameters for API requests.
 */
class Parameters implements \Stringable
{

    /**
     * Parameters constructor.
     *
     * @param Format $format The format of the response. Defaults to JSON.
     * @param bool|null $use_human_readable Whether to use human-readable format for values. Defaults to null.
     * @param Mode|null $mode The data feed mode to use. Defaults to null.
     * @param DateFormat|null $date_format The date format for CSV and HTML responses. Can only be used when format=CSV or format=HTML. Defaults to null.
     * @param array|null $columns The columns to include in CSV and HTML responses. Can only be used when format=CSV or format=HTML. Defaults to null.
     * @param bool|null $add_headers Whether to add headers to CSV and HTML responses. Can only be used when format=CSV or format=HTML. Defaults to null.
     * @param string|null $filename File path for CSV and HTML output. Can only be used when format=CSV or format=HTML. Must end with .csv for CSV format or .html for HTML format. Directory must exist. File must not exist. Defaults to null.
     * @throws \InvalidArgumentException If date_format is set but format is not CSV or HTML.
     * @throws \InvalidArgumentException If columns is set but format is not CSV or HTML.
     * @throws \InvalidArgumentException If add_headers is set but format is not CSV or HTML.
     * @throws \InvalidArgumentException If filename is set but format is not CSV or HTML.
     * @throws \InvalidArgumentException If columns contains non-string elements.
     * @throws \InvalidArgumentException If filename has invalid extension, directory doesn't exist, or file already exists.
     */
    public function __construct(
        // Open price.
        public Format $format = Format::JSON,
        public ?bool $use_human_readable = null,
        public ?Mode $mode = null,
        public ?DateFormat $date_format = null,
        public ?array $columns = null,
        public ?bool $add_headers = null,
        public ?string $filename = null,
    ) {
        // Validate that date_format can only be used with CSV or HTML format
        if ($date_format !== null && $format !== Format::CSV && $format !== Format::HTML) {
            throw new \InvalidArgumentException(
                'date_format parameter can only be used with CSV or HTML format. ' .
                'Current format: ' . $format->value
            );
        }

        // Validate that columns can only be used with CSV or HTML format
        if ($columns !== null && $format !== Format::CSV && $format !== Format::HTML) {
            throw new \InvalidArgumentException(
                'columns parameter can only be used with CSV or HTML format. ' .
                'Current format: ' . $format->value
            );
        }

        // Validate that columns array contains only strings
        if ($columns !== null && !empty($columns)) {
            foreach ($columns as $column) {
                if (!is_string($column)) {
                    throw new \InvalidArgumentException(
                        'columns parameter must contain only strings. ' .
                        'Found non-string element: ' . gettype($column)
                    );
                }
            }
        }

        // Validate that add_headers can only be used with CSV or HTML format
        if ($add_headers !== null && $format !== Format::CSV && $format !== Format::HTML) {
            throw new \InvalidArgumentException(
                'add_headers parameter can only be used with CSV or HTML format. ' .
                'Current format: ' . $format->value
            );
        }

        // Validate that filename can only be used with CSV or HTML format
        if ($filename !== null && $format !== Format::CSV && $format !== Format::HTML) {
            throw new \InvalidArgumentException(
                'filename parameter can only be used with CSV or HTML format. ' .
                'Current format: ' . $format->value
            );
        }

        // Validate filename if provided
        if ($filename !== null) {
            // Determine expected extension based on format
            $expectedExtension = $format === Format::CSV ? '.csv' : '.html';
            
            // Validate file extension
            if (!str_ends_with($filename, $expectedExtension)) {
                throw new \InvalidArgumentException(
                    "filename must end with {$expectedExtension}. Got: {$filename}"
                );
            }

            // Validate that the parent directory exists (SDK does not create directories)
            $directory = dirname($filename);
            if ($directory !== '.' && $directory !== '' && !is_dir($directory)) {
                throw new \InvalidArgumentException(
                    "Directory does not exist: {$directory}. Please create the directory before specifying this filename."
                );
            }

            // Validate file does not exist (prevent overwrites)
            if (file_exists($filename)) {
                throw new \InvalidArgumentException(
                    "File already exists: {$filename}"
                );
            }
        }
    }

    /**
     * Returns a string representation of the parameters.
     *
     * @return string Human-readable parameters summary.
     */
    public function __toString(): string
    {
        $parts = ['format=' . $this->format->value];

        if ($this->mode !== null) {
            $parts[] = 'mode=' . $this->mode->value;
        }

        if ($this->date_format !== null) {
            $parts[] = 'date_format=' . $this->date_format->value;
        }

        if ($this->use_human_readable !== null) {
            $parts[] = 'human_readable=' . ($this->use_human_readable ? 'true' : 'false');
        }

        if ($this->add_headers !== null) {
            $parts[] = 'add_headers=' . ($this->add_headers ? 'true' : 'false');
        }

        if ($this->columns !== null) {
            $parts[] = 'columns=[' . implode(',', $this->columns) . ']';
        }

        if ($this->filename !== null) {
            $parts[] = 'filename=' . $this->filename;
        }

        return 'Parameters: ' . implode(', ', $parts);
    }
}
