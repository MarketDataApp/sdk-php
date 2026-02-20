<?php

namespace MarketDataApp\Tests\Unit\Options;

use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\Lookup;
use MarketDataApp\Enums\Format;

/**
 * Unit tests for the Options Lookup endpoint.
 */
class LookupTest extends OptionsTestCase
{
    /**
     * Test the lookup endpoint for a successful response.
     */
    public function testLookup_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'            => 'no_data',
            'optionSymbol' => 'AAPL230728C00200000',
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->lookup('AAPL 7/28/23 $200 Call');

        $this->assertInstanceOf(Lookup::class, $response);
        $this->assertEquals($mocked_response['optionSymbol'], $response->option_symbol);
    }

    /**
     * Test the lookup endpoint for a successful CSV response.
     */
    public function testLookup_csv_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, optionSymbol\r\n";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->lookup('AAPL 7/28/23 $200 Call', new Parameters(format: Format::CSV));

        $this->assertInstanceOf(Lookup::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test that CSV response initializes typed properties to safe defaults.
     *
     * Regression test for BUG-018: Options lookup CSV responses leave typed
     * properties uninitialized, causing fatal errors when accessed.
     */
    public function testLookup_csv_typedPropertiesInitialized()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, optionSymbol\r\n";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->options->lookup('AAPL 7/28/23 $200 Call', new Parameters(format: Format::CSV));

        // Should not throw "must not be accessed before initialization"
        $this->assertEquals('no_data', $response->status);
        $this->assertNull($response->option_symbol);
    }

    /**
     * Test the lookup endpoint with human-readable format.
     */
    public function testLookup_humanReadable_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            'Symbol' => 'AAPL230728C00200000'
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->lookup(
            'AAPL 7/28/23 $200 Call',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Lookup::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals($mocked_response['Symbol'], $response->option_symbol);
    }

    /**
     * Test the lookup endpoint with human-readable format when Symbol is an array.
     *
     * Regression test for BUG-028: Options lookup crashes when human-readable
     * Symbol is an array instead of a string.
     */
    public function testLookup_humanReadable_symbolAsArray()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        // API can return Symbol as an array even for single results
        $mocked_response = [
            'Symbol' => ['AAPL230728C00200000']
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->options->lookup(
            'AAPL 7/28/23 $200 Call',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Lookup::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('AAPL230728C00200000', $response->option_symbol);
    }

    /**
     * Test lookup endpoint with empty input.
     */
    public function testLookup_emptyInput_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-empty string');

        $this->client->options->lookup('');
    }
}
