<?php

/**
 * Market Status
 *
 * @see market_status.md for detailed documentation
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MarketDataApp\Client;
use Psr\Log\NullLogger;

$client = new Client(logger: new NullLogger());

// Current market status
$current = $client->markets->status();
$today = $current->statuses[0];
echo "Today ({$today->date->format('Y-m-d l')}): " . ucfirst($today->status) . "\n\n";

// Check specific dates
echo "Specific Dates:\n";
$christmas = $client->markets->status(date: '2024-12-25');
echo "  2024-12-25 (Christmas): " . ucfirst($christmas->statuses[0]->status) . "\n";
$july4th = $client->markets->status(date: '2024-07-04');
echo "  2024-07-04 (July 4th): " . ucfirst($july4th->statuses[0]->status) . "\n";
$monday = $client->markets->status(date: '2024-01-15');
echo "  2024-01-15 (MLK Day): " . ucfirst($monday->statuses[0]->status) . "\n\n";

// Market calendar
echo "Calendar (Jan 1-14, 2024):\n";
$calendar = $client->markets->status(from: '2024-01-01', to: '2024-01-14');
foreach ($calendar->statuses as $day) {
    $icon = $day->status === 'open' ? '[OPEN]  ' : '[CLOSED]';
    echo "  {$day->date->format('Y-m-d (D)')} {$icon}\n";
}
echo "\n";

// Count trading days
$month = $client->markets->status(from: '2024-01-01', to: '2024-01-31');
$trading = count(array_filter($month->statuses, fn($d) => $d->status === 'open'));
$closed = count($month->statuses) - $trading;
echo "January 2024: {$trading} trading days, {$closed} closed days\n\n";

// Next trading day
$nextWeek = $client->markets->status(from: date('Y-m-d'), to: date('Y-m-d', strtotime('+7 days')));
foreach ($nextWeek->statuses as $day) {
    if ($day->status === 'open' && $day->date->isFuture()) {
        echo "Next Trading Day: {$day->date->format('Y-m-d (l)')}\n\n";
        break;
    }
}

// Holiday detection
echo "Holidays (Nov-Dec 2024):\n";
$holidays = $client->markets->status(from: '2024-11-25', to: '2024-12-31');
foreach ($holidays->statuses as $day) {
    if ($day->status === 'closed' && !in_array($day->date->format('l'), ['Saturday', 'Sunday'])) {
        echo "  {$day->date->format('Y-m-d (l)')}\n";
    }
}
