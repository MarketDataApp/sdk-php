# ADR-010: Automatic Token Resolution

## Status
Accepted

## Context

API authentication requires a token, but hardcoding tokens in code is a security risk. Different deployment environments (development, staging, production) need different tokens. Users need flexibility in how they provide credentials:
- Environment variables (12-factor app compliance)
- `.env` files (local development)
- Explicit parameters (programmatic access)
- No token (accessing free endpoints)

## Decision

We implemented **automatic token resolution** with a clear priority order:

1. **Explicit Parameter**: `new Client(token: 'your-token')` - highest priority
2. **Environment Variable**: `MARKETDATA_TOKEN` env var
3. **`.env` File**: Via vlucas/phpdotenv
4. **Empty String Fallback**: Allows free symbol access (e.g., AAPL)

### Implementation

```php
// src/Settings.php
class Settings
{
    private static bool $dotenvLoaded = false;

    public static function getToken(?string $explicitToken = null): string
    {
        // Priority 1: Explicit token (even if empty string)
        if ($explicitToken !== null) {
            return $explicitToken;
        }

        // Priority 2: Environment variable
        $envToken = self::getEnvToken();
        if ($envToken !== null && $envToken !== '') {
            return $envToken;
        }

        // Priority 3: .env file
        $dotenvToken = self::getDotenvToken();
        if ($dotenvToken !== null && $dotenvToken !== '') {
            return $dotenvToken;
        }

        // Priority 4: Empty string (allows free symbols)
        return '';
    }

    private static function getEnvToken(): ?string
    {
        // Try getenv() first (most environments)
        $token = getenv('MARKETDATA_TOKEN');
        if ($token !== false && $token !== '') {
            return $token;
        }

        // Try $_ENV (if variables_order includes 'E')
        if (isset($_ENV['MARKETDATA_TOKEN']) && $_ENV['MARKETDATA_TOKEN'] !== '') {
            return $_ENV['MARKETDATA_TOKEN'];
        }

        // Try $_SERVER (always available)
        if (isset($_SERVER['MARKETDATA_TOKEN']) && $_SERVER['MARKETDATA_TOKEN'] !== '') {
            return $_SERVER['MARKETDATA_TOKEN'];
        }

        return null;
    }

    private static function loadDotenv(): void
    {
        $currentDir = getcwd();
        $maxLevels = 5;

        // Search up directory tree for .env file
        while ($levels < $maxLevels) {
            $envFile = $dir . DIRECTORY_SEPARATOR . '.env';
            if (file_exists($envFile) && is_readable($envFile)) {
                $dotenv = Dotenv::createImmutable($dir);
                $dotenv->load();
                return;
            }
            $dir = dirname($dir);
        }
    }
}
```

### Token Validation

```php
// src/ClientBase.php
protected function _setup_rate_limits(): void
{
    // Skip validation for empty token (allows free symbols)
    if ($this->token === '') {
        return;
    }

    try {
        $response = $this->makeRawRequest("user/");
        $this->validateResponseStatusCode($response, true);
        // ... extract rate limits
    } catch (UnauthorizedException $e) {
        // Invalid token - re-throw to prevent client creation
        throw $e;
    } catch (\Exception $e) {
        // Network errors - gracefully continue
    }
}
```

### Usage Patterns

```php
// Explicit token
$client = new Client(token: 'your-api-token');

// Environment variable
// export MARKETDATA_TOKEN=your-api-token
$client = new Client();  // Automatically uses env var

// .env file
// MARKETDATA_TOKEN=your-api-token
$client = new Client();  // Automatically loads from .env

// No token (free symbols only)
$client = new Client(token: '');
$quote = $client->stocks->quote('AAPL');  // Works for free symbols

// Explicit empty token overrides env var
// Even with MARKETDATA_TOKEN set, this uses empty string
$client = new Client(token: '');
```

### Token Obfuscation in Logs

```php
private static function obfuscateToken(string $token): string
{
    if (strlen($token) <= 4) {
        return str_repeat('*', strlen($token));
    }
    // Show last 4 characters only
    return str_repeat('*', strlen($token) - 4) . substr($token, -4);
}

// Logged at DEBUG level: "Token: ****************************xyz9"
```

## Consequences

### Positive
- **Security**: No hardcoded tokens in code
- **Flexibility**: Multiple token sources supported
- **12-Factor Compliance**: Environment variable support
- **Local Development**: `.env` file support
- **Graceful Fallback**: Free symbols work without token

### Negative
- **Magic Behavior**: Token source isn't always obvious
- **Debug Complexity**: Need to check multiple sources
- **Directory Search**: `.env` lookup traverses directories

### Mitigations
- Clear documentation of priority order
- Debug logging shows obfuscated token source
- `.env` search limited to 5 directory levels

## Alternatives Considered

### Alternative 1: Required Token Parameter
```php
public function __construct(string $token)  // Required, no default
```

**Pros**: Explicit, no magic
**Cons**: Breaks free symbol access, verbose configuration

### Alternative 2: Configuration File Only
```php
$config = json_decode(file_get_contents('marketdata.json'));
$client = new Client($config);
```

**Pros**: Single config location
**Cons**: Not 12-factor compliant, requires file management

### Alternative 3: OAuth Flow
```php
$client = Client::authenticate($clientId, $clientSecret);
```

**Pros**: Industry standard for web apps
**Cons**: API doesn't support OAuth, unnecessary complexity

### Alternative 4: Token in URL (Query Parameter)
```php
// ?token=xxx passed to every request
```

**Pros**: Simple implementation
**Cons**: Tokens in logs, URL history; SDK uses Authorization header

## References

- `src/Settings.php:26-59` - Token resolution logic
- `src/Client.php:76-95` - Client construction with token
- `src/ClientBase.php:126-148` - Token validation
- [12-Factor App: Config](https://12factor.net/config)
- [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv)
