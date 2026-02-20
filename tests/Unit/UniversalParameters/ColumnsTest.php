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
 * Unit tests for the Columns universal parameter.
 *
 * Tests parameter merging, environment variable support, and validation
 * for the columns parameter (array of column names for CSV/HTML output).
 * Note: columns is only valid for CSV/HTML formats.
 */
class ColumnsTest extends UniversalParametersTestCase
{
    // ============================================================================
    // Parameter Merging Tests
    // ============================================================================

    public function testMergeParameters_columns_methodParamOverridesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->columns = ['symbol', 'ask'];
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV, columns: ['bid', 'last']));
        $this->assertEquals(['bid', 'last'], $merged->columns);
    }

    public function testMergeParameters_columns_nullMethodParamUsesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->columns = ['symbol', 'ask', 'bid'];
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV));
        $this->assertEquals(['symbol', 'ask', 'bid'], $merged->columns);
    }

    public function testMergeParameters_columns_emptyArrayOverridesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->columns = ['symbol', 'ask'];
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV, columns: []));
        $this->assertEquals([], $merged->columns);
    }

    // ============================================================================
    // Environment Variable Tests
    // ============================================================================

    public function testGetDefaultParameters_columns_fromEnvVar_singleColumn(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_COLUMNS=symbol');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_COLUMNS'] = 'symbol';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(['symbol'], $params->columns);
    }

    public function testGetDefaultParameters_columns_fromEnvVar_multipleColumns(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_COLUMNS=symbol,ask,bid');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_COLUMNS'] = 'symbol,ask,bid';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(['symbol', 'ask', 'bid'], $params->columns);
    }

    public function testGetDefaultParameters_columns_fromEnvVar_withSpaces(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_COLUMNS=symbol, ask, bid');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_COLUMNS'] = 'symbol, ask, bid';
        $params = Settings::getDefaultParameters();
        $this->assertEquals(['symbol', 'ask', 'bid'], $params->columns);
    }

    public function testGetDefaultParameters_columns_emptyString_returnsNull(): void
    {
        putenv('MARKETDATA_COLUMNS=');
        $_ENV['MARKETDATA_COLUMNS'] = '';
        $params = Settings::getDefaultParameters();
        $this->assertNull($params->columns);
    }

    public function testGetDefaultParameters_columns_notSet_returnsNull(): void
    {
        putenv('MARKETDATA_COLUMNS');
        unset($_ENV['MARKETDATA_COLUMNS']);
        $params = Settings::getDefaultParameters();
        $this->assertNull($params->columns);
    }

    // ============================================================================
    // Format Restriction Tests
    // ============================================================================

    public function testIntegration_formatChange_resetsCsvOnlyParams(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;
        $this->client->default_params->columns = ['symbol', 'ask'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('columns parameter can only be used with CSV or HTML format');

        $this->client->stocks->quote('AAPL', parameters: new Parameters(format: Format::JSON));
    }

    public function testIntegration_multiSymbol_withColumns_csvFormat(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;

        // Mock CSV response for multi-symbol request (single API call returns all data)
        $csvContent = "symbol,ask\nAAPL,150.0\nMSFT,300.0";
        $this->setMockResponses([
            new Response(200, [], $csvContent)
        ]);

        $response = $this->client->stocks->quotes(['AAPL', 'MSFT'], parameters: new Parameters(format: Format::CSV, columns: ['symbol', 'ask']));

        $this->assertIsObject($response);
        $this->assertIsArray($response->quotes);
        // CSV format returns a single Quote object containing all data
        $this->assertCount(1, $response->quotes);
        $this->assertTrue($response->quotes[0]->isCsv());
        $this->assertStringContainsString('AAPL', $response->quotes[0]->getCsv());
        $this->assertStringContainsString('MSFT', $response->quotes[0]->getCsv());
    }

    public function testIntegration_multiSymbol_withColumns_htmlFormat(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::HTML;

        // Mock HTML response for multi-symbol request (single API call returns all data)
        $htmlContent = "<table><tr><th>symbol</th><th>ask</th></tr><tr><td>AAPL</td><td>150.0</td></tr><tr><td>MSFT</td><td>300.0</td></tr></table>";
        $this->setMockResponses([
            new Response(200, [], $htmlContent)
        ]);

        $response = $this->client->stocks->quotes(['AAPL', 'MSFT'], parameters: new Parameters(format: Format::HTML, columns: ['symbol', 'ask']));

        $this->assertIsObject($response);
        $this->assertIsArray($response->quotes);
        // HTML format returns a single Quote object containing all data
        $this->assertCount(1, $response->quotes);
        $this->assertTrue($response->quotes[0]->isHtml());
        $this->assertStringContainsString('AAPL', $response->quotes[0]->getHtml());
        $this->assertStringContainsString('MSFT', $response->quotes[0]->getHtml());
    }

    public function testIntegration_multiSymbol_withColumns_jsonFormat(): void
    {
        $this->client = new Client('');

        // Mock JSON response for multi-symbol request (single API call returns all data)
        $mockResponse = [
            's' => 'ok',
            'symbol' => ['AAPL', 'MSFT'],
            'ask' => [150.0, 300.0],
            'askSize' => [100, 100],
            'bid' => [149.5, 299.5],
            'bidSize' => [200, 200],
            'mid' => [149.75, 299.75],
            'last' => [150.0, 300.0],
            'change' => [1.0, 2.0],
            'changepct' => [0.67, 0.67],
            'volume' => [1000000, 2000000],
            'updated' => [1705747800, 1705747800]
        ];
        $this->setMockResponses([
            new Response(200, [], json_encode($mockResponse))
        ]);

        $response = $this->client->stocks->quotes(['AAPL', 'MSFT']);

        $this->assertIsObject($response);
        $this->assertIsArray($response->quotes);
        // JSON format creates individual Quote objects for each symbol
        $this->assertCount(2, $response->quotes);
        $this->assertEquals('AAPL', $response->quotes[0]->symbol);
        $this->assertEquals('MSFT', $response->quotes[1]->symbol);
    }

    // ============================================================================
    // Constructor Validation Tests
    // ============================================================================

    public function testParameters_columns_withCsv_success(): void
    {
        $params1 = new Parameters(format: Format::CSV, columns: ['symbol']);
        $this->assertEquals(Format::CSV, $params1->format);
        $this->assertEquals(['symbol'], $params1->columns);

        $params2 = new Parameters(format: Format::CSV, columns: ['symbol', 'ask', 'bid']);
        $this->assertEquals(Format::CSV, $params2->format);
        $this->assertEquals(['symbol', 'ask', 'bid'], $params2->columns);
    }

    public function testParameters_columns_withHtml_success(): void
    {
        $params1 = new Parameters(format: Format::HTML, columns: ['symbol']);
        $this->assertEquals(Format::HTML, $params1->format);
        $this->assertEquals(['symbol'], $params1->columns);

        $params2 = new Parameters(format: Format::HTML, columns: ['symbol', 'ask', 'bid']);
        $this->assertEquals(Format::HTML, $params2->format);
        $this->assertEquals(['symbol', 'ask', 'bid'], $params2->columns);
    }

    public function testParameters_columns_withJson_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('columns parameter can only be used with CSV or HTML format');

        new Parameters(format: Format::JSON, columns: ['symbol']);
    }

    public function testParameters_columns_null_withCsv_success(): void
    {
        $params = new Parameters(format: Format::CSV, columns: null);
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertNull($params->columns);
    }

    public function testParameters_columns_null_withHtml_success(): void
    {
        $params = new Parameters(format: Format::HTML, columns: null);
        $this->assertEquals(Format::HTML, $params->format);
        $this->assertNull($params->columns);
    }

    public function testParameters_columns_null_withJson_success(): void
    {
        $params = new Parameters(format: Format::JSON, columns: null);
        $this->assertEquals(Format::JSON, $params->format);
        $this->assertNull($params->columns);
    }

    public function testParameters_columns_emptyArray_withCsv_success(): void
    {
        $params = new Parameters(format: Format::CSV, columns: []);
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertEquals([], $params->columns);
    }

    public function testParameters_columns_nonStringArray_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('columns parameter must contain only strings');

        new Parameters(format: Format::CSV, columns: ['symbol', 123]);
    }

    public function testParameters_columns_mixedTypes_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('columns parameter must contain only strings');

        new Parameters(format: Format::CSV, columns: ['symbol', null]);
    }

    public function testParameters_columns_singleColumn_success(): void
    {
        $params = new Parameters(format: Format::CSV, columns: ['symbol']);
        $this->assertEquals(['symbol'], $params->columns);
    }

    public function testParameters_columns_multipleColumns_success(): void
    {
        $params = new Parameters(format: Format::CSV, columns: ['symbol', 'ask', 'bid', 'last']);
        $this->assertEquals(['symbol', 'ask', 'bid', 'last'], $params->columns);
    }

    public function testParameters_columns_withOtherParameters_success(): void
    {
        $params = new Parameters(
            format: Format::CSV,
            use_human_readable: true,
            mode: Mode::LIVE,
            date_format: DateFormat::UNIX,
            columns: ['symbol', 'ask', 'bid']
        );

        $this->assertEquals(Format::CSV, $params->format);
        $this->assertTrue($params->use_human_readable);
        $this->assertEquals(Mode::LIVE, $params->mode);
        $this->assertEquals(DateFormat::UNIX, $params->date_format);
        $this->assertEquals(['symbol', 'ask', 'bid'], $params->columns);
    }
}
