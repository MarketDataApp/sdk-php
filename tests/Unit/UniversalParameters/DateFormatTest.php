<?php

namespace MarketDataApp\Tests\Unit\UniversalParameters;

use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
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

    public function testIntegration_parallelRequests_withDateFormat(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;
        $this->client->default_params->date_format = DateFormat::UNIX;

        $mockResponse1 = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'ask' => [150.0],
            'askSize' => [100],
            'bid' => [149.5],
            'bidSize' => [200],
            'mid' => [149.75],
            'last' => [150.0],
            'change' => [1.0],
            'changepct' => [0.67],
            'volume' => [1000000],
            'updated' => ['2024-01-20T10:30:00Z']
        ];
        $mockResponse2 = [
            's' => 'ok',
            'symbol' => ['MSFT'],
            'ask' => [300.0],
            'askSize' => [100],
            'bid' => [299.5],
            'bidSize' => [200],
            'mid' => [299.75],
            'last' => [300.0],
            'change' => [2.0],
            'changepct' => [0.67],
            'volume' => [2000000],
            'updated' => ['2024-01-20T10:30:00Z']
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($mockResponse1)),
            new Response(200, [], json_encode($mockResponse2))
        ]);

        $response = $this->client->stocks->quotes(['AAPL', 'MSFT'], parameters: new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP));

        $this->assertIsObject($response);
        $this->assertIsArray($response->quotes);
        $this->assertCount(2, $response->quotes);
    }

    public function testIntegration_parallelRequests_withDateFormat_htmlFormat(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::HTML;
        $this->client->default_params->date_format = DateFormat::UNIX;

        $mockResponse1 = [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'ask' => [150.0],
            'askSize' => [100],
            'bid' => [149.5],
            'bidSize' => [200],
            'mid' => [149.75],
            'last' => [150.0],
            'change' => [1.0],
            'changepct' => [0.67],
            'volume' => [1000000],
            'updated' => ['2024-01-20T10:30:00Z']
        ];
        $mockResponse2 = [
            's' => 'ok',
            'symbol' => ['MSFT'],
            'ask' => [300.0],
            'askSize' => [100],
            'bid' => [299.5],
            'bidSize' => [200],
            'mid' => [299.75],
            'last' => [300.0],
            'change' => [2.0],
            'changepct' => [0.67],
            'volume' => [2000000],
            'updated' => ['2024-01-20T10:30:00Z']
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($mockResponse1)),
            new Response(200, [], json_encode($mockResponse2))
        ]);

        $response = $this->client->stocks->quotes(['AAPL', 'MSFT'], parameters: new Parameters(format: Format::HTML, date_format: DateFormat::TIMESTAMP));

        $this->assertIsObject($response);
        $this->assertIsArray($response->quotes);
        $this->assertCount(2, $response->quotes);
    }
}
