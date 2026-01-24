<?php

namespace MarketDataApp\Traits;

use Carbon\Carbon;

/**
 * Trait for formatting values in __toString() methods.
 *
 * Provides consistent formatting for currency, percentages, volumes,
 * dates, and other common display values across all SDK response objects.
 */
trait FormatsForDisplay
{
    /**
     * Format a float as currency (e.g., "$150.25").
     *
     * @param float|null $value The value to format.
     *
     * @return string Formatted currency string or "N/A" if null.
     */
    protected function formatCurrency(?float $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return '$' . number_format($value, 2);
    }

    /**
     * Format a percentage with sign (e.g., "+3.25%" or "-1.50%").
     * Assumes input is a decimal (0.30 = 30%).
     *
     * @param float|null $value The decimal value to format as percentage.
     *
     * @return string Formatted percentage string or "N/A" if null.
     */
    protected function formatPercent(?float $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        $percent = $value * 100;
        $sign = $percent >= 0 ? '+' : '';

        return $sign . number_format($percent, 2) . '%';
    }

    /**
     * Format a percentage that is already in percent form (e.g., "32.50%").
     * Use this for values like implied volatility that are already percentages.
     *
     * @param float|null $value The percentage value to format.
     *
     * @return string Formatted percentage string or "N/A" if null.
     */
    protected function formatPercentRaw(?float $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return number_format($value * 100, 2) . '%';
    }

    /**
     * Format volume with K/M/B suffixes (e.g., "54.9M", "12.3K").
     *
     * @param int|null $value The volume to format.
     *
     * @return string Formatted volume string or "N/A" if null.
     */
    protected function formatVolume(?int $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        if ($value >= 1_000_000_000) {
            return number_format($value / 1_000_000_000, 1) . 'B';
        }

        if ($value >= 1_000_000) {
            return number_format($value / 1_000_000, 1) . 'M';
        }

        if ($value >= 1_000) {
            return number_format($value / 1_000, 1) . 'K';
        }

        return (string) $value;
    }

    /**
     * Format a Carbon date with time (e.g., "Jan 24, 2026 3:45 PM").
     *
     * @param Carbon|null $date The date to format.
     *
     * @return string Formatted date string or "N/A" if null.
     */
    protected function formatDateTime(?Carbon $date): string
    {
        if ($date === null) {
            return 'N/A';
        }

        return $date->format('M j, Y g:i A');
    }

    /**
     * Format a Carbon date without time (e.g., "Jan 24, 2026").
     *
     * @param Carbon|null $date The date to format.
     *
     * @return string Formatted date string or "N/A" if null.
     */
    protected function formatDate(?Carbon $date): string
    {
        if ($date === null) {
            return 'N/A';
        }

        return $date->format('M j, Y');
    }

    /**
     * Format a Greek value (4 decimal places, e.g., "0.4520").
     *
     * @param float|null $value The Greek value to format.
     *
     * @return string Formatted Greek string or "N/A" if null.
     */
    protected function formatGreek(?float $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return number_format($value, 4);
    }

    /**
     * Format a number with commas (e.g., "15,234").
     *
     * @param int|null $value The number to format.
     *
     * @return string Formatted number string or "N/A" if null.
     */
    protected function formatNumber(?int $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return number_format($value);
    }

    /**
     * Format a change value with sign and currency (e.g., "+$1.25" or "-$0.50").
     *
     * @param float|null $value The change value to format.
     *
     * @return string Formatted change string or "N/A" if null.
     */
    protected function formatChange(?float $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        $sign = $value >= 0 ? '+' : '';

        return $sign . '$' . number_format(abs($value), 2);
    }
}
