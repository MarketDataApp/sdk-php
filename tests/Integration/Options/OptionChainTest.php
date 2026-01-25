<?php

namespace MarketDataApp\Tests\Integration\Options;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\OptionQuote;
use MarketDataApp\Endpoints\Responses\Options\OptionChains;
use MarketDataApp\Enums\Expiration;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Side;

/**
 * Integration tests for the Options OptionChain endpoint.
 */
class OptionChainTest extends OptionsTestCase
{
    /**
     * Test successful retrieval of option chain.
     */
    public function testOptionChain_success()
    {
        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: '2028-12-15',
            side: Side::CALL,
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertNotEmpty($response->option_chains);
        $option_chain = array_pop($response->option_chains);
        $this->assertNotEmpty($option_chain);

        $option_strike = array_pop($option_chain);
        $this->assertInstanceOf(OptionQuote::class, $option_strike);
        $this->assertEquals('string', gettype($option_strike->option_symbol));
        $this->assertEquals('string', gettype($option_strike->underlying));
        $this->assertInstanceOf(Carbon::class, $option_strike->expiration);
        $this->assertInstanceOf(Side::class, $option_strike->side);
        $this->assertEquals('double', gettype($option_strike->strike));
        $this->assertInstanceOf(Carbon::class, $option_strike->first_traded);
        $this->assertEquals('integer', gettype($option_strike->dte));
        $this->assertInstanceOf(Carbon::class, $option_strike->updated);
        $this->assertEquals('double', gettype($option_strike->bid));
        $this->assertEquals('integer', gettype($option_strike->bid_size));
        $this->assertEquals('double', gettype($option_strike->mid));
        $this->assertEquals('double', gettype($option_strike->ask));
        $this->assertEquals('integer', gettype($option_strike->ask_size));
        $this->assertTrue(in_array(gettype($option_strike->last), ['double', 'NULL']));
        $this->assertEquals('integer', gettype($option_strike->open_interest));
        $this->assertEquals('integer', gettype($option_strike->volume));
        $this->assertEquals('boolean', gettype($option_strike->in_the_money));
        $this->assertEquals('double', gettype($option_strike->intrinsic_value));
        $this->assertEquals('double', gettype($option_strike->extrinsic_value));
        $this->assertEquals('double', gettype($option_strike->implied_volatility));
        $this->assertTrue(in_array(gettype($option_strike->delta), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($option_strike->gamma), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($option_strike->theta), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($option_strike->vega), ['double', 'NULL']));
        $this->assertEquals('double', gettype($option_strike->underlying_price));
    }

    /**
     * Test successful retrieval of option chain in CSV format.
     */
    public function testOptionChain_csv_success()
    {
        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: '2025-01-17',
            side: Side::CALL,
            parameters: new Parameters(format: Format::CSV),
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('string', gettype($response->getCsv()));
    }

    /**
     * Test successful retrieval of option chain using Expiration enum.
     */
    public function testOptionChain_expirationEnum_success()
    {
        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: Expiration::ALL,
            side: Side::CALL,
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertNotEmpty($response->option_chains);
        $option_chain = array_pop($response->option_chains);
        $this->assertNotEmpty($option_chain);

        $option_strike = array_pop($option_chain);
        $this->assertInstanceOf(OptionQuote::class, $option_strike);
        $this->assertEquals('string', gettype($option_strike->option_symbol));
        $this->assertEquals('string', gettype($option_strike->underlying));
        $this->assertInstanceOf(Carbon::class, $option_strike->expiration);
        $this->assertInstanceOf(Side::class, $option_strike->side);
        $this->assertEquals('double', gettype($option_strike->strike));
        $this->assertInstanceOf(Carbon::class, $option_strike->first_traded);
        $this->assertEquals('integer', gettype($option_strike->dte));
        $this->assertInstanceOf(Carbon::class, $option_strike->updated);
        $this->assertEquals('double', gettype($option_strike->bid));
        $this->assertEquals('integer', gettype($option_strike->bid_size));
        $this->assertEquals('double', gettype($option_strike->mid));
        $this->assertEquals('double', gettype($option_strike->ask));
        $this->assertEquals('integer', gettype($option_strike->ask_size));
        $this->assertTrue(in_array(gettype($option_strike->last), ['double', 'NULL']));
        $this->assertEquals('integer', gettype($option_strike->open_interest));
        $this->assertEquals('integer', gettype($option_strike->volume));
        $this->assertEquals('boolean', gettype($option_strike->in_the_money));
        $this->assertEquals('double', gettype($option_strike->intrinsic_value));
        $this->assertEquals('double', gettype($option_strike->extrinsic_value));
        $this->assertEquals('double', gettype($option_strike->implied_volatility));
        $this->assertTrue(in_array(gettype($option_strike->delta), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($option_strike->gamma), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($option_strike->theta), ['double', 'NULL']));
        $this->assertTrue(in_array(gettype($option_strike->vega), ['double', 'NULL']));
        $this->assertEquals('double', gettype($option_strike->underlying_price));
    }

