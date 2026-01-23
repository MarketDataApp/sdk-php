<?php

namespace MarketDataApp\Tests\Unit\Logging;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Utilities;
use MarketDataApp\Logging\LoggerFactory;
use MarketDataApp\Settings;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for request logging in ClientBase.
 */
class RequestLoggingTest extends TestCase
{
    private ?string $originalToken = null;
    private ?string $originalLogLevel = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Save and clear env vars
        $this->originalToken = getenv('MARKETDATA_TOKEN') ?: null;
        $this->originalLogLevel = getenv('MARKETDATA_LOGGING_LEVEL') ?: null;

        putenv('MARKETDATA_TOKEN');
        putenv('MARKETDATA_LOGGING_LEVEL=NONE');
        unset($_ENV['MARKETDATA_TOKEN']);
        unset($_SERVER['MARKETDATA_TOKEN']);
        $_ENV['MARKETDATA_LOGGING_LEVEL'] = 'NONE';

        // Reset singletons
        LoggerFactory::resetLogger();
        Utilities::clearApiStatusCache();

        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);
    }

    protected function tearDown(): void
    {
        // Restore env vars
        if ($this->originalToken !== null) {
            putenv("MARKETDATA_TOKEN={$this->originalToken}");
            $_ENV['MARKETDATA_TOKEN'] = $this->originalToken;
        } else {
            putenv('MARKETDATA_TOKEN');
            unset($_ENV['MARKETDATA_TOKEN']);
        }

        if ($this->originalLogLevel !== null) {
            putenv("MARKETDATA_LOGGING_LEVEL={$this->originalLogLevel}");
            $_ENV['MARKETDATA_LOGGING_LEVEL'] = $this->originalLogLevel;
        } else {
            putenv('MARKETDATA_LOGGING_LEVEL');
            unset($_ENV['MARKETDATA_LOGGING_LEVEL']);
        }

        LoggerFactory::resetLogger();

        parent::tearDown();
    }

    /**
     * Create a mock logger that records all log calls.
     */
    private function createMockLogger(): LoggerInterface
    {
        return new class implements LoggerInterface {
            public array $logs = [];

            public function emergency(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => 'emergency', 'message' => $message, 'context' => $context];
            }

            public function alert(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => 'alert', 'message' => $message, 'context' => $context];
            }

            public function critical(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => 'critical', 'message' => $message, 'context' => $context];
            }

            public function error(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => 'error', 'message' => $message, 'context' => $context];
            }

            public function warning(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
            }

            public function notice(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => 'notice', 'message' => $message, 'context' => $context];
            }

            public function info(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => 'info', 'message' => $message, 'context' => $context];
            }

            public function debug(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => 'debug', 'message' => $message, 'context' => $context];
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }
        };
    }

    private function createClientWithMockResponses(array $responses, LoggerInterface $logger): Client
    {
        $client = new Client('', $logger);

        $mockHandler = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockGuzzle = new GuzzleClient(['handler' => $handlerStack]);
        $client->setGuzzle($mockGuzzle);

        return $client;
    }

    public function testExecute_logsRequestAtInfoLevel(): void
    {
        $mockLogger = $this->createMockLogger();

        // Mock response: synthetic test data
        $mockResponse = new Response(200, [
            'x-api-ratelimit-limit' => '100',
            'x-api-ratelimit-remaining' => '99',
            'x-api-ratelimit-reset' => (string)(time() + 3600),
            'x-api-ratelimit-consumed' => '1',
            'cf-ray' => 'test-ray-id-123',
        ], json_encode(['s' => 'ok', 'symbol' => ['AAPL'], 'ask' => [150.00]]));

        $client = $this->createClientWithMockResponses([$mockResponse], $mockLogger);

        $client->execute('v1/stocks/quotes/AAPL', ['format' => 'json']);

        // Find request log
        $requestLogs = array_filter($mockLogger->logs, fn($log) =>
            $log['level'] === 'info' && str_contains($log['message'], 'GET 200')
        );

        $this->assertNotEmpty($requestLogs, 'Should log request at info level');

        $logEntry = array_values($requestLogs)[0];
        $this->assertStringContainsString('GET', $logEntry['message']);
        $this->assertStringContainsString('200', $logEntry['message']);
        $this->assertStringContainsString('test-ray-id-123', $logEntry['message']);
        $this->assertStringContainsString('v1/stocks/quotes/AAPL', $logEntry['message']);
    }

    public function testExecute_logsFullUrlWithQueryParams(): void
    {
        $mockLogger = $this->createMockLogger();

        // Mock response: synthetic test data
        $mockResponse = new Response(200, [
            'x-api-ratelimit-limit' => '100',
            'x-api-ratelimit-remaining' => '99',
            'x-api-ratelimit-reset' => (string)(time() + 3600),
            'x-api-ratelimit-consumed' => '1',
            'cf-ray' => 'ray-123',
        ], json_encode(['s' => 'ok']));

        $client = $this->createClientWithMockResponses([$mockResponse], $mockLogger);

        $client->execute('v1/stocks/quotes/AAPL', ['format' => 'json', 'mode' => 'live']);

        // Find request log
        $requestLogs = array_filter($mockLogger->logs, fn($log) =>
            str_contains($log['message'], 'GET 200')
        );

        $logEntry = array_values($requestLogs)[0];

        // Should contain full URL with query params
        $this->assertStringContainsString('format=json', $logEntry['message']);
        $this->assertStringContainsString('mode=live', $logEntry['message']);
    }

    public function testMakeRawRequest_logsAtDebugLevel(): void
    {
        $mockLogger = $this->createMockLogger();

        // Mock response: synthetic test data
        $mockResponse = new Response(200, [
            'x-api-ratelimit-limit' => '100',
            'x-api-ratelimit-remaining' => '99',
            'x-api-ratelimit-reset' => (string)(time() + 3600),
            'x-api-ratelimit-consumed' => '1',
            'cf-ray' => 'user-ray-id',
        ], json_encode(['status' => 'ok']));

        $client = $this->createClientWithMockResponses([$mockResponse], $mockLogger);

        $client->makeRawRequest('user/');

        // Find request log at debug level
        $debugLogs = array_filter($mockLogger->logs, fn($log) =>
            $log['level'] === 'debug' && str_contains($log['message'], 'GET 200')
        );

        $this->assertNotEmpty($debugLogs, 'makeRawRequest should log at debug level');
    }

    public function testIsInternalRequest_userEndpoint_returnsTrue(): void
    {
        // Use reflection to test the protected method
        $client = new Client('', $this->createMockLogger());
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('isInternalRequest');

        $this->assertTrue($method->invoke($client, 'user/'));
    }

    public function testIsInternalRequest_statusEndpoint_returnsTrue(): void
    {
        $client = new Client('', $this->createMockLogger());
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('isInternalRequest');

        $this->assertTrue($method->invoke($client, 'utilities/status'));
    }

    public function testIsInternalRequest_stocksEndpoint_returnsFalse(): void
    {
        $client = new Client('', $this->createMockLogger());
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('isInternalRequest');

        $this->assertFalse($method->invoke($client, 'v1/stocks/quotes/AAPL'));
    }

    public function testLogRequest_formatIncludesAllComponents(): void
    {
        $mockLogger = $this->createMockLogger();

        // Mock response: synthetic test data
        $mockResponse = new Response(201, [
            'cf-ray' => 'abc123-XYZ',
        ], '');

        // Use reflection to test logRequest directly
        $client = new Client('', $mockLogger);
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('logRequest');

        $method->invoke($client, 'POST', $mockResponse, 123.45, 'https://api.example.com/test?foo=bar', 'info');

        // Find the log entry
        $logEntry = array_filter($mockLogger->logs, fn($log) =>
            str_contains($log['message'], 'POST')
        );

        $this->assertNotEmpty($logEntry);
        $entry = array_values($logEntry)[0];

        // Verify format: METHOD STATUS DURATION REQUEST_ID URL
        $this->assertStringContainsString('POST', $entry['message']);
        $this->assertStringContainsString('201', $entry['message']);
        $this->assertStringContainsString('123ms', $entry['message']);
        $this->assertStringContainsString('abc123-XYZ', $entry['message']);
        $this->assertStringContainsString('https://api.example.com/test?foo=bar', $entry['message']);
    }

    public function testLogRequest_withMissingCfRay_usesDash(): void
    {
        $mockLogger = $this->createMockLogger();

        // Response without cf-ray header
        $mockResponse = new Response(200, [], '');

        $client = new Client('', $mockLogger);
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('logRequest');

        $method->invoke($client, 'GET', $mockResponse, 50, 'https://api.example.com/test', 'info');

        $logEntry = array_values(array_filter($mockLogger->logs, fn($log) =>
            str_contains($log['message'], 'GET')
        ))[0];

        // Should use '-' when cf-ray is missing
        $this->assertStringContainsString(' - ', $logEntry['message']);
    }

    public function testExecute_404Response_stillLogsRequest(): void
    {
        $mockLogger = $this->createMockLogger();

        // Mock 404 response
        $mockResponse = new Response(404, [
            'x-api-ratelimit-limit' => '100',
            'x-api-ratelimit-remaining' => '99',
            'x-api-ratelimit-reset' => (string)(time() + 3600),
            'x-api-ratelimit-consumed' => '1',
            'cf-ray' => '404-ray',
        ], json_encode(['s' => 'no_data', 'errmsg' => 'No data found']));

        // Create a mock handler that returns 404
        $mockHandler = new MockHandler([
            new \GuzzleHttp\Exception\ClientException(
                'Not Found',
                new \GuzzleHttp\Psr7\Request('GET', 'test'),
                $mockResponse
            )
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockGuzzle = new GuzzleClient(['handler' => $handlerStack]);

        $client = new Client('', $mockLogger);
        $client->setGuzzle($mockGuzzle);

        // 404 should return as response, not throw
        $client->execute('v1/stocks/quotes/INVALID', ['format' => 'json']);

        // Should have logged the 404
        $requestLogs = array_filter($mockLogger->logs, fn($log) =>
            str_contains($log['message'], 'GET 404')
        );

        $this->assertNotEmpty($requestLogs, 'Should log 404 responses');
    }
}
