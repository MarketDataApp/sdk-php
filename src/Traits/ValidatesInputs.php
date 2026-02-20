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

        // Check if it's numeric (unix timestamp or spreadsheet format)
        if (is_numeric($value)) {
            return true;
        }

        // Check for specific date patterns (not just any string with - or /)
        // See: https://www.marketdata.app/docs/api/dates-and-times
        $datePatterns = [
            // ISO 8601: YYYY-MM-DD, YYYY-MM, YYYY/MM/DD
            '/^\d{4}-\d{2}(-\d{2})?/',
            '/^\d{4}\/\d{2}(\/\d{2})?/',
            // American: MM/DD/YYYY, MM-DD-YYYY
            '/^\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}/',
            // Relative: -5 days, +1 week, -30 minutes
            '/^[-+]\d+\s*(day|week|month|year|minute|hour)s?/i',
            // Relative keywords: today, yesterday, tomorrow, now
            '/^(today|yesterday|tomorrow|now)$/i',
            // Relative: "X days ago", "X weeks ago"
            '/^\d+\s+(day|week|month|year)s?\s+ago$/i',
            // Option expiration: "this month's expiration", "next week's expiration"
            '/^(this|last|next)\s+(month|week)\'?s?\s+expiration$/i',
            // Option expiration: "expiration in X weeks"
            '/^expiration\s+in\s+\d+\s+weeks?$/i',
        ];

        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
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

        // Check numeric FIRST (unix timestamp or spreadsheet) to avoid
        // strtotime() misinterpreting timestamps like "1234567890" as dates
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

        // Try strtotime (handles ISO 8601, American format, etc.)
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return $timestamp;
        }

        return null;
    }
    
    /**
     * Validate date range logic.
     *
     * Rules:
     * - If `to` is provided, it requires either `from` OR `countback` (but not both)
     * - If both `from` and `to` are parseable dates, validates that `from` < `to`
     * - If `countback` is provided, it must be a positive integer
     *
     * This allows relative dates and option expiration dates to pass through without
     * strict format validation.
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
        // Validate countback first (simple check)
        if ($countback !== null && $countback <= 0) {
            throw new \InvalidArgumentException(
                "`countback` must be a positive integer. Got: {$countback}"
            );
        }

        // If 'to' is provided, it must have either 'from' or 'countback' (but not both)
        if ($to !== null) {
            $hasFrom = $from !== null;
            $hasCountback = $countback !== null;

            if (!$hasFrom && !$hasCountback) {
                throw new \InvalidArgumentException(
                    "`to` requires either `from` or `countback` to be specified."
                );
            }

            if ($hasFrom && $hasCountback) {
                throw new \InvalidArgumentException(
                    "Cannot use both `from` and `countback` with `to`. " .
                    "Use either `from`+`to` or `to`+`countback`."
                );
            }
        }

        // Only validate date order if both dates are parseable
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
     * Validate that a number (int or float) is positive if provided.
     *
     * @param int|float|null $value The value to validate
     * @param string $fieldName The field name for error messages
     * @return void
     * @throws \InvalidArgumentException If value is not positive
     */
    protected function validatePositiveNumber(int|float|null $value, string $fieldName): void
    {
        if ($value !== null && $value <= 0) {
            throw new \InvalidArgumentException(
                "`{$fieldName}` must be a positive number. Got: {$value}"
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
