<?php

namespace MarketDataApp\Tests\Unit;

use MarketDataApp\Enums\Format;
use MarketDataApp\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Settings class.
 *
 * Tests token resolution logic with various scenarios.
 */
class SettingsTest extends TestCase
{
    /**
     * Original environment variable values to restore after tests.
     */
    private $originalToken = false;
    private $originalEnvToken = null;
    private $originalServerToken = null;

    /**
     * Temporary directories created during tests.
     */
    private array $tempDirs = [];

    /**
     * Temporary files created during tests.
     */
    private array $tempFiles = [];

    /**
     * Original working directory.
     */
    private string $originalCwd;

    /**
     * Save original environment variable state before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Save original working directory - use realpath to get absolute path
        $cwd = getcwd();
        $this->originalCwd = $cwd ? realpath($cwd) : $cwd;
        
        // Save original values
        $this->originalToken = getenv('MARKETDATA_TOKEN');
        $this->originalEnvToken = $_ENV['MARKETDATA_TOKEN'] ?? null;
        $this->originalServerToken = $_SERVER['MARKETDATA_TOKEN'] ?? null;
        
        // Reset Settings dotenv loaded flag
        $this->resetDotenvLoaded();
    }

    /**
     * Restore original environment variable state after each test.
     */
    protected function tearDown(): void
    {
        // Restore working directory FIRST, before any other cleanup
        // This is critical to prevent affecting subsequent tests
        // Use @ to suppress errors if directory no longer exists
        if (isset($this->originalCwd) && $this->originalCwd !== false && is_dir($this->originalCwd)) {
            @chdir($this->originalCwd);
        } elseif (isset($this->originalCwd) && $this->originalCwd) {
            // Try to restore even if directory check fails (in case of permission issues)
            @chdir($this->originalCwd);
        }

        // Restore original environment variable state
        if ($this->originalToken !== false) {
            putenv('MARKETDATA_TOKEN=' . $this->originalToken);
        } else {
            putenv('MARKETDATA_TOKEN');
        }
        
        if ($this->originalEnvToken !== null) {
            $_ENV['MARKETDATA_TOKEN'] = $this->originalEnvToken;
        } else {
            unset($_ENV['MARKETDATA_TOKEN']);
        }
        
        if ($this->originalServerToken !== null) {
            $_SERVER['MARKETDATA_TOKEN'] = $this->originalServerToken;
        } else {
            unset($_SERVER['MARKETDATA_TOKEN']);
        }

        // Clean up temporary files and directories
        $this->cleanupTempFiles();
        
        // Note: We don't reset dotenvLoaded in tearDown to avoid affecting subsequent tests
        // Each test should reset it in setUp if needed
        
        parent::tearDown();
    }

    /**
     * Test that explicit token takes highest precedence.
     *
     * @return void
     */
    public function testGetToken_explicitToken_takesPrecedence()
    {
        // Set up environment variable
        putenv('MARKETDATA_TOKEN=env_token_value');
        $_ENV['MARKETDATA_TOKEN'] = 'env_token_value';

        // Explicit token should be used even if env var is set
        $token = Settings::getToken('explicit_token');
        $this->assertEquals('explicit_token', $token);
    }

    /**
     * Test that environment variable is used when no explicit token.
     *
     * @return void
     */
    public function testGetToken_envVar_usedWhenNoExplicit()
    {
        // Set environment variable
        $testToken = 'test_env_token_123';
        putenv('MARKETDATA_TOKEN=' . $testToken);
        $_ENV['MARKETDATA_TOKEN'] = $testToken;
        $_SERVER['MARKETDATA_TOKEN'] = $testToken;

        // No explicit token, should use env var
        $token = Settings::getToken(null);
        $this->assertEquals($testToken, $token);
    }

    /**
     * Test that empty string is returned when no token sources available.
     *
     * @return void
     */
    public function testGetToken_noSources_returnsEmptyString()
    {
        // Clear all token sources
        putenv('MARKETDATA_TOKEN');
        unset($_ENV['MARKETDATA_TOKEN']);
        unset($_SERVER['MARKETDATA_TOKEN']);

        // Run outside the repository so a developer's local .env file is not
        // itself a token source for this test.
        $tempDir = $this->createTempDir();
        $originalCwd = getcwd();
        try {
            chdir($tempDir);
            $this->resetDotenvLoaded();

            // Should return empty string as fallback
            $token = Settings::getToken(null);
            $this->assertEquals('', $token);
        } finally {
            if ($originalCwd && is_dir($originalCwd)) {
                @chdir($originalCwd);
            }
        }
    }

