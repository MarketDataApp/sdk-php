# Market Status

This example demonstrates checking market open/closed status, viewing market calendars, and detecting holidays.

## Running the Example

```bash
php examples/market_status.php
```

## What It Covers

- Current market status (open/closed)
- Checking specific dates
- Market calendar for date ranges
- Counting trading days
- Finding the next trading day
- Holiday detection

## Current Market Status

```php
$status = $client->markets->status();
$today = $status->statuses[0];

echo "Date: {$today->date->format('Y-m-d')}\n";
echo "Status: {$today->status}\n";  // 'open' or 'closed'

if ($today->status === 'open') {
    echo "The US stock market is open for trading today.\n";
} else {
    echo "The US stock market is closed today.\n";
}
```

## Check a Specific Date

```php
// Check if Christmas 2024 was a trading day
$christmas = $client->markets->status(date: '2024-12-25');
echo "Christmas: {$christmas->statuses[0]->status}\n";  // closed

// Check July 4th
$july4th = $client->markets->status(date: '2024-07-04');
echo "July 4th: {$july4th->statuses[0]->status}\n";  // closed

// Check a regular Monday
$monday = $client->markets->status(date: '2024-01-15');
echo "Jan 15: {$monday->statuses[0]->status}\n";  // open or closed (MLK Day)
```

## Market Calendar (Date Range)

```php
$calendar = $client->markets->status(
    from: '2024-01-01',
    to: '2024-01-14'
);

foreach ($calendar->statuses as $day) {
    $icon = $day->status === 'open' ? '[OPEN]  ' : '[CLOSED]';
    echo "{$day->date->format('Y-m-d (D)')} {$icon}\n";
}
```

Output:
```
2024-01-01 (Mon) [CLOSED]  <- New Year's Day
2024-01-02 (Tue) [OPEN]
2024-01-03 (Wed) [OPEN]
...
2024-01-06 (Sat) [CLOSED]  <- Weekend
2024-01-07 (Sun) [CLOSED]  <- Weekend
```

## Count Trading Days

```php
$month = $client->markets->status(
    from: '2024-01-01',
    to: '2024-01-31'
);

$tradingDays = 0;
$closedDays = 0;

foreach ($month->statuses as $day) {
    if ($day->status === 'open') {
        $tradingDays++;
    } else {
        $closedDays++;
    }
}

echo "Total calendar days: " . count($month->statuses) . "\n";
echo "Trading days: {$tradingDays}\n";
echo "Closed days: {$closedDays}\n";
```

## Find Next Trading Day

```php
$nextWeek = $client->markets->status(
    from: date('Y-m-d'),
    to: date('Y-m-d', strtotime('+7 days'))
);

foreach ($nextWeek->statuses as $day) {
    if ($day->status === 'open' && $day->date->isFuture()) {
        echo "Next trading day: {$day->date->format('Y-m-d (l)')}\n";
        break;
    }
}
```

## Holiday Detection

Find market holidays (closed days that aren't weekends):

```php
$period = $client->markets->status(
    from: '2024-11-01',
    to: '2024-12-31'
);

echo "Market Holidays (Nov-Dec 2024):\n";
foreach ($period->statuses as $day) {
    if ($day->status === 'closed') {
        $dayOfWeek = $day->date->format('l');

        // Skip regular weekends
        if (in_array($dayOfWeek, ['Saturday', 'Sunday'])) {
            continue;
        }

        echo "  {$day->date->format('Y-m-d (l)')}\n";
    }
}
```

Output:
```
Market Holidays (Nov-Dec 2024):
  2024-11-28 (Thursday)   <- Thanksgiving
  2024-12-25 (Wednesday)  <- Christmas
```

## US Market Holidays

The US stock market is typically closed on:

| Holiday | Date |
|---------|------|
| New Year's Day | January 1 |
| Martin Luther King Jr. Day | Third Monday in January |
| Presidents Day | Third Monday in February |
| Good Friday | Friday before Easter |
| Memorial Day | Last Monday in May |
| Juneteenth | June 19 |
| Independence Day | July 4 |
| Labor Day | First Monday in September |
| Thanksgiving | Fourth Thursday in November |
| Christmas | December 25 |

**Note:** When a holiday falls on a weekend, the market is typically closed on the adjacent Friday (Saturday holiday) or Monday (Sunday holiday).

## Status Properties

| Property | Type | Description |
|----------|------|-------------|
| `date` | Carbon | The date |
| `status` | string | 'open' or 'closed' |

## See Also

- [quick_start.md](quick_start.md) - Basic SDK usage
- [utilities.md](utilities.md) - API status monitoring
