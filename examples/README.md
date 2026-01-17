# Examples

This directory contains example scripts demonstrating how to use the MarketData PHP SDK.

## Running Examples

All examples automatically read your MarketData API token from environment variables or `.env` file.

### Option 1: Environment Variable (Recommended)

Set the token as an environment variable:

```bash
export MARKETDATA_TOKEN=your_token_here
```

### Option 2: .env File

Create a `.env` file in the project root:

```env
MARKETDATA_TOKEN=your_token_here
```

Then run any example:

```bash
php examples/rate_limit_tracking.php
```

**Note:** You can also pass the token explicitly: `new Client('your_token_here')`

## Available Examples

### rate_limit_tracking.php

Demonstrates how to monitor rate limits during API requests. This example shows:

- How rate limits are automatically tracked by the SDK
- How to access rate limit information using `$client->rate_limits`
- How rate limits update after each API request
- How to check remaining credits before making additional requests

**Key Features:**
- Rate limits are automatically initialized during client construction
- Rate limits are automatically updated after every successful API request
- No manual header extraction or response parsing needed
- Simple property access: `$client->rate_limits->remaining`