    /**
     * Test that explicit empty string is preserved.
     *
     * @return void
     */
    public function testGetToken_explicitEmptyString_preserved()
    {
        // Set environment variable
        putenv('MARKETDATA_TOKEN=env_token_value');
        $_ENV['MARKETDATA_TOKEN'] = 'env_token_value';

        // Explicit empty string should be used (not env var)
        $token = Settings::getToken('');
        $this->assertEquals('', $token);
    }

    /**
     * Test that getenv() is checked first for environment variables.
     *
     * @return void
     */
    public function testGetToken_getenvCheckedFirst()
    {
        $testToken = 'getenv_token_value';
        putenv('MARKETDATA_TOKEN=' . $testToken);

        $token = Settings::getToken(null);
        $this->assertEquals($testToken, $token);
    }

    /**
     * Test that $_ENV is checked as fallback.
     *
     * @return void
     */
    public function testGetToken_envVarFallback()
    {
        // Clear getenv() first to test $_ENV fallback
        putenv('MARKETDATA_TOKEN');
        
        // Note: This test may not work if variables_order doesn't include 'E'
        // But it's good to test the fallback logic
        $testToken = 'env_var_token_value';
        $_ENV['MARKETDATA_TOKEN'] = $testToken;
        $_SERVER['MARKETDATA_TOKEN'] = $testToken;

        $token = Settings::getToken(null);
        $this->assertEquals($testToken, $token);
    }

    /**
     * Test that $_SERVER is checked as last resort fallback.
     * 
     * Covers line 81: return $_SERVER['MARKETDATA_TOKEN'];
     *
     * @return void
     */
    public function testGetToken_serverVarFallback()
    {
        // Clear getenv() and $_ENV to test $_SERVER fallback
        putenv('MARKETDATA_TOKEN');
        unset($_ENV['MARKETDATA_TOKEN']);
        
        // Set only $_SERVER
        $testToken = 'server_token_value';
        $_SERVER['MARKETDATA_TOKEN'] = $testToken;

        $token = Settings::getToken(null);
        $this->assertEquals($testToken, $token);
    }

    /**
     * Test that token is loaded from .env file when environment variables are not set.
     * 
     * Covers lines 54 and 106: return $dotenvToken; and return $token;
     *
     * @return void
     */
    public function testGetToken_fromDotenvFile()
    {
        // Clear all environment variables
        putenv('MARKETDATA_TOKEN');
        unset($_ENV['MARKETDATA_TOKEN']);
        unset($_SERVER['MARKETDATA_TOKEN']);

        // Create temporary directory and .env file
        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, ['MARKETDATA_TOKEN' => 'dotenv_token_value']);
        