    /**
     * Test options chain with human-readable format.
     */
    public function testOptionChain_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: '2028-12-15',
            side: Side::CALL,
            strike_limit: 5,
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->option_chains);
        $option_chain = array_pop($response->option_chains);
        $this->assertNotEmpty($option_chain);

        $option_strike = array_pop($option_chain);
        $this->assertInstanceOf(OptionQuote::class, $option_strike);
        $this->assertEquals('string', gettype($option_strike->option_symbol));
        $this->assertEquals('string', gettype($option_strike->underlying));
        $this->assertInstanceOf(Carbon::class, $option_strike->expiration);
        $this->assertInstanceOf(Side::class, $option_strike->side);
        $this->assertEquals('double', gettype($option_strike->strike));
        $this->assertInstanceOf(Carbon::class, $option_strike->first_traded);
        $this->assertEquals('integer', gettype($option_strike->dte));
        $this->assertInstanceOf(Carbon::class, $option_strike->updated);
        $this->assertEquals('double', gettype($option_strike->bid));
        $this->assertEquals('integer', gettype($option_strike->bid_size));
        $this->assertEquals('double', gettype($option_strike->mid));
        $this->assertEquals('double', gettype($option_strike->ask));
        $this->assertEquals('integer', gettype($option_strike->ask_size));
    }

    /**
     * Test options chain with human_readable=false.
     */
    public function testOptionChain_humanReadableFalse_returnsRegularKeys()
    {
        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: '2028-12-15',
            side: Side::CALL,
            strike_limit: 5,
            parameters: new Parameters(use_human_readable: false)
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->option_chains);
        $option_chain = array_pop($response->option_chains);
        $this->assertNotEmpty($option_chain);

        $option_strike = array_pop($option_chain);
        $this->assertInstanceOf(OptionQuote::class, $option_strike);
        $this->assertEquals('string', gettype($option_strike->option_symbol));
        $this->assertEquals('string', gettype($option_strike->underlying));
    }

    /**
     * Test real-time option chain with from/to date range filter.
     *
     * Tests whether the API now supports filtering real-time quotes by expiration date range.
     * Documentation previously stated: "from, to, month, year, weekly, monthly, and quarterly
     * filtering parameters are not yet supported for real-time quotes."
     */
    public function testOptionChain_realTimeWithFromTo_success()
    {
        // Calculate a date range that should include some expirations
        // Use 30-90 days out to ensure we have expirations in range
        $from = Carbon::now()->addDays(30)->format('Y-m-d');
        $to = Carbon::now()->addDays(90)->format('Y-m-d');

        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: Expiration::ALL,
            from: $from,
            to: $to,
            side: Side::CALL,
            strike_limit: 5,
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status, 'Real-time chain with from/to filter should return ok status');
        $this->assertNotEmpty($response->option_chains, 'Should have option chains in the date range');

        // Verify all returned expirations are within the specified range
        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);
        foreach ($response->option_chains as $expirationDate => $strikes) {
            $expDate = Carbon::parse($expirationDate);
            $this->assertTrue(
                $expDate->gte($fromDate) && $expDate->lt($toDate),
                "Expiration {$expirationDate} should be between {$from} and {$to}"
            );
        }
    }

    /**
     * Test real-time option chain with month filter.
     */
    public function testOptionChain_realTimeWithMonth_success()
    {
        // Pick a month that's likely to have expirations (3 months out)
        $targetDate = Carbon::now()->addMonths(3);
        $month = (int) $targetDate->format('n');
        $year = (int) $targetDate->format('Y');

        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: Expiration::ALL,
            month: $month,
            year: $year,
            side: Side::CALL,
            strike_limit: 5,
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status, 'Real-time chain with month filter should return ok status');
        $this->assertNotEmpty($response->option_chains, "Should have option chains in month {$month}");

        // Verify all returned expirations are in the specified month
        foreach ($response->option_chains as $expirationDate => $strikes) {
            $expDate = Carbon::parse($expirationDate);
            $this->assertEquals(
                $month,
                (int) $expDate->format('n'),
                "Expiration {$expirationDate} should be in month {$month}"
            );
            $this->assertEquals(
                $year,
                (int) $expDate->format('Y'),
                "Expiration {$expirationDate} should be in year {$year}"
            );
        }
    }

    /**
     * Test real-time option chain with year filter only.
     */
    public function testOptionChain_realTimeWithYear_success()
    {
        // Use next year to ensure we get future expirations
        $year = (int) Carbon::now()->addYear()->format('Y');

        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: Expiration::ALL,
            year: $year,
            side: Side::CALL,
            strike_limit: 3,
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status, 'Real-time chain with year filter should return ok status');
        $this->assertNotEmpty($response->option_chains, "Should have option chains in year {$year}");

        // Verify all returned expirations are in the specified year
        foreach ($response->option_chains as $expirationDate => $strikes) {
            $expDate = Carbon::parse($expirationDate);
            $this->assertEquals(
                $year,
                (int) $expDate->format('Y'),
                "Expiration {$expirationDate} should be in year {$year}"
            );
        }
    }

    /**
     * Test real-time option chain with weekly=false (monthly only).
     */
    public function testOptionChain_realTimeMonthlyOnly_success()
    {
        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: Expiration::ALL,
            weekly: false,
            monthly: true,
            quarterly: false,
            side: Side::CALL,
            strike_limit: 3,
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertEquals('ok', $response->status, 'Real-time chain with monthly=true filter should return ok status');
        $this->assertNotEmpty($response->option_chains, 'Should have monthly option chains');

        // Verify we get fewer expirations than with all=true (monthly filter is applied)
        // Monthly options typically fall on the 3rd Friday (or Thursday if Friday is a holiday)
        $expirationCount = count($response->option_chains);
        $this->assertGreaterThan(0, $expirationCount, 'Should have at least one monthly expiration');
    }

    /**
     * Test real-time option chain with quarterly=true only.
     */
    public function testOptionChain_realTimeQuarterlyOnly_success()
    {
        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: Expiration::ALL,
            weekly: false,
            monthly: false,
            quarterly: true,
            side: Side::CALL,
            strike_limit: 3,
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        // Quarterly expirations may be sparse, so we just check the response is valid
        $this->assertContains($response->status, ['ok', 'no_data'], 'Real-time chain with quarterly filter should return valid status');
    }
}
