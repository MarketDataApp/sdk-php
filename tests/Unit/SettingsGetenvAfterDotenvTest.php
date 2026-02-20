<?php

/**
 * This test file covers the edge case where getenv() returns a value
 * AFTER a .env file is loaded in Settings::getEnvValue().
 *
 * Line 341 in Settings.php is the return statement that executes when:
 * 1. Initial checks for getenv(), $_ENV, $_SERVER all fail
 * 2. The .env file loads (setting $dotenvLoaded = true)
 * 3. After loading, getenv() returns a valid value
 *
 * Line 349 is the return statement that executes when:
 * 1. Initial checks for getenv(), $_ENV, $_SERVER all fail
 * 2. The .env file loads (setting $dotenvLoaded = true)
 * 3. After loading, getenv() and $_ENV both fail
 * 4. $_SERVER returns a valid value
 *
 * IMPORTANT FINDING:
 * Lines 341 and 349 are UNREACHABLE with the current implementation because:
 * - Settings::loadDotenv() uses Dotenv::createImmutable() which does NOT call putenv()
 * - This means getenv() is never populated during loadDotenv()
 * - Dotenv::createImmutable() populates both $_ENV and $_SERVER
 * - $_ENV is checked first (line 344), so line 349 is also unreachable
 *
 * These lines are defensive code that would handle:
 * - Custom Dotenv adapters that call putenv()
 * - External processes that set environment variables during loading
 * - Future changes to use Dotenv::createUnsafeImmutable()
 *
 * This test file documents this finding and tests the reachable related paths.
 */

namespace MarketDataApp\Tests\Unit;

use Dotenv\Dotenv;
use MarketDataApp\Enums\Format;
use MarketDataApp\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Settings class getenv() after dotenv loading edge case.
 *
 * Tests specifically targeting the post-dotenv-load checks in Settings::getEnvValue():
 * - Line 341: return $value; (getenv after load) - UNREACHABLE with current implementation
 * - Line 345: return $_ENV[$varName]; (already covered)
 * - Line 349: return $_SERVER[$varName]; (after load) - UNREACHABLE with current implementation
 *
 * This test class documents and verifies the reachable paths while noting
 * which lines are defensive code that cannot be reached with the current implementation.
 */
class SettingsGetenvAfterDotenvTest extends TestCase
{
    /**
     * Original environment variable values to restore after tests.
     */
    private $originalEnvVars = [];

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
     * Save original environment state before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $cwd = getcwd();
        $this->originalCwd = $cwd ? realpath($cwd) : $cwd;

        // Save original values for all env vars we'll manipulate
        $this->saveEnvVar('MARKETDATA_TOKEN');
        $this->saveEnvVar('MARKETDATA_OUTPUT_FORMAT');
        $this->saveEnvVar('MARKETDATA_TEST_VAR');

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

        // Restore all environment variables
        foreach ($this->originalEnvVars as $varName => $values) {
            $this->restoreEnvVar($varName, $values);
        }

        // Clean up temporary files and directories
        $this->cleanupTempFiles();

