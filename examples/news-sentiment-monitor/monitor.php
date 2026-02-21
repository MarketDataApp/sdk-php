#!/usr/bin/env php
<?php

/**
 * News Sentiment Monitor
 *
 * Monitor news flow for a watchlist and generate digests.
 *
 * Usage:
 *   php monitor.php                             # Use sample watchlist, last 24h
 *   php monitor.php /path/to/watchlist.txt      # Custom watchlist
 *   php monitor.php --days=7                    # Last 7 days
 *   php monitor.php --html                      # Export HTML digest
 *
 * Note: The news endpoint is in beta.
 *
 * @see plan.md for detailed documentation
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Exceptions\ApiException;

// Parse arguments
$watchlistFile = __DIR__ . '/sample-watchlist.txt';
$daysBack = 1;
$exportHtml = false;
$outputDir = __DIR__ . '/output';

foreach ($argv as $i => $arg) {
    if ($i === 0) continue;

    if ($arg === '--help' || $arg === '-h') {
        showHelp();
        exit(0);
    } elseif ($arg === '--html') {
        $exportHtml = true;
    } elseif (str_starts_with($arg, '--days=')) {
        $daysBack = (int) substr($arg, 7);
    } elseif (str_starts_with($arg, '--output=')) {
        $outputDir = substr($arg, 9);
    } elseif (!str_starts_with($arg, '-')) {
        $watchlistFile = $arg;
    }
}

function showHelp(): void
{
    echo <<<HELP
News Sentiment Monitor - Track news for your watchlist

Usage:
  php monitor.php [options] [watchlist-file]

Options:
  --days=N           Look back N days (default: 1)
  --html             Export digest as HTML file
  --output=DIR       Output directory for HTML (default: ./output)
  --help, -h         Show this help message

Note: The news endpoint is currently in beta.

Examples:
  php monitor.php                            Last 24 hours, sample watchlist
  php monitor.php my-watchlist.txt           Custom watchlist
  php monitor.php --days=7                   Last 7 days of news
  php monitor.php --html                     Export HTML digest

Environment:
  MARKETDATA_TOKEN    Your Market Data API token (required)

HELP;
}

/**
 * Parse watchlist file
 */
function parseWatchlist(string $filepath): array
{
    if (!file_exists($filepath)) {
        throw new \RuntimeException("Watchlist file not found: {$filepath}");
    }

    $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $symbols = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = preg_split('/\s+/', $line);
        $symbol = strtoupper($parts[0]);
        if ($symbol !== '') {
            $symbols[] = $symbol;
        }
    }

    return array_unique($symbols);
}

/**
 * Truncate text to max length
 */
function truncate(string $text, int $maxLength = 80): string
{
    if (strlen($text) <= $maxLength) {
        return $text;
    }
    return substr($text, 0, $maxLength - 3) . '...';
}

// Load watchlist
try {
    $symbols = parseWatchlist($watchlistFile);
} catch (\RuntimeException $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}

if (empty($symbols)) {
    fprintf(STDERR, "Error: No symbols found in watchlist\n");
    exit(1);
}

// Calculate date range
$toDate = date('Y-m-d');
$fromDate = date('Y-m-d', strtotime("-{$daysBack} days"));

echo "=== News Sentiment Monitor ===\n\n";
echo "Watchlist: " . count($symbols) . " symbols\n";
echo "Date Range: {$fromDate} to {$toDate} ({$daysBack} days)\n\n";

