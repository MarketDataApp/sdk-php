<?php

namespace MarketDataApp\Logging;

/**
 * Utility functions for logging.
 */
class LoggingUtilities
{
    /**
     * Format duration in milliseconds to human-readable string.
     *
     * Returns exactly 5 characters for consistent log alignment:
     * - < 1000ms: " 45ms", "999ms" (space-padded)
     * - 1-9.99s:  "1.23s", "9.87s"
     * - 10-99.9s: "12.3s", "99.9s"
     * - 100-999s: " 100s", " 999s" (space-padded)
     * - 1000-9999s: "1000s", "9999s"
     * - >=10000s: "9999s" (clamped at ~2.7 hours)
     *
     * @param float $durationMs Duration in milliseconds.
     *
     * @return string Formatted duration string (exactly 5 characters).
     */
    public static function formatDuration(float $durationMs): string
    {
        if ($durationMs < 1000) {
            return sprintf('%3dms', (int)$durationMs);
        }

        $seconds = $durationMs / 1000;

        if ($seconds < 10) {
            return sprintf('%.2fs', $seconds);
        } elseif ($seconds < 100) {
            return sprintf('%04.1fs', $seconds);
        } elseif ($seconds < 1000) {
            return sprintf('%4ds', (int)$seconds);
        } elseif ($seconds < 10000) {
            return sprintf('%4ds', (int)$seconds);
        }

        return '9999s';
    }
}
