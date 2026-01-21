<?php

namespace MarketDataApp\Traits;

/**
 * Trait for input validation methods.
 * 
 * Provides reusable validation methods following the Python SDK's approach:
 * - Does NOT validate date formats strictly (allows relative dates, option expiration dates, etc.)
 * - Only validates date ranges when both dates are parseable
 * - Validates numeric ranges, symbols, resolutions, etc.
 */
trait ValidatesInputs
{
    /**
     * Check if a string can be parsed as a date.
     * Similar to Python SDK's check_is_date() function.
     * Returns true if the value contains "-" or "/" (indicating parseable date format)
     * or is numeric (unix timestamp or spreadsheet format).
     * 
     * This allows relative dates ("today", "yesterday", "-5 days") and option
     * expiration dates ("December expiration") to pass through without validation.
     * 
     * @param string|null $value The value to check
     * @return bool True if the value can be parsed as a date
     */
    protected function canParseAsDate(?string $value): bool
    {
        if ($value === null) {
            return false;
        }
        
        // Check if it contains date-like separators (ISO 8601 or American format)
        if (strpos($value, '-') !== false || strpos($value, '/') !== false) {
            return true;
        }
        
        // Check if it's numeric (unix timestamp or spreadsheet format)
        if (is_numeric($value)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Parse a date string to unix timestamp.
     * Handles ISO 8601, unix timestamps, spreadsheet dates, and American format.
     * 
     * @param string|null $value The date string to parse
     * @return int|null Unix timestamp or null if cannot be parsed
     */
    protected function parseDateToTimestamp(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        
        // Try strtotime first (handles ISO 8601, American format, etc.)
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return $timestamp;
        }
        
        // Try numeric (unix timestamp or spreadsheet)
        if (is_numeric($value)) {
            $num = (float)$value;
            // Spreadsheet dates are typically < 100000
            if ($num > 0 && $num < 100000) {
                // Spreadsheet format - convert to unix timestamp
                // Excel epoch is 1899-12-30, convert days to seconds
                $excelEpoch = strtotime('1899-12-30');
                return $excelEpoch + (int)($num * 86400);
            }
            // Unix timestamp
            return (int)$num;
        }
        
        return null;
    }
    
    /**
     * Validate date range logic.
     * Only validates when both dates can be parsed as dates (following Python SDK approach).
     * This allows relative dates and option expiration dates to pass through.
     * 
     * @param string|null $from The start date
     * @param string|null $to The end date
     * @param int|null $countback The countback value
     * @param string $context Optional context for error messages
     * @return void
     * @throws \InvalidArgumentException If validation fails
     */
    protected function validateDateRange(
        ?string $from,
        ?string $to,
        ?int $countback = null,
        string $context = ''
    ): void {
        // Only validate range if both dates are parseable
        $fromIsDate = $this->canParseAsDate($from);
        $toIsDate = $this->canParseAsDate($to);
        
        if ($fromIsDate && $toIsDate) {
            // Both are parseable - validate range
            $fromTime = $this->parseDateToTimestamp($from);
            $toTime = $this->parseDateToTimestamp($to);
            
            if ($fromTime !== null && $toTime !== null && $fromTime > $toTime) {
                throw new \InvalidArgumentException(
                    "`from` date must be before `to` date. Got: from={$from}, to={$to}"
                );
            }
        }
        
        // Validate countback
        if ($countback !== null && $countback <= 0) {
            throw new \InvalidArgumentException(
                "`countback` must be a positive integer. Got: {$countback}"
            );
        }
    }
    
    /**
     * Validate that an integer is positive if provided.
     * 
     * @param int|null $value The value to validate
     * @param string $fieldName The field name for error messages
     * @return void
     * @throws \InvalidArgumentException If value is not positive
     */
    protected function validatePositiveInteger(?int $value, string $fieldName): void
    {
        if ($value !== null && $value <= 0) {
            throw new \InvalidArgumentException(
                "`{$fieldName}` must be a positive integer. Got: {$value}"
            );
        }
    }
    
    /**
     * Validate that min < max when both are provided.
     * 
     * @param float|null $min The minimum value
     * @param float|null $max The maximum value
     * @param string $minField The minimum field name for error messages
     * @param string $maxField The maximum field name for error messages
     * @return void
     * @throws \InvalidArgumentException If min >= max
     */
    protected function validateNumericRange(
        ?float $min,
        ?float $max,
        string $minField,
        string $maxField
    ): void {
        if ($min !== null && $max !== null && $min >= $max) {
            throw new \InvalidArgumentException(
                "`{$minField}` must be less than `{$maxField}`. Got: {$minField}={$min}, {$maxField}={$max}"
            );
        }
    }
    
    /**
     * Validate that a string is non-empty.
     * 
     * @param string $value The value to validate
     * @param string $fieldName The field name for error messages
     * @return void
     * @throws \InvalidArgumentException If value is empty
     */
    protected function validateNonEmptyString(string $value, string $fieldName): void
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException(
                "`{$fieldName}` must be a non-empty string."
            );
        }
    }
    
    /**
     * Validate that an array is non-empty.
     * 
     * @param array $value The value to validate
     * @param string $fieldName The field name for error messages
     * @return void
     * @throws \InvalidArgumentException If array is empty
     */
    protected function validateNonEmptyArray(array $value, string $fieldName): void
    {
        if (empty($value)) {
            throw new \InvalidArgumentException(
                "`{$fieldName}` must be a non-empty array."
            );
        }
    }
    
    /**
     * Validate symbols array (trim and ensure non-empty).
     * 
     * @param array $symbols The symbols array to validate
     * @return void
     * @throws \InvalidArgumentException If symbols array is invalid
     */
    protected function validateSymbols(array $symbols): void
    {
        $this->validateNonEmptyArray($symbols, 'symbols');
        
        foreach ($symbols as $symbol) {
            if (!is_string($symbol) || trim($symbol) === '') {
                throw new \InvalidArgumentException(
                    "All elements in `symbols` must be non-empty strings."
                );
            }
        }
    }
    
    /**
     * Validate resolution format.
     * Valid resolutions: minutely, hourly, daily, weekly, monthly, yearly,
     * or numeric with optional suffix (1, 3, 5, 15, 30, 45, H, 1H, 2H, D, 1D, 2D, etc.)
     * 
     * @param string $resolution The resolution to validate
     * @return void
     * @throws \InvalidArgumentException If resolution is invalid
     */
    protected function validateResolution(string $resolution): void
    {
        $this->validateNonEmptyString($resolution, 'resolution');
        
        // Pattern matches: numeric with optional suffix, or single letter, or word format
        $pattern = '/^(?:[1-9]\d*(?:[HDWMY])?|[HDWMY]|minutely|hourly|daily|weekly|monthly|yearly)$/i';
        
        if (!preg_match($pattern, $resolution)) {
            throw new \InvalidArgumentException(
                "Invalid resolution format: {$resolution}. " .
                "Expected: minutely, hourly, daily, weekly, monthly, yearly, " .
                "or numeric with optional suffix (e.g., 1, 3, 5, 15, 30, 45, H, 1H, 2H, D, 1D, 2D, etc.)"
            );
        }
    }
    
    /**
     * Validate ISO 3166 two-letter country code.
     * 
     * @param string $country The country code to validate
     * @return void
     * @throws \InvalidArgumentException If country code is invalid
     */
    protected function validateCountryCode(string $country): void
    {
        $this->validateNonEmptyString($country, 'country');
        
        // ISO 3166-1 alpha-2 codes are exactly 2 uppercase letters
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            throw new \InvalidArgumentException(
                "Invalid country code: {$country}. Expected ISO 3166 two-letter code (e.g., US, GB, CA)."
            );
        }
    }
}
