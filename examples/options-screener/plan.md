# Options Screener

## Purpose

Screen for liquid options opportunities based on specific strategy criteria, helping options traders identify potential covered calls and cash-secured puts.

## Target Audience

Options traders looking for income-generating strategies with specific risk/reward profiles.

## SDK Features Demonstrated

### Primary Features
- **Option Chains** (`$client->options->option_chain()`) - Full chain with filtering
- **Greeks Analysis** - Delta, theta, IV filtering
- **Range Filtering** - ITM/OTM/ATM options
- **Volume/OI Thresholds** - Liquidity screening

### Secondary Features
- **Expirations** (`$client->options->expirations()`) - Find available expirations
- **Strikes** (`$client->options->strikes()`) - Available strike prices
- **Side Filtering** - Call vs Put isolation

## Strategies Implemented

### 1. Covered Call Screener
Find call options to sell against existing stock positions:
- OTM calls (typically 0.20-0.35 delta)
- 15-45 DTE for optimal theta decay
- Minimum premium threshold
- Volume/OI for liquidity

### 2. Cash-Secured Put Screener
Find puts to sell for income generation:
- OTM puts (typically 0.15-0.30 delta)
- 30-60 DTE for premium collection
- Strike at acceptable assignment price
- High IV rank preferred

## Usage

```bash
# Run main screener with default settings
php screener.php AAPL

# Run covered call strategy
php strategies/covered-call.php AAPL

# Run cash-secured put strategy
php strategies/cash-secured-put.php AAPL --budget=5000

# Screen multiple symbols
php screener.php AAPL,MSFT,GOOGL
```

## Implementation Notes

- Uses option_chain with extensive filtering to minimize data transfer
- Calculates annualized return for premium income
- Shows bid-ask spread as percentage for liquidity assessment
- Filters by minimum open interest for exit liquidity
