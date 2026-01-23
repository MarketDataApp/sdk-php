<?php

namespace MarketDataApp\Tests\Unit\UniversalParameters;

use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;
use MarketDataApp\Settings;

/**
 * Unit tests for the DateFormat universal parameter.
 *
 * Tests parameter merging, environment variable support, and validation
 * for the date_format parameter (UNIX, TIMESTAMP, SPREADSHEET).
 * Note: date_format is only valid for CSV/HTML formats.
 */
class DateFormatTest extends UniversalParametersTestCase
{
    // ============================================================================
    // Parameter Merging Tests
    // ============================================================================

    public function testMergeParameters_dateFormat_methodParamOverridesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->date_format = DateFormat::UNIX;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP));
        $this->assertEquals(DateFormat::TIMESTAMP, $merged->date_format);
    }

    public function testMergeParameters_dateFormat_nullMethodParamUsesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->date_format = DateFormat::SPREADSHEET;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV));
        $this->assertEquals(DateFormat::SPREADSHEET, $merged->date_format);
    }

    public function testMergeParameters_dateFormat_formatChangeResetsDateFormat(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->date_format = DateFormat::UNIX;
        $stocks = $client->stocks;

        // When format changes to JSON, date_format should cause an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('date_format parameter can only be used with CSV or HTML format');

        $this->callMergeParameters($stocks, new Parameters(format: Format::JSON));
    }

    // ============================================================================
    // Environment Variable Tests
    // ============================================================================

    public function testGetDefaultParameters_dateFormat_fromEnvVar_timestamp(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_DATE_FORMAT=timestamp');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_DATE_FORMAT'] = 'timestamp';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(DateFormat::TIMESTAMP, $params->date_format);
    }

    public function testGetDefaultParameters_dateFormat_fromEnvVar_unix(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_DATE_FORMAT=unix');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_DATE_FORMAT'] = 'unix';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(DateFormat::UNIX, $params->date_format);
    }

    public function testGetDefaultParameters_dateFormat_fromEnvVar_spreadsheet(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_DATE_FORMAT=spreadsheet');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_DATE_FORMAT'] = 'spreadsheet';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(DateFormat::SPREADSHEET, $params->date_format);
    }

    public function testGetDefaultParameters_dateFormat_invalidValue_returnsNull(): void
    {
        putenv('MARKETDATA_DATE_FORMAT=invalid');
        $_ENV['MARKETDATA_DATE_FORMAT'] = 'invalid';
        $params = Settings::getDefaultParameters();
        $this->assertNull($params->date_format);
    }

    public function testGetDefaultParameters_dateFormat_notSet_returnsNull(): void
    {
        putenv('MARKETDATA_DATE_FORMAT');
        unset($_ENV['MARKETDATA_DATE_FORMAT']);
        $params = Settings::getDefaultParameters();
        $this->assertNull($params->date_format);
    }

    // ============================================================================
    // Format Restriction Tests
    // ============================================================================

    public function testIntegration_csvOnlyParams_workWithMergedFormat(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;
        $this->client->default_params->date_format = DateFormat::UNIX;

        $this->setMockResponses([
            new Response(200, [], 'symbol,ask\nAAPL,150.0')
        ]);

        $response = $this->client->stocks->quote('AAPL', parameters: null);
        $this->assertIsObject($response);
    }

    public function testIntegration_csvOnlyParams_invalidWithJsonFormat(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::JSON;
        $this->client->default_params->date_format = DateFormat::UNIX;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('date_format parameter can only be used with CSV or HTML format');

        $this->client->stocks->quote('AAPL', parameters: null);
    }

    public function testIntegration_multiSymbol_withDateFormat_csvFormat(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;
        $this->client->default_params->date_format = DateFormat::UNIX;

        // Mock CSV response for multi-symbol request (single API call returns all data)
        $csvContent = "symbol,ask,updated\nAAPL,150.0,1705747800\nMSFT,300.0,1705747800";
        $this->setMockResponses([
            new Response(200, [], $csvContent)
        ]);

        $response = $this->client->stocks->quotes(['AAPL', 'MSFT'], parameters: new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP));

        $this->assertIsObject($response);
        $this->assertIsArray($response->quotes);
        // CSV format returns a single Quote object containing all data
        $this->assertCount(1, $response->quotes);
        $this->assertTrue($response->quotes[0]->isCsv());
    }

    public function testIntegration_multiSymbol_withDateFormat_htmlFormat(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::HTML;
        $this->client->default_params->date_format = DateFormat::UNIX;

        // Mock HTML response for multi-symbol request (single API call returns all data)
        $htmlContent = "<table><tr><th>symbol</th><th>ask</th><th>updated</th></tr><tr><td>AAPL</td><td>150.0</td><td>1705747800</td></tr><tr><td>MSFT</td><td>300.0</td><td>1705747800</td></tr></table>";
        $this->setMockResponses([
            new Response(200, [], $htmlContent)
        ]);

        $response = $this->client->stocks->quotes(['AAPL', 'MSFT'], parameters: new Parameters(format: Format::HTML, date_format: DateFormat::TIMESTAMP));

        $this->assertIsObject($response);
        $this->assertIsArray($response->quotes);
        // HTML format returns a single Quote object containing all data
        $this->assertCount(1, $response->quotes);
        $this->assertTrue($response->quotes[0]->isHtml());
    }

    // ============================================================================
    // Constructor Validation Tests
    // ============================================================================

    public function testParameters_dateFormat_withCsv_success(): void
    {
        $params1 = new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP);
        $this->assertEquals(Format::CSV, $params1->format);
        $this->assertEquals(DateFormat::TIMESTAMP, $params1->date_format);

        $params2 = new Parameters(format: Format::CSV, date_format: DateFormat::UNIX);
        $this->assertEquals(Format::CSV, $params2->format);
        $this->assertEquals(DateFormat::UNIX, $params2->date_format);

        $params3 = new Parameters(format: Format::CSV, date_format: DateFormat::SPREADSHEET);
        $this->assertEquals(Format::CSV, $params3->format);
        $this->assertEquals(DateFormat::SPREADSHEET, $params3->date_format);
    }

    public function testParameters_dateFormat_withHtml_success(): void
    {
        $params1 = new Parameters(format: Format::HTML, date_format: DateFormat::TIMESTAMP);
        $this->assertEquals(Format::HTML, $params1->format);
        $this->assertEquals(DateFormat::TIMESTAMP, $params1->date_format);

        $params2 = new Parameters(format: Format::HTML, date_format: DateFormat::UNIX);
        $this->assertEquals(Format::HTML, $params2->format);
        $this->assertEquals(DateFormat::UNIX, $params2->date_format);

        $params3 = new Parameters(format: Format::HTML, date_format: DateFormat::SPREADSHEET);
        $this->assertEquals(Format::HTML, $params3->format);
        $this->assertEquals(DateFormat::SPREADSHEET, $params3->date_format);
    }

    public function testParameters_dateFormat_withJson_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('date_format parameter can only be used with CSV or HTML format');

        new Parameters(format: Format::JSON, date_format: DateFormat::TIMESTAMP);
    }

    public function testParameters_dateFormat_null_withCsv_success(): void
    {
        $params = new Parameters(format: Format::CSV, date_format: null);
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertNull($params->date_format);
    }

    public function testParameters_dateFormat_null_withHtml_success(): void
    {
        $params = new Parameters(format: Format::HTML, date_format: null);
        $this->assertEquals(Format::HTML, $params->format);
        $this->assertNull($params->date_format);
    }

    public function testParameters_dateFormat_null_withJson_success(): void
    {
        $params = new Parameters(format: Format::JSON, date_format: null);
        $this->assertEquals(Format::JSON, $params->format);
        $this->assertNull($params->date_format);
    }

    public function testParameters_default_backwardCompatible(): void
    {
        $params = new Parameters();
        $this->assertEquals(Format::JSON, $params->format);
        $this->assertNull($params->date_format);
        $this->assertNull($params->use_human_readable);
        $this->assertNull($params->mode);
    }

    public function testDateFormat_enumValues(): void
    {
        $this->assertEquals('timestamp', DateFormat::TIMESTAMP->value);
        $this->assertEquals('unix', DateFormat::UNIX->value);
        $this->assertEquals('spreadsheet', DateFormat::SPREADSHEET->value);
    }

    public function testParameters_allParameters_withCsv(): void
    {
        $params = new Parameters(
            format: Format::CSV,
            use_human_readable: true,
            mode: Mode::LIVE,
            date_format: DateFormat::UNIX
        );

        $this->assertEquals(Format::CSV, $params->format);
        $this->assertTrue($params->use_human_readable);
        $this->assertEquals(Mode::LIVE, $params->mode);
        $this->assertEquals(DateFormat::UNIX, $params->date_format);
    }
}
