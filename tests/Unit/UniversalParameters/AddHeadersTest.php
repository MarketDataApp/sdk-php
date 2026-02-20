<?php

namespace MarketDataApp\Tests\Unit\UniversalParameters;

use InvalidArgumentException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;
use MarketDataApp\Settings;

/**
 * Unit tests for the AddHeaders universal parameter.
 *
 * Tests parameter merging, environment variable support, and validation
 * for the add_headers parameter (boolean for CSV/HTML output).
 * Note: add_headers is only valid for CSV/HTML formats.
 */
class AddHeadersTest extends UniversalParametersTestCase
{
    // ============================================================================
    // Parameter Merging Tests
    // ============================================================================

    public function testMergeParameters_addHeaders_methodParamOverridesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->add_headers = true;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV, add_headers: false));
        $this->assertFalse($merged->add_headers);
    }

    public function testMergeParameters_addHeaders_nullMethodParamUsesClientDefault(): void
    {
        $client = new Client();
        $client->default_params->format = Format::CSV;
        $client->default_params->add_headers = false;
        $stocks = $client->stocks;
        $merged = $this->callMergeParameters($stocks, new Parameters(format: Format::CSV));
        $this->assertFalse($merged->add_headers);
    }

    // ============================================================================
    // Environment Variable Tests
    // ============================================================================

    public function testGetDefaultParameters_addHeaders_fromEnvVar_true(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_ADD_HEADERS=true');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_ADD_HEADERS'] = 'true';
        $params = Settings::getDefaultParameters();
        $this->assertTrue($params->add_headers);
    }

    public function testGetDefaultParameters_addHeaders_fromEnvVar_false(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_ADD_HEADERS=false');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_ADD_HEADERS'] = 'false';
        $params = Settings::getDefaultParameters();
        $this->assertFalse($params->add_headers);
    }

    public function testGetDefaultParameters_addHeaders_fromEnvVar_caseInsensitive(): void
    {
        putenv('MARKETDATA_OUTPUT_FORMAT=csv');
        putenv('MARKETDATA_ADD_HEADERS=TRUE');
        $_ENV['MARKETDATA_OUTPUT_FORMAT'] = 'csv';
        $_ENV['MARKETDATA_ADD_HEADERS'] = 'TRUE';
        $params = Settings::getDefaultParameters();
        $this->assertTrue($params->add_headers);
    }

    public function testGetDefaultParameters_addHeaders_invalidValue_returnsNull(): void
    {
        putenv('MARKETDATA_ADD_HEADERS=invalid');
        $_ENV['MARKETDATA_ADD_HEADERS'] = 'invalid';
        $params = Settings::getDefaultParameters();
        $this->assertNull($params->add_headers);
    }

    // ============================================================================
    // Format Restriction Tests
    // ============================================================================

    public function testIntegration_addHeaders_invalidWithJsonFormat(): void
    {
        $this->client = new Client('');
        $this->client->default_params->format = Format::CSV;
        $this->client->default_params->add_headers = true;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('add_headers parameter can only be used with CSV or HTML format');

        $this->client->stocks->quote('AAPL', parameters: new Parameters(format: Format::JSON));
    }

    // ============================================================================
    // Constructor Validation Tests
    // ============================================================================

    public function testParameters_addHeaders_withCsv_success(): void
    {
        $params1 = new Parameters(format: Format::CSV, add_headers: true);
        $this->assertEquals(Format::CSV, $params1->format);
        $this->assertTrue($params1->add_headers);

        $params2 = new Parameters(format: Format::CSV, add_headers: false);
        $this->assertEquals(Format::CSV, $params2->format);
        $this->assertFalse($params2->add_headers);
    }

    public function testParameters_addHeaders_withHtml_success(): void
    {
        $params1 = new Parameters(format: Format::HTML, add_headers: true);
        $this->assertEquals(Format::HTML, $params1->format);
        $this->assertTrue($params1->add_headers);

        $params2 = new Parameters(format: Format::HTML, add_headers: false);
        $this->assertEquals(Format::HTML, $params2->format);
        $this->assertFalse($params2->add_headers);
    }

    public function testParameters_addHeaders_withJson_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('add_headers parameter can only be used with CSV or HTML format');

        new Parameters(format: Format::JSON, add_headers: true);
    }

    public function testParameters_addHeaders_null_withCsv_success(): void
    {
        $params = new Parameters(format: Format::CSV, add_headers: null);
        $this->assertEquals(Format::CSV, $params->format);
        $this->assertNull($params->add_headers);
    }

    public function testParameters_addHeaders_null_withHtml_success(): void
    {
        $params = new Parameters(format: Format::HTML, add_headers: null);
        $this->assertEquals(Format::HTML, $params->format);
        $this->assertNull($params->add_headers);
    }

    public function testParameters_addHeaders_null_withJson_success(): void
    {
        $params = new Parameters(format: Format::JSON, add_headers: null);
        $this->assertEquals(Format::JSON, $params->format);
        $this->assertNull($params->add_headers);
    }

    public function testParameters_addHeaders_withOtherParameters_success(): void
    {
        $params = new Parameters(
            format: Format::CSV,
            use_human_readable: true,
            mode: Mode::LIVE,
            date_format: DateFormat::UNIX,
            columns: ['symbol', 'ask', 'bid'],
            add_headers: true
        );

        $this->assertEquals(Format::CSV, $params->format);
        $this->assertTrue($params->use_human_readable);
        $this->assertEquals(Mode::LIVE, $params->mode);
        $this->assertEquals(DateFormat::UNIX, $params->date_format);
        $this->assertEquals(['symbol', 'ask', 'bid'], $params->columns);
        $this->assertTrue($params->add_headers);
    }

    // ============================================================================
    // Execute Tests (cover line 161 in UniversalParameters.php)
    // ============================================================================

    /**
     * Test that add_headers=true sends headers=true to the API.
     *
     * This test covers line 161 in UniversalParameters.php - the `true` branch of the ternary
     * that sets headers parameter when add_headers=true.
     *
     * Mock response: NOT from real API output (synthetic data)
     */
    public function testExecute_addHeadersTrue_sendsHeadersTrue(): void
    {
        // Set up mock response for CSV format
        $history = [];
        $mock = new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(200, [], "symbol,last\nAAPL,150.0"),
        ]);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
        $handlerStack->push(\GuzzleHttp\Middleware::history($history));
        $this->client->setGuzzle(new \GuzzleHttp\Client(['handler' => $handlerStack]));

        // Make request with add_headers=true explicitly
        $this->client->stocks->quote(
            'AAPL',
            parameters: new Parameters(format: Format::CSV, add_headers: true)
        );

        // Verify headers=true was sent in the query string
        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $query = [];
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertArrayHasKey('headers', $query);
        $this->assertEquals('true', $query['headers']);
    }
}
