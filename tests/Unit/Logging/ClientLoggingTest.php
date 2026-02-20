<?php

namespace MarketDataApp\Tests\Unit\Logging;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Logging\LoggerFactory;
use MarketDataApp\Settings;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for Client logging functionality.
 */
class ClientLoggingTest extends TestCase
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

    public function testClient_withCustomLogger_usesCustomLogger(): void
    {
        $mockLogger = $this->createMockLogger();

        $client = new Client('', $mockLogger);

        $this->assertSame($mockLogger, $client->logger);
    }

    public function testClient_logsInitializationMessage(): void
    {
        $mockLogger = $this->createMockLogger();

        $client = new Client('', $mockLogger);

        // Find the initialization log message
        $initLog = array_filter($mockLogger->logs, fn($log) =>
            $log['level'] === 'info' && str_contains($log['message'], 'initialized')
        );

        $this->assertNotEmpty($initLog, 'Should log initialization message');
    }

    public function testClient_logsObfuscatedTokenAtDebug(): void
    {
        $mockLogger = $this->createMockLogger();

        // Use empty token to avoid API validation call
        $client = new Client('', $mockLogger);

        // Find the token log message
        $tokenLog = array_filter($mockLogger->logs, fn($log) =>
            $log['level'] === 'debug' && str_contains($log['message'], 'Token')
        );

        $this->assertNotEmpty($tokenLog, 'Should log token at debug level');

        // Empty token should be obfuscated to empty string
        $tokenLogEntry = array_values($tokenLog)[0];
        $this->assertArrayHasKey('token', $tokenLogEntry['context']);
        $this->assertEquals('', $tokenLogEntry['context']['token']);
    }

    public function testClient_obfuscatesNonEmptyToken(): void
    {
        // Test the obfuscation logic directly without making API calls
        $reflection = new \ReflectionClass(Client::class);
        $method = $reflection->getMethod('obfuscateToken');

        $obfuscated = $method->invoke(null, 'mySecretToken123');

        $this->assertStringContainsString('*', $obfuscated);
        $this->assertStringEndsWith('n123', $obfuscated);
        $this->assertEquals(strlen('mySecretToken123'), strlen($obfuscated));
    }

    public function testObfuscateToken_withLongToken_showsLast4Chars(): void
    {
        // Use reflection to test the private method
        $reflection = new \ReflectionClass(Client::class);
        $method = $reflection->getMethod('obfuscateToken');

        $result = $method->invoke(null, 'abc123xyz789');

        $this->assertEquals('********z789', $result);
    }

    public function testObfuscateToken_withExactly4Chars_showsAllAsterisks(): void
    {
        $reflection = new \ReflectionClass(Client::class);
        $method = $reflection->getMethod('obfuscateToken');

        $result = $method->invoke(null, 'abcd');

        $this->assertEquals('****', $result);
    }

    public function testObfuscateToken_withShortToken_showsAllAsterisks(): void
    {
        $reflection = new \ReflectionClass(Client::class);
        $method = $reflection->getMethod('obfuscateToken');

        $result = $method->invoke(null, 'ab');

        $this->assertEquals('**', $result);
    }

    public function testObfuscateToken_withEmptyToken_returnsEmptyString(): void
    {
        $reflection = new \ReflectionClass(Client::class);
        $method = $reflection->getMethod('obfuscateToken');

        $result = $method->invoke(null, '');

        $this->assertEquals('', $result);
    }

    public function testObfuscateToken_preservesLength(): void
    {
        $reflection = new \ReflectionClass(Client::class);
        $method = $reflection->getMethod('obfuscateToken');

        $token = 'YVIwZTNXU2tGLTRnMjNqU2VCYTJ6T05LTmNLUm56enhPNmFCTXZhZURHMD0';
        $result = $method->invoke(null, $token);

        $this->assertEquals(strlen($token), strlen($result));
        // Token ends with 'HMD0' (last 4 characters)
        $this->assertStringEndsWith('HMD0', $result);
    }

    public function testClient_withoutLogger_usesFactoryLogger(): void
    {
        // Set up a custom logger via factory
        $mockLogger = $this->createMockLogger();
        LoggerFactory::setLogger($mockLogger);

        $client = new Client('');

        // The client should have used the factory logger
        $this->assertSame($mockLogger, $client->logger);
    }
}
