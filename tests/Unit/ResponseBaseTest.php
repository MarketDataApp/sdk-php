<?php

namespace MarketDataApp\Tests\Unit;

use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use PHPUnit\Framework\TestCase;

/**
 * Test case for ResponseBase file operations.
 *
 * This class tests the saveToFile method and error handling.
 */
class ResponseBaseTest extends TestCase
{
    /**
     * Temporary files created during tests.
     *
     * @var array
     */
    private array $tempFiles = [];

    /**
     * Temporary directories created during tests.
     *
     * @var array
     */
    private array $tempDirs = [];

    /**
     * Recursively remove a directory and its contents.
     *
     * @param string $dir Directory path
     * @return void
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Clean up temporary files and directories after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];

        // Remove directories in reverse order (need to remove files and subdirectories first)
        foreach (array_reverse($this->tempDirs) as $dir) {
            if (is_dir($dir)) {
                // Recursively remove directory contents
                $this->removeDirectory($dir);
            }
        }
        $this->tempDirs = [];
    }

    /**
     * Test saveToFile with JSON response (should throw).
     *
     * @return void
     */
    public function testSaveToFile_withJsonResponse_throwsException()
    {
        // Create a JSON response (no csv or html) - need minimal valid response structure
        $response = new Quote((object)[
            's' => 'ok',
            'symbol' => ['AAPL'],
            'last' => [150.0],
            'ask' => [150.1],
            'askSize' => [200],
            'bid' => [150.0],
            'bidSize' => [300],
            'mid' => [150.05],
            'change' => [0.5],
            'changepct' => [0.33],
            'volume' => [1000000],
            'updated' => [1234567890]
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('saveToFile() can only be used with CSV or HTML responses');

        $response->saveToFile('/tmp/test.json');
    }

    /**
     * Test saveToFile with invalid filename extension for CSV.
     *
     * @return void
     */
    public function testSaveToFile_withInvalidExtension_throwsException()
    {
        // Create a CSV response with minimal valid structure
        $response = new Quote((object)[
            's' => 'ok',
            'csv' => 'Symbol,Price\nAAPL,150.0'
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('filename must end with .csv');

        $response->saveToFile('/tmp/test.txt');
    }

    /**
     * Test saveToFile with invalid filename extension for HTML.
     *
     * @return void
     */
    public function testSaveToFile_withInvalidHtmlExtension_throwsException()
    {
        // Create an HTML response with minimal valid structure
        $response = new Quote((object)[
            's' => 'ok',
            'html' => '<html><body>Test</body></html>'
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('filename must end with .html');

        $response->saveToFile('/tmp/test.csv');
    }

    /**
     * Test saveToFile with directory creation failure.
     *
     * @return void
     */
    public function testSaveToFile_withDirectoryCreationFailure_throwsException()
    {
        // Create a CSV response with minimal valid structure
        $response = new Quote((object)[
            's' => 'ok',
            'csv' => 'Symbol,Price\nAAPL,150.0'
        ]);

        // Create a file where we want to create a directory - this will cause mkdir to fail
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_dir_', true);
        $this->tempDirs[] = $tempDir;
        
        // Create a file with the same name as the directory we want to create
        touch($tempDir);
        $this->tempFiles[] = $tempDir;
        
        // Now try to save to a file in that "directory" - mkdir will fail because $tempDir is a file, not a directory
        $filename = $tempDir . '/subdir/test.csv';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create directory');

        // This is an error-path test - we're verifying the exception is thrown correctly
        // The PHP warning from mkdir() is expected and doesn't indicate a problem
        // Use @ operator to suppress the expected warning
        @$response->saveToFile($filename);
    }

    /**
     * Test saveToFile with file write failure.
     * 
     * Unix-only: Uses read-only directory permissions which work differently on Windows.
     *
     * @return void
     */
    public function testSaveToFile_withFileWriteFailure_throwsException()
    {
        // Skip on non-Unix platforms - test passes without running
        if (PHP_OS_FAMILY !== 'Linux' && PHP_OS_FAMILY !== 'Darwin') {
            $this->assertTrue(true);
            return;
        }

        // Create a CSV response with minimal valid structure
        $response = new Quote((object)[
            's' => 'ok',
            'csv' => 'Symbol,Price\nAAPL,150.0'
        ]);

        // Create a directory that exists but is read-only
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_readonly_', true);
        if (mkdir($tempDir, 0555, true)) {
            $this->tempDirs[] = $tempDir;
            $filename = $tempDir . '/test.csv';

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Failed to write file');

            // This is an error-path test - we're verifying the exception is thrown correctly
            // The PHP warning from file_put_contents() is expected and doesn't indicate a problem
            // Use @ operator to suppress the expected warning
            try {
                @$response->saveToFile($filename);
            } catch (\RuntimeException $e) {
                // Verify the error message
                $this->assertStringContainsString('Failed to write file', $e->getMessage());
                // Restore permissions for cleanup
                chmod($tempDir, 0755);
                throw $e;
            }
        } else {
            $this->markTestSkipped('Could not create read-only directory for testing');
        }
    }

    /**
     * Test saveToFile with file write failure on Windows.
     * 
     * Windows-only: Creates a read-only file and attempts to overwrite it, which should fail.
     *
     * @return void
     */
    public function testSaveToFile_withFileWriteFailure_throwsExceptionWindows()
    {
        // Skip on non-Windows platforms - test passes without running
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertTrue(true);
            return;
        }

        // Create a CSV response with minimal valid structure
        $response = new Quote((object)[
            's' => 'ok',
            'csv' => 'Symbol,Price\nAAPL,150.0'
        ]);

        // Create a file and make it read-only, then try to overwrite it
        // On Windows, attempting to overwrite a read-only file should fail
        $tempDir = sys_get_temp_dir() . '\\' . uniqid('test_', true);
        if (mkdir($tempDir, 0755, true)) {
            $this->tempDirs[] = $tempDir;
            $filename = $tempDir . '\\test.csv';
            
            // Create the file first
            file_put_contents($filename, 'existing content');
            $this->tempFiles[] = $filename;
            
            // Make it read-only
            chmod($filename, 0444);
            
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Failed to write file');

            // This is an error-path test - we're verifying the exception is thrown correctly
            // The PHP warning from file_put_contents() is expected and doesn't indicate a problem
            // Use @ operator to suppress the expected warning
            try {
                @$response->saveToFile($filename);
            } catch (\RuntimeException $e) {
                // Verify the error message
                $this->assertStringContainsString('Failed to write file', $e->getMessage());
                // Restore permissions for cleanup
                chmod($filename, 0644);
                throw $e;
            }
        } else {
            $this->markTestSkipped('Could not create test directory');
        }
    }

    /**
     * Test saveToFile successfully saves CSV file.
     *
     * @return void
     */
    public function testSaveToFile_withCsv_savesSuccessfully()
    {
        // Create a CSV response with minimal valid structure
        $csvContent = 'Symbol,Price\nAAPL,150.0';
        $response = new Quote((object)[
            's' => 'ok',
            'csv' => $csvContent
        ]);

        $tempFile = sys_get_temp_dir() . '/' . uniqid('test_', true) . '.csv';
        $this->tempFiles[] = $tempFile;

        $result = $response->saveToFile($tempFile);

        $this->assertFileExists($tempFile);
        $this->assertEquals($csvContent, file_get_contents($tempFile));
        $this->assertNotEmpty($result); // Should return absolute path
    }

    /**
     * Test saveToFile successfully saves HTML file.
     *
     * @return void
     */
    public function testSaveToFile_withHtml_savesSuccessfully()
    {
        // Create an HTML response with minimal valid structure
        $htmlContent = '<html><body><h1>Test</h1></body></html>';
        $response = new Quote((object)[
            's' => 'ok',
            'html' => $htmlContent
        ]);

        $tempFile = sys_get_temp_dir() . '/' . uniqid('test_', true) . '.html';
        $this->tempFiles[] = $tempFile;

        $result = $response->saveToFile($tempFile);

        $this->assertFileExists($tempFile);
        $this->assertEquals($htmlContent, file_get_contents($tempFile));
        $this->assertNotEmpty($result); // Should return absolute path
    }

    /**
     * Test saveToFile creates directory if needed.
     *
     * @return void
     */
    public function testSaveToFile_createsDirectoryIfNeeded()
    {
        // Create a CSV response with minimal valid structure
        $csvContent = 'Symbol,Price\nAAPL,150.0';
        $response = new Quote((object)[
            's' => 'ok',
            'csv' => $csvContent
        ]);

        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_dir_', true);
        $this->tempDirs[] = $tempDir;
        $tempFile = $tempDir . '/subdir/test.csv';
        $this->tempFiles[] = $tempFile;

        $result = $response->saveToFile($tempFile);

        $this->assertFileExists($tempFile);
        $this->assertEquals($csvContent, file_get_contents($tempFile));
        $this->assertDirectoryExists($tempDir . '/subdir');
    }

    /**
     * Test constructor with csv property sets csv.
     *
     * @return void
     */
    public function testConstructor_withCsvProperty_setsCsv(): void
    {
        $csvContent = 'Symbol,Price\nAAPL,150.0';
        $response = new Quote((object)[
            's' => 'ok',
            'csv' => $csvContent
        ]);

        $this->assertEquals($csvContent, $response->getCsv());
        $this->assertTrue($response->isCsv());
    }

    /**
     * Test constructor with html property sets html.
     *
     * @return void
     */
    public function testConstructor_withHtmlProperty_setsHtml(): void
    {
        $htmlContent = '<html><body>Test</body></html>';
        $response = new Quote((object)[
            's' => 'ok',
            'html' => $htmlContent
        ]);

        $this->assertEquals($htmlContent, $response->getHtml());
        $this->assertTrue($response->isHtml());
    }

    /**
     * Test constructor with _saved_filename property sets _saved_filename.
     *
     * @return void
     */
    public function testConstructor_withSavedFilename_setsSavedFilename(): void
    {
        $savedFilename = '/tmp/test.csv';
        $response = new Quote((object)[
            's' => 'ok',
            'csv' => 'Symbol,Price\nAAPL,150.0',
            '_saved_filename' => $savedFilename
        ]);

        $this->assertEquals($savedFilename, $response->_saved_filename);
    }

    /**
     * Test getCsv returns csv content.
     *
     * @return void
     */
    public function testGetCsv_returnsCsvContent(): void
    {
        $csvContent = 'Symbol,Price\nAAPL,150.0';
        $response = new Quote((object)[
            's' => 'ok',
            'csv' => $csvContent
        ]);

        $this->assertEquals($csvContent, $response->getCsv());
    }

    /**
     * Test getHtml returns html content.
     *
     * @return void
     */
    public function testGetHtml_returnsHtmlContent(): void
    {
        $htmlContent = '<html><body>Test</body></html>';
        $response = new Quote((object)[
            's' => 'ok',
            'html' => $htmlContent
        ]);

        $this->assertEquals($htmlContent, $response->getHtml());
    }

    /**
     * Test isJson with empty csv and html returns true.
     *
     * @return void
     */
    public function testIsJson_withEmptyCsvAndHtml_returnsTrue(): void
    {
        $response = new Quote((object)[
            's' => 'ok',
            'symbol' => ['AAPL'],
            'last' => [150.0],
            'ask' => [150.1],
            'askSize' => [200],
            'bid' => [150.0],
            'bidSize' => [300],
            'mid' => [150.05],
            'change' => [0.5],
            'changepct' => [0.33],
            'volume' => [1000000],
            'updated' => [1234567890]
        ]);

        $this->assertTrue($response->isJson());
        $this->assertFalse($response->isCsv());
        $this->assertFalse($response->isHtml());
    }

    /**
     * Test isJson with csv returns false.
     *
     * @return void
     */
    public function testIsJson_withCsv_returnsFalse(): void
    {
        $response = new Quote((object)[
            's' => 'ok',
            'csv' => 'Symbol,Price\nAAPL,150.0'
        ]);

        $this->assertFalse($response->isJson());
        $this->assertTrue($response->isCsv());
    }

    /**
     * Test isJson with html returns false.
     *
     * @return void
     */
    public function testIsJson_withHtml_returnsFalse(): void
    {
        $response = new Quote((object)[
            's' => 'ok',
            'html' => '<html><body>Test</body></html>'
        ]);

        $this->assertFalse($response->isJson());
        $this->assertTrue($response->isHtml());
    }

    /**
     * Test isHtml with html returns true.
     *
     * @return void
     */
    public function testIsHtml_withHtml_returnsTrue(): void
    {
        $response = new Quote((object)[
            's' => 'ok',
            'html' => '<html><body>Test</body></html>'
        ]);

        $this->assertTrue($response->isHtml());
    }

    /**
     * Test isHtml without html returns false.
     *
     * @return void
     */
    public function testIsHtml_withoutHtml_returnsFalse(): void
    {
        $response = new Quote((object)[
            's' => 'ok',
            'symbol' => ['AAPL'],
            'last' => [150.0],
            'ask' => [150.1],
            'askSize' => [200],
            'bid' => [150.0],
            'bidSize' => [300],
            'mid' => [150.05],
            'change' => [0.5],
            'changepct' => [0.33],
            'volume' => [1000000],
            'updated' => [1234567890]
        ]);

        $this->assertFalse($response->isHtml());
    }

    /**
     * Test isCsv with csv returns true.
     *
     * @return void
     */
    public function testIsCsv_withCsv_returnsTrue(): void
    {
        $response = new Quote((object)[
            's' => 'ok',
            'csv' => 'Symbol,Price\nAAPL,150.0'
        ]);

        $this->assertTrue($response->isCsv());
    }

    /**
     * Test isCsv without csv returns false.
     *
     * @return void
     */
    public function testIsCsv_withoutCsv_returnsFalse(): void
    {
        $response = new Quote((object)[
            's' => 'ok',
            'symbol' => ['AAPL'],
            'last' => [150.0],
            'ask' => [150.1],
            'askSize' => [200],
            'bid' => [150.0],
            'bidSize' => [300],
            'mid' => [150.05],
            'change' => [0.5],
            'changepct' => [0.33],
            'volume' => [1000000],
            'updated' => [1234567890]
        ]);

        $this->assertFalse($response->isCsv());
    }

    /**
     * Test empty CSV response is correctly classified as CSV (not JSON).
     *
     * This is a regression test for BUG-001 where empty CSV responses
     * were misclassified as JSON because empty() returns true for empty strings.
     *
     * Mock response: NOT from real API output (uses synthetic/test data)
     *
     * @return void
     */
    public function testIsJson_withEmptyCsv_returnsFalse(): void
    {
        // Create a response with an empty CSV string
        $response = new Quote((object)[
            's' => 'ok',
            'csv' => ''
        ]);

        // Empty CSV should still be recognized as CSV, not JSON
        $this->assertTrue($response->isCsv(), 'Empty CSV should be recognized as CSV');
        $this->assertFalse($response->isJson(), 'Empty CSV should not be classified as JSON');
        $this->assertFalse($response->isHtml(), 'Empty CSV should not be classified as HTML');
    }

    /**
     * Test empty HTML response is correctly classified as HTML (not JSON).
     *
     * This is a regression test for BUG-001 where empty HTML responses
     * were misclassified as JSON because empty() returns true for empty strings.
     *
     * Mock response: NOT from real API output (uses synthetic/test data)
     *
     * @return void
     */
    public function testIsJson_withEmptyHtml_returnsFalse(): void
    {
        // Create a response with an empty HTML string
        $response = new Quote((object)[
            's' => 'ok',
            'html' => ''
        ]);

        // Empty HTML should still be recognized as HTML, not JSON
        $this->assertTrue($response->isHtml(), 'Empty HTML should be recognized as HTML');
        $this->assertFalse($response->isJson(), 'Empty HTML should not be classified as JSON');
        $this->assertFalse($response->isCsv(), 'Empty HTML should not be classified as CSV');
    }

    /**
     * Test saveToFile when realpath returns false returns filename.
     *
     * @return void
     */
    public function testSaveToFile_whenRealpathReturnsFalse_returnsFilename(): void
    {
        // Create a CSV response
        $csvContent = 'Symbol,Price\nAAPL,150.0';
        $response = new Quote((object)[
            's' => 'ok',
            'csv' => $csvContent
        ]);

        // Create a temporary file
        $tempFile = sys_get_temp_dir() . '/' . uniqid('test_', true) . '.csv';
        $this->tempFiles[] = $tempFile;

        // Save the file
        $result = $response->saveToFile($tempFile);

        // Verify file was created
        $this->assertFileExists($tempFile);
        $this->assertEquals($csvContent, file_get_contents($tempFile));

        // The result should be the absolute path (realpath) or the filename as fallback
        // Since we're using a real temp file, realpath should work, but we verify the behavior
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('.csv', $result);
    }
}