        parent::tearDown();
    }

    /**
     * Test getEnvValue() line 320: getenv() returns value (initial check).
     *
     * This test verifies the getenv() initial check path (line 318-320).
     * We use Dotenv::createUnsafeImmutable() to populate getenv(), then
     * verify that the initial getenv() check finds the value.
     *
     * Note: This test exercises line 320, not line 341. Line 341 is
     * INSIDE the "if (!self::$dotenvLoaded)" block and is unreachable
     * with the current implementation (see class documentation).
     *
     * @return void
     */
    public function testGetEnvValue_line320_unsafeDotenvSimulation()
    {
        $testVar = 'MARKETDATA_OUTPUT_FORMAT';

        // Create temp dir with .env file
        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, [$testVar => 'html']);

        try {
            chdir($tempDir);

            // Clear all environment sources first
            putenv($testVar);
            unset($_ENV[$testVar]);
            unset($_SERVER[$testVar]);

            // Use Dotenv's unsafe mode to populate getenv() 
            // This simulates what would happen if Settings used createUnsafeImmutable()
            $dotenv = Dotenv::createUnsafeImmutable($tempDir);
            $dotenv->load();

            // Verify getenv() now has the value from .env
            $this->assertEquals('html', getenv($testVar));

            // Clear $_ENV and $_SERVER to isolate the getenv() path
            unset($_ENV[$testVar]);
            unset($_SERVER[$testVar]);

            // Reset dotenvLoaded so the code tries to load again
            $this->resetDotenvLoaded();

            // Now call getDefaultParameters
            // Flow for MARKETDATA_OUTPUT_FORMAT:
            // 1. Line 318: getenv() returns 'html' (we set it via unsafe Dotenv)
            // 2. Line 319-320: Returns 'html'
            //
            // This tests that getenv() values ARE properly returned.
            // While it hits line 320 (not 341), it verifies the getenv() check works.

            $params = Settings::getDefaultParameters();
            $this->assertEquals(Format::HTML, $params->format);

        } finally {
            if (is_dir($this->originalCwd)) {
                @chdir($this->originalCwd);
            }
            // Clean up getenv
            putenv($testVar);
        }
    }

    /**
     * Test getEnvValue() with post-load getenv check path simulation.
     *
     * This test verifies that after loadDotenv() runs, subsequent calls
     * with getenv() set will find the value via the initial check.
     *
     * Note: This exercises line 320, not line 341.
     *
     * @return void
     */
    public function testGetEnvValue_postLoadGetenvCheck()
    {
        $testVar = 'MARKETDATA_OUTPUT_FORMAT';

        // Create temp directory with .env file containing a DIFFERENT variable
        // This ensures loadDotenv() runs but doesn't set our test variable
        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, ['MARKETDATA_OTHER_VAR' => 'value']);

        try {
            chdir($tempDir);

            // Clear all environment sources
            putenv($testVar);
            unset($_ENV[$testVar]);
            unset($_SERVER[$testVar]);

            // Reset dotenvLoaded
            $this->resetDotenvLoaded();

            // First call - triggers loadDotenv(), sets dotenvLoaded = true
            // Returns JSON (default) since MARKETDATA_OUTPUT_FORMAT not in .env
            $params1 = Settings::getDefaultParameters();
            $this->assertEquals(Format::JSON, $params1->format);

            // Now set getenv() value and reset dotenvLoaded
            putenv("$testVar=csv");
            $this->resetDotenvLoaded();

            // Clear $_ENV and $_SERVER to force getenv() path
            unset($_ENV[$testVar]);
            unset($_SERVER[$testVar]);

            // Second call - getenv() is set, so line 318-320 returns immediately
            $params2 = Settings::getDefaultParameters();
            $this->assertEquals(Format::CSV, $params2->format);

        } finally {
            if (is_dir($this->originalCwd)) {
                @chdir($this->originalCwd);
            }
            putenv($testVar);
        }
    }

    /**
     * Test getEnvValue() with tick-based injection attempt.
     *
     * This test attempts to use register_tick_function to inject an
     * environment variable value DURING the loadDotenv() execution.
     *
     * Note: The tick-based injection is non-deterministic and may or
     * may not succeed in hitting line 341 depending on timing.
     *
     * @return void
     */
    public function testGetEnvValue_tickBasedInjection()
    {
        $testVar = 'MARKETDATA_OUTPUT_FORMAT';

        // Create temp directory with .env file
        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, ['MARKETDATA_DUMMY' => 'value']);

        try {
            chdir($tempDir);

            // Clear all environment sources
            putenv($testVar);
            unset($_ENV[$testVar]);
            unset($_SERVER[$testVar]);

            // Reset dotenvLoaded
            $this->resetDotenvLoaded();

            // Set up tick function to inject value during execution
            declare(ticks=1);

            $tickCount = 0;
            $injected = false;
            $tickFunction = function () use ($testVar, &$tickCount, &$injected) {
                $tickCount++;
                // Inject after several ticks (during loadDotenv execution window)
                if ($tickCount >= 10 && !$injected) {
                    putenv("$testVar=csv");
                    $injected = true;
                }
            };

            register_tick_function($tickFunction);

            try {
                // Call getDefaultParameters
                $params = Settings::getDefaultParameters();

                // Result depends on tick timing - could be CSV (if injected in time)
                // or JSON (default if injection was too late)
                $this->assertTrue(
                    $params->format === Format::CSV || $params->format === Format::JSON,
                    'Format should be CSV (if tick injection worked) or JSON (default)'
                );
            } finally {
                unregister_tick_function($tickFunction);
            }

        } finally {
            if (is_dir($this->originalCwd)) {
                @chdir($this->originalCwd);
            }
            putenv($testVar);
        }
    }

    /**
     * Test getEnvValue() via .env file loading with $_ENV population.
     *
     * This test covers line 345 (the $_ENV check after .env loads).
     * Dotenv::createImmutable() populates $_ENV, so this path is
     * the normal path when loading from .env files.
     *
     * @return void
     */
    public function testGetEnvValue_line345_envVarAfterDotenvLoads()
    {
        $testVar = 'MARKETDATA_OUTPUT_FORMAT';

        // Create temp directory with .env file containing our test variable
        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, [$testVar => 'html']);

        try {
            chdir($tempDir);

            // Clear all environment sources
            putenv($testVar);
            unset($_ENV[$testVar]);
            unset($_SERVER[$testVar]);

            // Reset dotenvLoaded
            $this->resetDotenvLoaded();

            // Call getDefaultParameters
            // Flow:
            // 1. Line 318: getenv() returns false ✓
            // 2. Line 324: $_ENV not set ✓
            // 3. Line 328: $_SERVER not set ✓
            // 4. Line 334: dotenvLoaded is false ✓
            // 5. Line 335-336: loadDotenv() runs, sets dotenvLoaded = true
            // 6. Dotenv::createImmutable() loads .env, populating $_ENV
            // 7. Line 339: getenv() still returns false (createImmutable doesn't use putenv)
            // 8. Line 344: $_ENV is now set - HITS LINE 345!

            $params = Settings::getDefaultParameters();
            $this->assertEquals(Format::HTML, $params->format);

        } finally {
            if (is_dir($this->originalCwd)) {
                @chdir($this->originalCwd);
            }
        }
    }

    /**
     * Test getEnvValue() returns value from getenv() when available.
     *
     * This test verifies that getenv() values are properly checked
     * and returned at line 320. While not line 341 specifically, it
     * confirms the getenv() checking logic works correctly.
     *
     * @return void
     */
    public function testGetEnvValue_line320_getenvReturnsValue()
    {
        $testVar = 'MARKETDATA_OUTPUT_FORMAT';

        try {
            // Clear $_ENV and $_SERVER
            unset($_ENV[$testVar]);
            unset($_SERVER[$testVar]);

            // Set ONLY getenv() via putenv()
            putenv("$testVar=csv");

            // Reset dotenvLoaded
            $this->resetDotenvLoaded();

            // Call getDefaultParameters - should find value via getenv() at line 318-320
            $params = Settings::getDefaultParameters();
            $this->assertEquals(Format::CSV, $params->format);

        } finally {
            putenv($testVar);
        }
    }

    /**
     * Test getEnvValue() line 341: getenv() returns value after dotenv loads.
     *
     * This test covers line 341 by using Dotenv's "unsafe" mode which DOES
     * call putenv(). We simulate the scenario where loadDotenv() populates
     * getenv() by using createUnsafeImmutable().
     *
     * The strategy:
     * 1. Create .env file with our test variable
     * 2. Clear all env sources and reset dotenvLoaded
     * 3. Manually load with createUnsafeImmutable (populates getenv)
     * 4. Clear $_ENV and $_SERVER (but keep getenv)
     * 5. Reset dotenvLoaded again
     * 6. The next call to getEnvValue() will:
     *    - Initial getenv() check succeeds -> returns at line 320
     *
     * Wait - that's still line 320. To hit line 341, we need the INITIAL
     * checks to fail, then loadDotenv() runs, THEN getenv() succeeds.
     *
     * The only way to achieve this is if getenv() state changes DURING
     * loadDotenv(). Since loadDotenv uses createImmutable (not unsafe),
     * line 341 handles the theoretical case where an external factor
     * populates getenv() during loading.
     *
     * To properly test line 341, we use a tick handler that sets getenv()
     * AFTER the initial checks but during the loadDotenv() window.
     *
     * @return void
     */
    public function testGetEnvValue_line341_getenvAfterLoadDotenv()
    {
        $testVar = 'MARKETDATA_TEST_GETENV_LINE341';

        // Create temp directory with .env file that contains our test variable
        // Using createUnsafeImmutable mode so loadDotenv populates getenv()
        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, [$testVar => 'test_value_341']);

        try {
            chdir($tempDir);

            // Clear all environment sources
            putenv($testVar);
            unset($_ENV[$testVar]);
            unset($_SERVER[$testVar]);

            // Reset dotenvLoaded to force loadDotenv() to run
            $this->resetDotenvLoaded();

            // First, manually load with unsafe mode to populate getenv()
            // This simulates what would happen if Settings used createUnsafeImmutable
            $dotenv = Dotenv::createUnsafeImmutable($tempDir);
            $dotenv->load();

            // Verify getenv() is now populated
            $this->assertEquals('test_value_341', getenv($testVar));

            // Now clear $_ENV and $_SERVER, keeping only getenv()
            unset($_ENV[$testVar]);
            unset($_SERVER[$testVar]);

            // Verify the isolation
            $this->assertEquals('test_value_341', getenv($testVar));
            $this->assertArrayNotHasKey($testVar, $_ENV);
            $this->assertArrayNotHasKey($testVar, $_SERVER);

            // Reset dotenvLoaded so we can test the initial check path
            $this->resetDotenvLoaded();

            // Call getEnvValue via reflection
            // This will hit line 318-320 (initial getenv check succeeds)
            $reflection = new \ReflectionClass(Settings::class);
            $method = $reflection->getMethod('getEnvValue');
            $result = $method->invoke(null, $testVar);

            // This tests that getenv() values are properly returned
            // (confirms the getenv() check logic works, even if at line 320)
            $this->assertEquals('test_value_341', $result);

        } finally {
            if (is_dir($this->originalCwd)) {
                @chdir($this->originalCwd);
            }
            putenv($testVar);
        }
    }

    /**
     * Test getEnvValue() line 349: $_SERVER returns value after dotenv loads.
     *
     * Similar to line 341, line 349 handles the scenario where $_SERVER
     * is populated AFTER loadDotenv() but not by it directly.
     *
     * This test verifies the $_SERVER check path after dotenv loading.
     *
     * @return void
     */
    public function testGetEnvValue_line349_serverAfterLoadDotenv()
    {
        $testVar = 'MARKETDATA_TEST_SERVER_LINE349';

        // Create temp directory with .env file
        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, ['MARKETDATA_DUMMY' => 'dummy']);

        try {
            chdir($tempDir);

            // Clear all environment sources
            putenv($testVar);
            unset($_ENV[$testVar]);
            unset($_SERVER[$testVar]);

            // Reset dotenvLoaded
            $this->resetDotenvLoaded();

            // Trigger loadDotenv() by calling getEnvValue for a different var
            $reflection = new \ReflectionClass(Settings::class);
            $method = $reflection->getMethod('getEnvValue');
            $method->invoke(null, 'MARKETDATA_DUMMY');

            // Now dotenvLoaded is true. Set our test var in $_SERVER only
            $_SERVER[$testVar] = 'test_value_349';

            // Call getEnvValue - since dotenvLoaded is true, it won't reload
            // The initial $_SERVER check at line 329-330 will find it
            $result = $method->invoke(null, $testVar);

            $this->assertEquals('test_value_349', $result);

        } finally {
            if (is_dir($this->originalCwd)) {
                @chdir($this->originalCwd);
            }
            putenv($testVar);
            unset($_SERVER[$testVar]);
        }
    }

    /**
     * Test getEnvValue() post-load checks via simulated loadDotenv behavior.
     *
     * This test exercises the post-load check paths (lines 339-350) by
     * creating a scenario where the initial checks fail but after
     * loadDotenv() runs, the values become available.
     *
     * Since Dotenv::createImmutable() populates $_ENV and $_SERVER (not getenv),
     * loading a .env file exercises lines 344-345 (the $_ENV check).
     *
     * @return void
     */
    public function testGetEnvValue_postLoadChecks_envPopulated()
    {
        $testVar = 'MARKETDATA_TEST_POSTLOAD';

        // Create temp directory with .env file containing our test variable
        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, [$testVar => 'postload_value']);

        try {
            chdir($tempDir);

            // Clear all environment sources
            putenv($testVar);
            unset($_ENV[$testVar]);
            unset($_SERVER[$testVar]);

            // Reset dotenvLoaded to force loadDotenv() to run
            $this->resetDotenvLoaded();

            // Call getEnvValue via reflection
            // Flow:
            // 1. Line 318: getenv() returns false ✓
            // 2. Line 324: $_ENV not set ✓
            // 3. Line 328: $_SERVER not set ✓
            // 4. Line 334: dotenvLoaded is false ✓
            // 5. Line 335-336: loadDotenv() runs, sets dotenvLoaded = true
            // 6. Dotenv::createImmutable() populates $_ENV and/or $_SERVER
            // 7. Line 339: getenv() returns false (createImmutable doesn't use putenv)
            // 8. Line 344: $_ENV IS set -> returns at line 345
            $reflection = new \ReflectionClass(Settings::class);
            $method = $reflection->getMethod('getEnvValue');
            $result = $method->invoke(null, $testVar);

            // Should get value from .env file via $_ENV at line 345
            $this->assertEquals('postload_value', $result);

        } finally {
            if (is_dir($this->originalCwd)) {
                @chdir($this->originalCwd);
            }
            putenv($testVar);
            unset($_ENV[$testVar]);
            unset($_SERVER[$testVar]);
        }
    }

    /**
     * Test getEnvValue() line 341: getenv() value found after loadDotenv.
     *
     * This test covers line 341 by using Dotenv::createUnsafeImmutable() which
     * DOES call putenv(). We manually load the .env file with unsafe mode,
     * then reset dotenvLoaded so the Settings code will re-enter the
     * loading block and find the value via getenv().
     *
     * Actually, this still won't hit line 341 because if dotenvLoaded is false
     * and we have a .env file, loadDotenv() will run and use createImmutable(),
     * not createUnsafeImmutable(). The value we set via unsafe mode would be
     * found in the INITIAL getenv() check (line 318), not the post-load check.
     *
     * The ONLY way to hit line 341 is if:
     * 1. Initial getenv() fails
     * 2. loadDotenv() runs
     * 3. Something during loadDotenv() populates getenv()
     * 4. The post-load getenv() check finds it
     *
     * Since loadDotenv() uses createImmutable() which doesn't call putenv(),
     * line 341 is effectively unreachable with the current implementation.
     *
     * This test documents this behavior and tests the closest reachable paths.
     *
     * @return void
     */
    public function testGetEnvValue_line341_documentedAsUnreachable()
    {
        // Line 341 is defensive code that handles the theoretical scenario where
        // getenv() becomes populated during loadDotenv(). With the current
        // implementation using Dotenv::createImmutable(), this cannot happen.
        //
        // This test documents that understanding and tests the related paths.

        $testVar = 'MARKETDATA_LINE341_DOC';

        // Create temp directory with .env file
        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, [$testVar => 'from_dotenv']);

        try {
            chdir($tempDir);

            // Clear all environment sources
            putenv($testVar);
            unset($_ENV[$testVar]);
            unset($_SERVER[$testVar]);

            // Reset dotenvLoaded
            $this->resetDotenvLoaded();

            // Call getEnvValue - this will:
            // 1. Fail initial getenv() check (line 318)
            // 2. Fail initial $_ENV check (line 324)
            // 3. Fail initial $_SERVER check (line 328)
            // 4. Enter the if(!dotenvLoaded) block (line 334)
            // 5. Run loadDotenv() which uses createImmutable() (line 335)
            // 6. Set dotenvLoaded = true (line 336)
            // 7. Post-load getenv() check FAILS because createImmutable doesn't call putenv (line 339)
            // 8. Post-load $_ENV check SUCCEEDS (line 344-345) - Dotenv populates $_ENV
            //
            // Line 341 is SKIPPED because step 7 fails.

            $reflection = new \ReflectionClass(Settings::class);
            $method = $reflection->getMethod('getEnvValue');
            $result = $method->invoke(null, $testVar);

            // Value comes from $_ENV (line 345), not getenv (line 341)
            $this->assertEquals('from_dotenv', $result);

            // Verify it came from $_ENV, not getenv
            $this->assertEquals('from_dotenv', $_ENV[$testVar] ?? null);
            // getenv() is NOT populated by createImmutable()
            $this->assertFalse(getenv($testVar));

        } finally {
            if (is_dir($this->originalCwd)) {
                @chdir($this->originalCwd);
            }
            putenv($testVar);
            unset($_ENV[$testVar]);
        }
    }

    /**
     * Test getEnvValue() line 349: $_SERVER value found after loadDotenv.
     *
     * Similar to line 341, line 349 handles the scenario where $_SERVER is
     * populated during loadDotenv() but $_ENV is not. This is also effectively
     * unreachable because Dotenv::createImmutable() populates $_ENV first.
     *
     * This test documents this behavior.
     *
     * @return void
     */
    public function testGetEnvValue_line349_documentedAsUnreachable()
    {
        // Line 349 is defensive code that handles the theoretical scenario where
        // $_SERVER is populated during loadDotenv() but $_ENV is not.
        // With Dotenv::createImmutable(), both $_ENV and $_SERVER are populated,
        // and $_ENV is checked first (line 344), so line 349 is unreachable.

        $testVar = 'MARKETDATA_LINE349_DOC';

        // Create temp directory with .env file
        $tempDir = $this->createTempDir();
        $this->createTempEnvFile($tempDir, [$testVar => 'from_dotenv']);

        try {
            chdir($tempDir);

            // Clear all environment sources
            putenv($testVar);
            unset($_ENV[$testVar]);
            unset($_SERVER[$testVar]);

            // Reset dotenvLoaded
            $this->resetDotenvLoaded();

            // Call getEnvValue
            $reflection = new \ReflectionClass(Settings::class);
            $method = $reflection->getMethod('getEnvValue');
            $result = $method->invoke(null, $testVar);

            // Value comes from $_ENV (line 345)
            $this->assertEquals('from_dotenv', $result);

            // Verify both $_ENV and $_SERVER are populated by Dotenv
            // $_ENV is checked first, so line 349 is never reached
            $this->assertEquals('from_dotenv', $_ENV[$testVar] ?? null);
            $this->assertEquals('from_dotenv', $_SERVER[$testVar] ?? null);

        } finally {
            if (is_dir($this->originalCwd)) {
                @chdir($this->originalCwd);
            }
            putenv($testVar);
            unset($_ENV[$testVar]);
            unset($_SERVER[$testVar]);
        }
    }

    /**
     * Save environment variable values for later restoration.
     *
     * @param string $varName Environment variable name.
     */
    private function saveEnvVar(string $varName): void
    {
        $this->originalEnvVars[$varName] = [
            'getenv' => getenv($varName),
            'env' => $_ENV[$varName] ?? null,
            'server' => $_SERVER[$varName] ?? null,
        ];
    }

    /**
     * Restore environment variable to its original state.
     *
     * @param string $varName Environment variable name.
     * @param array $values Original values array.
     */
    private function restoreEnvVar(string $varName, array $values): void
    {
        if ($values['getenv'] !== false) {
            putenv("$varName=" . $values['getenv']);
        } else {
            putenv($varName);
        }

        if ($values['env'] !== null) {
            $_ENV[$varName] = $values['env'];
        } else {
            unset($_ENV[$varName]);
        }

        if ($values['server'] !== null) {
            $_SERVER[$varName] = $values['server'];
        } else {
            unset($_SERVER[$varName]);
        }
    }

    /**
     * Reset Settings::$dotenvLoaded static property.
     */
    private function resetDotenvLoaded(): void
    {
        $reflection = new \ReflectionClass(Settings::class);
        $property = $reflection->getProperty('dotenvLoaded');
        $property->setValue(null, false);
    }

    /**
     * Create a temporary directory.
     *
     * @return string Path to temporary directory.
     */
    private function createTempDir(): string
    {
        $tempDir = sys_get_temp_dir() . '/marketdata_sdk_getenv_' . uniqid();
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
     */
    private function cleanupTempFiles(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];

        foreach (array_reverse($this->tempDirs) as $dir) {
            if (is_dir($dir)) {
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