try {
    $client = new Client();

    $allNews = [];
    $errors = [];
    $symbolsWithNews = 0;

    echo "Fetching news";

    foreach ($symbols as $symbol) {
        echo ".";

        try {
            $news = $client->stocks->news(
                symbol: $symbol,
                from: $fromDate,
                to: $toDate
            );

            // News now returns all articles in the articles array
            if ($news->status === 'ok' && !empty($news->articles)) {
                if (!isset($allNews[$symbol])) {
                    $allNews[$symbol] = [];
                }
                foreach ($news->articles as $article) {
                    $allNews[$symbol][] = $article;
                }
                $symbolsWithNews++;
            }
        } catch (ApiException $e) {
            $errors[$symbol] = $e->getMessage();
        }
    }

    echo " Done!\n\n";

    if (empty($allNews)) {
        echo "No news found for watchlist in the specified date range.\n";

        if (!empty($errors)) {
            echo "\nNote: Some symbols had errors:\n";
            foreach ($errors as $symbol => $error) {
                echo "  {$symbol}: {$error}\n";
            }
        }
        exit(0);
    }

    // Display news by symbol
    echo str_repeat('=', 80) . "\n";
    echo "NEWS DIGEST\n";
    echo str_repeat('=', 80) . "\n\n";

    $totalArticles = 0;

    foreach ($allNews as $symbol => $articles) {
        echo "\033[1m{$symbol}\033[0m (" . count($articles) . " articles)\n";
        echo str_repeat('-', 60) . "\n";

        foreach ($articles as $article) {
            $date = $article->publication_date->format('M j, H:i');
            $headline = truncate($article->headline ?? 'No headline', 60);
            $source = $article->source ?? 'Unknown';

            echo "  [{$date}] {$headline}\n";
            echo "    Source: {$source}\n";

            if (!empty($article->content)) {
                $preview = truncate(strip_tags($article->content), 70);
                echo "    {$preview}\n";
            }

            echo "\n";
            $totalArticles++;
        }
    }

    // Summary
    echo str_repeat('=', 80) . "\n";
    echo "SUMMARY\n";
    echo str_repeat('-', 80) . "\n";
    echo "  Symbols with news: {$symbolsWithNews} / " . count($symbols) . "\n";
    echo "  Total articles: {$totalArticles}\n";

    // Most active symbols
    $sortedByCount = $allNews;
    uasort($sortedByCount, fn($a, $b) => count($b) <=> count($a));

    echo "\n  Most news activity:\n";
    $shown = 0;
    foreach ($sortedByCount as $symbol => $articles) {
        if ($shown >= 5) break;
        echo "    {$symbol}: " . count($articles) . " articles\n";
        $shown++;
    }

    // Export HTML if requested
    if ($exportHtml) {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $htmlFilename = "news-digest-{$toDate}.html";
        $htmlPath = $outputDir . '/' . $htmlFilename;

        $html = generateHtmlDigest($allNews, $fromDate, $toDate, $symbols);
        file_put_contents($htmlPath, $html);

        echo "\nHTML digest exported to: {$htmlPath}\n";
    }

    if (!empty($errors)) {
        echo "\nSymbols with errors (no news data):\n";
        foreach ($errors as $symbol => $error) {
            echo "  {$symbol}\n";
        }
    }

} catch (\Exception $e) {
    fprintf(STDERR, "\nError: %s\n", $e->getMessage());
    exit(1);
}

echo "\nDone.\n";

/**
 * Generate HTML digest
 */
function generateHtmlDigest(array $allNews, string $fromDate, string $toDate, array $symbols): string
{
    $totalArticles = array_sum(array_map('count', $allNews));

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Digest - {$toDate}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .header {
            background: #1a1a2e;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .header h1 { margin: 0 0 10px 0; }
        .meta { color: #aaa; font-size: 14px; }
        .symbol-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .symbol-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .symbol-name {
            font-size: 24px;
            font-weight: bold;
            color: #1a1a2e;
        }
        .article-count {
            background: #e0e0e0;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 14px;
        }
        .article {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .article:last-child { border-bottom: none; }
        .article-date {
            color: #666;
            font-size: 12px;
        }
        .article-headline {
            font-weight: 600;
            margin: 5px 0;
        }
        .article-source {
            color: #888;
            font-size: 13px;
        }
        .article-preview {
            color: #555;
            font-size: 14px;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>News Digest</h1>
        <div class="meta">
            Period: {$fromDate} to {$toDate}<br>
            Symbols: {$totalArticles} articles across %SYMBOLS_COUNT% symbols
        </div>
    </div>
HTML;

    $html = str_replace('%SYMBOLS_COUNT%', (string) count($allNews), $html);

    foreach ($allNews as $symbol => $articles) {
        $count = count($articles);
        $html .= <<<HTML
    <div class="symbol-section">
        <div class="symbol-header">
            <span class="symbol-name">{$symbol}</span>
            <span class="article-count">{$count} articles</span>
        </div>
HTML;

        foreach ($articles as $article) {
            $date = $article->publication_date->format('M j, Y H:i');
            $headline = htmlspecialchars($article->headline ?? 'No headline');
            $source = htmlspecialchars($article->source ?? 'Unknown');
            $preview = '';

            if (!empty($article->content)) {
                $preview = htmlspecialchars(truncate(strip_tags($article->content), 150));
                $preview = "<div class=\"article-preview\">{$preview}</div>";
            }

            $html .= <<<HTML
        <div class="article">
            <div class="article-date">{$date}</div>
            <div class="article-headline">{$headline}</div>
            <div class="article-source">Source: {$source}</div>
            {$preview}
        </div>
HTML;
        }

        $html .= "    </div>\n";
    }

    $html .= <<<HTML
    <div style="text-align: center; color: #888; padding: 20px;">
        Generated by Market Data PHP SDK
    </div>
</body>
</html>
HTML;

    return $html;
}