        $originalCwd = getcwd();
        try {
            chdir($tempDir);

            // Reset dotenv loaded flag to force reload
            $this->resetDotenvLoaded();

            $token = Settings::getToken(null);
            $this->assertEquals('dotenv_token_value', $token);
        } finally {
            // Always restore directory
            if ($originalCwd && is_dir($originalCwd)) {
                @chdir($originalCwd);
            }
        }
    }

    /**
     * Test loadDotenv() when getcwd() returns false.
     * 
     * Note: Line 124 (early return when getcwd() is false) is extremely difficult to test
     * without PHP extensions like uopz or runkit that allow function mocking.
     * The condition occurs in rare edge cases (e.g., current directory deleted, permission issues).
     * This test exercises the loadDotenv() path but may not hit line 124 specifically.
     * 
     * To fully test line 124, you would need to:
     * 1. Use uopz extension: uopz_set_return('getcwd', false)
     * 2. Or use runkit extension for function redefinition
     * 3. Or manually trigger the edge case (delete current directory while in it)
     *
     * @return void
     */
    public function testLoadDotenv_getcwdFalse()
    {
        // Save current directory
        $originalCwd = getcwd();
        $tempDir = null;
        
        try {
            // Change to a directory that exists
            $tempDir = $this->createTempDir();
            chdir($tempDir);
            
            // Reset dotenv loaded flag
            $this->resetDotenvLoaded();
            
            // Clear environment variables
            putenv('MARKETDATA_TOKEN');
            unset($_ENV['MARKETDATA_TOKEN']);
            unset($_SERVER['MARKETDATA_TOKEN']);
            
            // Test that normal operation works (exercises loadDotenv path)
            // Note: This test may not actually hit line 124 without function mocking
            $this->createTempEnvFile($tempDir, ['MARKETDATA_TOKEN' => 'test_token']);
            $token = Settings::getToken(null);
            $this->assertEquals('test_token', $token);
        } finally {
            // Always restore directory, even if test fails
            if ($originalCwd && is_dir($originalCwd)) {
                @chdir($originalCwd);
            }
        }
    }

    /**
     * Test loadDotenv() exception handling when Dotenv fails to load.
     * 
     * Covers lines 139 and 142: catch block and return from exception handler
     *
     * @return void
     */
    public function testLoadDotenv_exceptionHandling()
    {
        // Create temporary directory
        $tempDir = $this->createTempDir();
        $originalCwd = getcwd();
        
        try {
            chdir($tempDir);

            // Create a .env file with invalid syntax that will cause Dotenv to throw an exception
            // Dotenv throws InvalidFileException for unclosed SINGLE quotes (not double quotes)
            // Double quotes don't throw, but single quotes do
            $envFile = $tempDir . '/.env';
            file_put_contents($envFile, "MARKETDATA_TOKEN='unclosed_quote_value");
            $this->tempFiles[] = $envFile;

            // Reset dotenv loaded flag
            $this->resetDotenvLoaded();

            // Clear environment variables
            putenv('MARKETDATA_TOKEN');
            unset($_ENV['MARKETDATA_TOKEN']);
            unset($_SERVER['MARKETDATA_TOKEN']);

            // Try to get token - should gracefully handle the exception
            // The exception should be caught and the method should return silently
            $token = Settings::getToken(null);
            
            // Should return empty string since .env loading failed
            $this->assertEquals('', $token);
        } finally {
            // Always restore directory
            if ($originalCwd && is_dir($originalCwd)) {
                @chdir($originalCwd);
            }
        }
    }

    /**
     * Test loadDotenv() filesystem root detection.
     * 
     * Covers line 149: break; (when filesystem root is reached)
     *
     * @return void
     */
    public function testLoadDotenv_filesystemRootDetection()
    {
        // Create a directory structure without .env file
        $tempDir = $this->createTempDir();
        $childDir = $tempDir . '/child';
        $grandchildDir = $childDir . '/grandchild';
        mkdir($grandchildDir, 0755, true);
        $this->tempDirs[] = $childDir;
        $this->tempDirs[] = $grandchildDir;

        $originalCwd = getcwd();
        try {
            // Change to grandchild directory (no .env file in any parent)
            chdir($grandchildDir);

            // Reset dotenv loaded flag
            $this->resetDotenvLoaded();

            // Clear environment variables
            putenv('MARKETDATA_TOKEN');
            unset($_ENV['MARKETDATA_TOKEN']);
            unset($_SERVER['MARKETDATA_TOKEN']);

            // Try to get token - should search up to root and then break
            $token = Settings::getToken(null);
            
            // Should return empty string since no .env file was found
            $this->assertEquals('', $token);
        } finally {
            // Always restore directory
            if ($originalCwd && is_dir($originalCwd)) {
                @chdir($originalCwd);
            }
        }
    }

    /**
     * Test getEnvValue() $_SERVER fallback.
     * 
     * Covers line 330: return $_SERVER[$varName];
     *
     * @return void
     */
    public function testGetEnvValue_serverVarFallback()
    {
        // Save original value to restore later
        $testVar = 'MARKETDATA_OUTPUT_FORMAT';
        $originalGetenv = getenv($testVar);
        $originalEnv = $_ENV[$testVar] ?? null;
        $originalServer = $_SERVER[$testVar] ?? null;
        
        // Clear getenv() and $_ENV for a test variable
        putenv($testVar);
        unset($_ENV[$testVar]);
        
        // Set only $_SERVER
        $_SERVER[$testVar] = 'csv';

        // Reset dotenv loaded flag
        $this->resetDotenvLoaded();

        try {
            // getDefaultParameters() uses getEnvValue() internally
            $params = Settings::getDefaultParameters();
            
            // Should use $_SERVER value
            $this->assertEquals(Format::CSV, $params->format);
        } finally {
            // Restore original environment variable value
            if ($originalGetenv !== false) {
                putenv("$testVar=$originalGetenv");
            } else {
                putenv($testVar);
            }
            if ($originalEnv !== null) {
                $_ENV[$testVar] = $originalEnv;
            } else {
                unset($_ENV[$testVar]);
            }
            if ($originalServer !== null) {
                $_SERVER[$testVar] = $originalServer;
            } else {
                unset($_SERVER[$testVar]);
            }
        }
    }

    /**
     * Test getEnvValue() re-checking after .env file loads.
     * 
     * Covers lines 341 and 349: return $value; and return $_SERVER[$varName];
     *
     * @return void
     */
    public function testGetEnvValue_afterDotenvLoads()
    {
        // Save original value to restore later
        $testVar = 'MARKETDATA_OUTPUT_FORMAT';
        $originalGetenv = getenv($testVar);
        $originalEnv = $_ENV[$testVar] ?? null;
        $originalServer = $_SERVER[$testVar] ?? null;
        
        // Clear all environment variables
        putenv($testVar);
        unset($_ENV[$testVar]);
        unset($_SERVER[$testVar]);

        // Create temporary directory and .env file
        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, [$testVar => 'html']);
        
        $originalCwd = getcwd();
        try {
            chdir($tempDir);

            // Reset dotenv loaded flag to force reload
            $this->resetDotenvLoaded();

            // getDefaultParameters() will trigger .env loading via getEnvValue()
            $params = Settings::getDefaultParameters();
            
            // Should use value from .env file (which populates $_ENV or $_SERVER)
            $this->assertEquals(Format::HTML, $params->format);
        } finally {
            // Always restore directory
            if ($originalCwd && is_dir($originalCwd)) {
                @chdir($originalCwd);
            }
            
            // Restore original environment variable value
            if ($originalGetenv !== false) {
                putenv("$testVar=$originalGetenv");
            } else {
                putenv($testVar);
            }
            if ($originalEnv !== null) {
                $_ENV[$testVar] = $originalEnv;
            } else {
                unset($_ENV[$testVar]);
            }
            if ($originalServer !== null) {
                $_SERVER[$testVar] = $originalServer;
            } else {
                unset($_SERVER[$testVar]);
            }
        }
    }

    /**
     * Reset Settings::$dotenvLoaded static property.
     *
     * @return void
     */
    private function resetDotenvLoaded(): void
    {
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        // setAccessible() is not needed in PHP 8.1+ and deprecated in PHP 8.5+
        // Private properties are accessible by default via reflection
        $property->setValue(null, false);
    }

    /**
     * Create a temporary directory.
     *
     * @return string Path to temporary directory.
     */
    private function createTempDir(): string
    {
        $tempDir = sys_get_temp_dir() . '/marketdata_sdk_test_' . uniqid();
        mkdir($tempDir, 0755, true);
        $this->tempDirs[] = $tempDir;
        return $tempDir;
    }

    /**
     * Create a temporary .env file.
     *
     * @param string $dir Directory to create .env file in.
     * @param array $content Key-value pairs for .env file.
     *
     * @return string Path to .env file.
     */
    private function createTempEnvFile(string $dir, array $content): string
    {
        $envFile = $dir . '/.env';
        $lines = [];
        foreach ($content as $key => $value) {
            $lines[] = "$key=$value";
        }
        file_put_contents($envFile, implode("\n", $lines));
        $this->tempFiles[] = $envFile;
        return $envFile;
    }

    /**
     * Clean up temporary files and directories.
     *
     * @return void
     */
    private function cleanupTempFiles(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];

        // Remove temp directories (in reverse order, recursively)
        foreach (array_reverse($this->tempDirs) as $dir) {
            if (is_dir($dir)) {
                // Remove all files in directory first
                $files = array_diff(scandir($dir), ['.', '..']);
                foreach ($files as $file) {
                    $filePath = $dir . '/' . $file;
                    if (is_file($filePath)) {
                        @unlink($filePath);
                    } elseif (is_dir($filePath)) {
                        @rmdir($filePath);
                    }
                }
                @rmdir($dir);
            }
        }
        $this->tempDirs = [];
    }
}
