<?php

/**
 * Options Chain
 *
 * @see options_chain.md for detailed documentation
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MarketDataApp\Client;
use MarketDataApp\Enums\Side;
use MarketDataApp\Enums\Range;
use Psr\Log\NullLogger;

$client = new Client(logger: new NullLogger());
$symbol = 'AAPL';

// Get expiration dates
$expirations = $client->options->expirations($symbol);
echo "Expirations (next 5):\n";
foreach (array_slice($expirations->expirations, 0, 5) as $exp) {
    echo "  {$exp->format('Y-m-d')} ({$exp->format('l')})\n";
}
echo "\n";

$nearestExp = $expirations->expirations[0]->format('Y-m-d');

// Get strikes for expiration
$strikes = $client->options->strikes($symbol, $nearestExp);
$strikeList = $strikes->dates[$nearestExp] ?? [];
echo "Strikes for {$nearestExp}: " . count($strikeList) . " total\n";
echo "  Range: \${$strikeList[0]} - \${$strikeList[count($strikeList) - 1]}\n\n";

// Call options chain (filter by expiration using from/to)
echo "Calls ({$nearestExp}):\n";
$calls = $client->options->option_chain($symbol, from: $nearestExp, to: $nearestExp, side: Side::CALL, strike_limit: 5);
printf("  %-20s %8s %8s %8s %10s\n", "Contract", "Bid", "Ask", "Last", "Volume");
foreach ($calls->getAllQuotes() as $opt) {
    printf("  %-20s %8.2f %8.2f %8.2f %10s\n",
        $opt->option_symbol, $opt->bid, $opt->ask, $opt->last ?? 0, number_format($opt->volume));
}
echo "\n";

// Put options chain
echo "Puts ({$nearestExp}):\n";
$puts = $client->options->option_chain($symbol, from: $nearestExp, to: $nearestExp, side: Side::PUT, strike_limit: 5);
printf("  %-20s %8s %8s %8s %10s\n", "Contract", "Bid", "Ask", "Last", "Volume");
foreach ($puts->getAllQuotes() as $opt) {
    printf("  %-20s %8.2f %8.2f %8.2f %10s\n",
        $opt->option_symbol, $opt->bid, $opt->ask, $opt->last ?? 0, number_format($opt->volume));
}
echo "\n";

// ITM options
echo "In-The-Money Calls:\n";
$itm = $client->options->option_chain($symbol, from: $nearestExp, to: $nearestExp, side: Side::CALL, range: Range::IN_THE_MONEY, strike_limit: 3);
foreach ($itm->getAllQuotes() as $opt) {
    printf("  \$%-7s | Last: \$%.2f | IV: %.1f%%\n", $opt->strike, $opt->last ?? 0, ($opt->implied_volatility ?? 0) * 100);
}
echo "\n";

// Liquid options (high volume/OI)
echo "Liquid Calls (Vol>=100, OI>=1000):\n";
$liquid = $client->options->option_chain($symbol, from: $nearestExp, to: $nearestExp, side: Side::CALL, min_volume: 100, min_open_interest: 1000, strike_limit: 5);
foreach ($liquid->getAllQuotes() as $opt) {
    printf("  \$%-7s | Vol: %6s | OI: %7s | Bid/Ask: \$%.2f/\$%.2f\n",
        $opt->strike, number_format($opt->volume), number_format($opt->open_interest), $opt->bid, $opt->ask);
}
echo "\n";

// Option symbol lookup
$lookup = $client->options->lookup("AAPL 1/17/25 \$200 Call");
echo "Lookup: 'AAPL 1/17/25 \$200 Call' -> {$lookup->option_symbol}\n\n";

// Option quote
$callQuotes = $calls->getAllQuotes();
if (!empty($callQuotes)) {
    $quote = $client->options->quotes($callQuotes[0]->option_symbol);
    $q = $quote->quotes[0];
    echo "Quote for {$q->option_symbol}:\n";
    printf("  Bid: \$%.2f x %d | Ask: \$%.2f x %d | Last: \$%.2f | IV: %.1f%%\n",
        $q->bid, $q->bid_size, $q->ask, $q->ask_size, $q->last ?? 0, ($q->implied_volatility ?? 0) * 100);
    echo "\n";
}

// Greeks
echo "Greeks:\n";
$greeks = $client->options->option_chain($symbol, from: $nearestExp, to: $nearestExp, side: Side::CALL, strike_limit: 3);
printf("  %-7s %7s %7s %7s %7s %7s\n", "Strike", "Delta", "Gamma", "Theta", "Vega", "IV%");
foreach ($greeks->getAllQuotes() as $opt) {
    printf("  \$%-6s %7.4f %7.4f %7.4f %7.4f %6.1f%%\n",
        $opt->strike, $opt->delta ?? 0, $opt->gamma ?? 0, $opt->theta ?? 0, $opt->vega ?? 0, ($opt->implied_volatility ?? 0) * 100);
}
