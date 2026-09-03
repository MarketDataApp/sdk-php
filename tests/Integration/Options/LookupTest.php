<?php

namespace MarketDataApp\Tests\Integration\Options;

use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\Lookup;

/**
 * Integration tests for the Options Lookup endpoint.
 */
class LookupTest extends OptionsTestCase
{
    /**
     * Test successful lookup of an option symbol.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testLookup_success()
    {
        $response = $this->client->options->lookup('AAPL 12/15/28 $400 Call');

        $this->assertInstanceOf(Lookup::class, $response);
        $this->assertSame('ok', $response->status);
        $this->assertEquals('AAPL281215C00400000', $response->option_symbol);
    }

    /**
     * Test options lookup with human-readable format.
     */
    public function testLookup_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->options->lookup(
            input: 'AAPL 12/15/28 $400 Call',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Lookup::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('string', gettype($response->option_symbol));
        $this->assertEquals('AAPL281215C00400000', $response->option_symbol);
        $this->assertNotEmpty($response->option_symbol);
    }

    /**
     * Test options lookup with human_readable=false.
     */
    public function testLookup_humanReadableFalse_returnsRegularKeys()
    {
        $response = $this->client->options->lookup(
            input: 'AAPL 12/15/28 $400 Call',
            parameters: new Parameters(use_human_readable: false)
        );

        $this->assertInstanceOf(Lookup::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('string', gettype($response->option_symbol));
        $this->assertEquals('AAPL281215C00400000', $response->option_symbol);
        $this->assertNotEmpty($response->option_symbol);
    }
}
