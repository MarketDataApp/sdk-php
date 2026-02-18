# ADR-004: Multi-Format Response Support

## Status
Accepted

## Context

The Market Data API supports three response formats:
- **JSON**: Structured data for programmatic access
- **CSV**: Tabular data for spreadsheets and data analysis
- **HTML**: Pre-formatted tables for display (beta)

The SDK needed to handle these different formats while providing a consistent interface. Each format has unique requirements:
- JSON needs parsing into typed response objects
- CSV/HTML are raw strings, optionally saved to files
- All formats support universal parameters like `human` and `mode`

## Decision

We implemented **format-aware response handling** with:

1. **Format Enum**: Type-safe format selection via `Format::JSON`, `Format::CSV`, `Format::HTML`
2. **Universal Parameters**: `Parameters` object controls format and related options
3. **Typed Responses for JSON**: Strongly-typed response objects with business methods
4. **Raw Content for CSV/HTML**: String content with optional file saving

### Implementation

```php
// Format selection via Parameters
$params = new Parameters(format: Format::CSV);

// JSON format returns typed objects
$candles = $client->stocks->candles('AAPL', '2024-01-01', parameters: new Parameters(
    format: Format::JSON
));
foreach ($candles->candles as $candle) {
    echo $candle->close;  // Typed access
}

// CSV format returns raw string
$csvParams = new Parameters(
    format: Format::CSV,
    add_headers: true,
    columns: ['t', 'o', 'h', 'l', 'c'],
    filename: 'candles.csv'  // Optional: save to file
);
$response = $client->stocks->candles('AAPL', '2024-01-01', parameters: $csvParams);
echo $response->csv;  // Raw CSV content

// HTML format (beta)
$htmlParams = new Parameters(format: Format::HTML);
$response = $client->stocks->candles('AAPL', '2024-01-01', parameters: $htmlParams);
echo $response->html;  // Pre-formatted table
```

### Response Processing

```php
// src/ClientBase.php
protected function processResponse($response, string $format, array $arguments): object
{
    switch ($format) {
        case 'csv':
        case 'html':
            $content = (string)$response->getBody();
            $responseObject = (object)[$format => $content];

            // Optional file saving
            if (isset($arguments['_filename'])) {
                file_put_contents($arguments['_filename'], $content);
                $responseObject->_saved_filename = $arguments['_filename'];
            }
            return $responseObject;

        case 'json':
        default:
            $json = (string)$response->getBody();
            return json_decode($json);
    }
}
```

### CSV/HTML-Specific Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `date_format` | `DateFormat` | Date formatting for timestamps |
| `columns` | `array` | Select specific columns |
| `add_headers` | `bool` | Include column headers |
| `filename` | `string` | Save output to file |

## Consequences

### Positive
- **Flexibility**: Users choose format based on use case
- **Type Safety**: JSON responses have full IDE support
- **File Output**: CSV/HTML can be saved directly to disk
- **Validation**: Invalid format combinations caught early

### Negative
- **Complexity**: Three code paths for format handling
- **Parameter Restrictions**: Some params only valid for CSV/HTML
- **Response Variance**: Return type varies by format

### Mitigations
- Clear validation errors for invalid combinations
- Documentation explains format-specific parameters
- Response objects always have predictable structure

## Alternatives Considered

### Alternative 1: Separate Methods per Format
```php
$client->stocks->candlesJson('AAPL', ...);
$client->stocks->candlesCsv('AAPL', ...);
$client->stocks->candlesHtml('AAPL', ...);
```

**Pros**: Clear return types per method
**Cons**: API surface explosion, code duplication

### Alternative 2: Format as Method Suffix
```php
$client->stocks->candles('AAPL')->toJson();
$client->stocks->candles('AAPL')->toCsv();
```

**Pros**: Fluent interface, clear conversion
**Cons**: Extra API call needed, cannot request format from server

### Alternative 3: Always Return JSON, Convert Client-Side
```php
$candles = $client->stocks->candles('AAPL');
$csv = $candles->toCsv();  // Client-side conversion
```

**Pros**: Consistent return type
**Cons**: Cannot leverage server-side formatting, more data transfer

## References

- `src/Enums/Format.php` - Format enum definition
- `src/Endpoints/Requests/Parameters.php` - Parameter handling
- `src/ClientBase.php:629-708` - Response processing
- `src/Traits/UniversalParameters.php` - Format parameter merging
