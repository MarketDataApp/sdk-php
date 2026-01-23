<?php

namespace MarketDataApp\Tests\Integration\Options;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\Quote;
use MarketDataApp\Endpoints\Responses\Options\Quotes;
use MarketDataApp\Enums\DateFormat;
use MarketDataApp\Enums\Format;

/**
 * Integration tests for the Options Quotes endpoint.
 */
class QuotesTest extends OptionsTestCase
{
    /**
     * Test successful retrieval of option quotes.
     */
    public function testQuotes_success()
    {
        $response = $this->client->options->quotes('AAPL281215C00400000');

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->quotes);

        $this->assertInstanceOf(Quote::class, $response->quotes[0]);
        $this->assertEquals('string', gettype($response->quotes[0]->option_symbol));
        $this->assertEquals('double', gettype($response->quotes[0]->ask));
        $this->assertEquals('integer', gettype($response->quotes[0]->ask_size));
        $this->assertEquals('double', gettype($response->quotes[0]->bid));
        $this->assertEquals('integer', gettype($response->quotes[0]->bid_size));
        $this->assertEquals('double', gettype($response->quotes[0]->mid));
        $this->assertEquals('double', gettype($response->quotes[0]->last));
        $this->assertEquals('integer', gettype($response->quotes[0]->open_interest));
        $this->assertEquals('integer', gettype($response->quotes[0]->volume));
        $this->assertEquals('boolean', gettype($response->quotes[0]->in_the_money));
        $this->assertEquals('double', gettype($response->quotes[0]->underlying_price));
        $this->assertEquals('double', gettype($response->quotes[0]->implied_volatility));
        $this->assertEquals('double', gettype($response->quotes[0]->delta));
        $this->assertEquals('double', gettype($response->quotes[0]->gamma));
        $this->assertEquals('double', gettype($response->quotes[0]->theta));
        $this->assertEquals('double', gettype($response->quotes[0]->vega));
        $this->assertEquals('double', gettype($response->quotes[0]->intrinsic_value));
        $this->assertEquals('double', gettype($response->quotes[0]->extrinsic_value));
        $this->assertInstanceOf(Carbon::class, $response->quotes[0]->updated);
    }

    /**
     * Test successful retrieval of option quotes in CSV format.
     */
    public function testQuotes_csv_success()
    {
        $response = $this->client->options->quotes(
            option_symbol: 'AAPL281215C00400000',
            parameters: new Parameters(format: Format::CSV),
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test options quotes with human-readable format.
     */
    public function testQuotes_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->options->quotes(
            option_symbol: 'AAPL281215C00400000',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->quotes);
        $this->assertInstanceOf(Quote::class, $response->quotes[0]);
        $this->assertEquals('string', gettype($response->quotes[0]->option_symbol));
        $this->assertEquals('double', gettype($response->quotes[0]->ask));
        $this->assertEquals('integer', gettype($response->quotes[0]->ask_size));
        $this->assertEquals('double', gettype($response->quotes[0]->bid));
        $this->assertEquals('integer', gettype($response->quotes[0]->bid_size));
        $this->assertEquals('double', gettype($response->quotes[0]->mid));
        $this->assertTrue(in_array(gettype($response->quotes[0]->last), ['double', 'NULL']));
        $this->assertEquals('integer', gettype($response->quotes[0]->volume));
        $this->assertEquals('integer', gettype($response->quotes[0]->open_interest));
        $this->assertEquals('boolean', gettype($response->quotes[0]->in_the_money));
        $this->assertEquals('double', gettype($response->quotes[0]->underlying_price));
        $this->assertTrue(in_array(gettype($response->quotes[0]->implied_volatility), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->quotes[0]->delta), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->quotes[0]->gamma), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->quotes[0]->theta), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($response->quotes[0]->vega), ['double', 'NULL']));
        $this->assertInstanceOf(Carbon::class, $response->quotes[0]->updated);
    }

    /**
     * Test options quotes with human_readable=false.
     */
    public function testQuotes_humanReadableFalse_returnsRegularKeys()
    {
        $response = $this->client->options->quotes(
            option_symbol: 'AAPL281215C00400000',
            parameters: new Parameters(use_human_readable: false)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->quotes);
        $this->assertInstanceOf(Quote::class, $response->quotes[0]);
        $this->assertEquals('string', gettype($response->quotes[0]->option_symbol));
    }

    /**
     * Test options quotes endpoint with CSV format and dateformat=timestamp.
     */
    public function testQuotes_csv_dateFormat_timestamp_returnsCsv(): void
    {
        $response = $this->client->options->quotes(
            option_symbol: 'AAPL',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);
    }
}
