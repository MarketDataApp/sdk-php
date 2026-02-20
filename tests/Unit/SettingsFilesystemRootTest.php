<?php

namespace MarketDataApp\Tests\Unit;

use MarketDataApp\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Settings class filesystem edge cases.
 *
 * Tests specifically targeting the filesystem root detection path (line 149)
 * in the loadDotenv() method.
 */
class SettingsFilesystemRootTest extends TestCase
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
     * Original working directory.
     */
    private string $originalCwd;

    /**
     * Save original environment state before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
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
     * Restore original environment state after each test.
     */
    protected function tearDown(): void
    {
        // Restore working directory FIRST
        if (isset($this->originalCwd) && $this->originalCwd !== false && is_dir($this->originalCwd)) {
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

        // Clean up temporary directories
        $this->cleanupTempDirs();

        parent::tearDown();
    }

    /**
     * Test that loadDotenv() correctly handles reaching the filesystem root.
     *
     * This test covers line 149 (break statement) in Settings::loadDotenv().
     * The break occurs when dirname($dir) === $dir, which happens at the
     * filesystem root (e.g., "/" on Unix systems).
     *
     * @return void
     */
    public function testLoadDotenv_reachesFilesystemRootAndBreaks()
    {
        // Use /tmp directly (which resolves to /private/tmp on macOS)
        // This is only 3 levels from root, well within the maxLevels=5 limit
        $tempDir = '/tmp/sdk_fsroot_' . uniqid();
        
        if (!@mkdir($tempDir, 0755, true)) {
            $this->markTestSkipped('Unable to create temp directory at /tmp');
            return;
        }
        $this->tempDirs[] = $tempDir;

        // Verify no .env files exist in the path from temp dir to root
        $checkDir = realpath($tempDir);
        $levelsToRoot = 0;
        while ($checkDir !== dirname($checkDir) && $levelsToRoot < 10) {
            $envFile = $checkDir . '/.env';
            if (file_exists($envFile)) {
                $this->markTestSkipped(".env file found at $envFile - cannot test root path");
                return;
            }
            $checkDir = dirname($checkDir);
            $levelsToRoot++;
        }

        try {
            // Change to the temp directory
            $realTempDir = realpath($tempDir);
            $changed = @chdir($realTempDir);
            if (!$changed) {
                $this->markTestSkipped("Unable to change to temp directory: $realTempDir");
                return;
            }

            // Clear all environment variables
            putenv('MARKETDATA_TOKEN');
            unset($_ENV['MARKETDATA_TOKEN']);
            unset($_SERVER['MARKETDATA_TOKEN']);

            // Reset dotenv loaded flag to force a fresh search
            $this->resetDotenvLoaded();

            // Call getToken which will trigger loadDotenv()
            // Since there's no .env file in any parent directory up to root,
            // the while loop should traverse up until it hits the filesystem root
            // and then break at line 149.
            $token = Settings::getToken(null);

            // Should return empty string since no token source was found
            $this->assertEquals('', $token);

            // If we got here without error, the filesystem root path was exercised
        } finally {
            // Always restore directory
            if (isset($this->originalCwd) && $this->originalCwd && is_dir($this->originalCwd)) {
                @chdir($this->originalCwd);
            }
        }
    }

    /**
     * Test filesystem root detection with multiple directory levels.
     *
     * Creates a directory structure that requires traversing through
     * multiple parent directories before reaching the filesystem root.
     *
     * @return void
     */
    public function testLoadDotenv_traversesMultipleLevelsToRoot()
    {
        // Create a directory 3 levels deep within temp
        // This ensures we traverse through multiple levels before hitting root
        $tempDir = $this->createMultiLevelTempDir(3);

        if ($tempDir === null) {
            $this->markTestSkipped('Unable to create multi-level temp directory');
            return;
        }

        try {
            $changed = @chdir($tempDir);
            if (!$changed) {
                $this->markTestSkipped("Unable to change to temp directory: $tempDir");
                return;
            }

            // Clear all environment variables
            putenv('MARKETDATA_TOKEN');
            unset($_ENV['MARKETDATA_TOKEN']);
            unset($_SERVER['MARKETDATA_TOKEN']);

            // Reset dotenv loaded flag
            $this->resetDotenvLoaded();

            // Trigger loadDotenv() through getToken()
            $token = Settings::getToken(null);

            // Should return empty string
            $this->assertEquals('', $token);

        } finally {
            if (isset($this->originalCwd) && $this->originalCwd && is_dir($this->originalCwd)) {
                @chdir($this->originalCwd);
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
        $property->setValue(null, false);
    }

    /**
     * Create a temporary directory close to the filesystem root.
     *
     * Tries to create a directory in /tmp which is typically only
     * 1-2 levels from the root on most Unix systems.
     *
     * @return string|null Path to temporary directory, or null if creation failed.
     */
    private function createShallowTempDir(): ?string
    {
        // Try /tmp first (Unix-like systems)
        $tempRoot = '/tmp';
        if (!is_dir($tempRoot) || !is_writable($tempRoot)) {
            // Fallback to system temp directory
            $tempRoot = sys_get_temp_dir();
        }

        $tempDir = $tempRoot . '/sdk_root_test_' . uniqid();

        if (!@mkdir($tempDir, 0755, true)) {
            return null;
        }

        $this->tempDirs[] = $tempDir;
        return $tempDir;
    }

    /**
     * Create a temporary directory structure with multiple levels.
     *
     * @param int $levels Number of directory levels to create.
     *
     * @return string|null Path to deepest directory, or null if creation failed.
     */
    private function createMultiLevelTempDir(int $levels): ?string
    {
        $tempRoot = '/tmp';
        if (!is_dir($tempRoot) || !is_writable($tempRoot)) {
            $tempRoot = sys_get_temp_dir();
        }

        $path = $tempRoot . '/sdk_multi_' . uniqid();
        $this->tempDirs[] = $path; // Track the base for cleanup

        for ($i = 0; $i < $levels; $i++) {
            $path .= '/level' . $i;
        }

        if (!@mkdir($path, 0755, true)) {
            return null;
        }

        return $path;
    }

    /**
     * Clean up temporary directories.
     *
     * @return void
     */
    private function cleanupTempDirs(): void
    {
        foreach (array_reverse($this->tempDirs) as $dir) {
            if (is_dir($dir)) {
                $this->recursiveDelete($dir);
            }
        }
        $this->tempDirs = [];
    }

    /**
     * Recursively delete a directory and its contents.
     *
     * @param string $dir Directory path to delete.
     *
     * @return void
     */
    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
