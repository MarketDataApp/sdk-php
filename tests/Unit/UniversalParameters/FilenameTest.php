<?php

namespace MarketDataApp\Tests\Unit\UniversalParameters;

use InvalidArgumentException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\Format;

/**
 * Unit tests for the Filename universal parameter.
 *
 * Tests parameter merging, validation, and restrictions
 * for the filename parameter (file path for CSV/HTML output).
 * Note: filename is only valid for CSV/HTML formats and cannot be used with parallel requests.
 */
class FilenameTest extends UniversalParametersTestCase
{
    // ============================================================================
    // Parameter Merging Tests
    // ============================================================================

    public function testMergeParameters_filename_methodParamOverridesClientDefault(): void
    {
        $tempDir = $this->createTempDir();
        $defaultFile = $tempDir . '/default.csv';
        $testFile = $tempDir . '/test.csv';

        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->filename = $defaultFile;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV, filename: $testFile));
        $this->assertEquals($testFile, $merged->filename);
    }

    public function testMergeParameters_filename_nullMethodParamUsesClientDefault(): void
    {
        $tempDir = $this->createTempDir();
        $defaultFile = $tempDir . '/default.csv';

        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->filename = $defaultFile;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV));
        $this->assertEquals($defaultFile, $merged->filename);
    }

    // ============================================================================
    // Format Restriction Tests
    // ============================================================================

    public function testIntegration_filename_invalidWithJsonFormat(): void
    {
        $tempDir = $this->createTempDir();
        $filename = $tempDir . '/test.csv';

        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;
        $this->client->default_params->filename = $filename;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filename parameter can only be used with CSV or HTML format');

        $this->client->stocks->quote('AAPL', parameters: new Parameters(format: Format::JSON));
    }

    public function testIntegration_parallelRequests_filenameNotAllowed(): void
    {
        $tempDir = $this->createTempDir();
        $filename = $tempDir . '/test.csv';
        touch($filename);

        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;
        $this->client->default_params->filename = $filename;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filename parameter cannot be used with parallel requests');

        $this->client->stocks->quotes(['AAPL', 'MSFT'], parameters: null);
    }
}
