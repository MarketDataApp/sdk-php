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
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_success()
    {
        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            expiration: '2028-12-15',
            side: Side::CALL,
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertSame('ok', $response->status);
        $this->assertNotEmpty($response->option_chains);
        $option_chain = array_pop($response->option_chains);
        $this->assertNotEmpty($option_chain);

        $option_strike = array_pop($option_chain);
        $this->assertInstanceOf(OptionQuote::class, $option_strike);
        $this->assertStringStartsWith('AAPL281215C', $option_strike->option_symbol);
        $this->assertSame('AAPL', $option_strike->underlying);
        $this->assertSame('2028-12-15', $option_strike->expiration->toDateString());
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

    /**
     * Test real-time option chain with dte as a single int value (existing form).
     *
     * A single dte value limits the chain to the one expiration closest to it.
     * Combining dte with expiration: Expiration::ALL makes the server ignore
     * dte entirely and return the complete chain, so these tests omit
     * expiration to let dte alone pick the expiration, as the documentation's
     * own default ("if omitted the next monthly expiration ... will be
     * returned") describes for real-time quotes.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_realTimeWithDteIntSingleValue_success(): void
    {
        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            side: Side::CALL,
            strike_limit: 1,
            dte: 400,
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertSame('ok', $response->status);
        $this->assertCount(
            1,
            $response->getExpirationDates(),
            'A single dte value should limit the chain to exactly one expiration'
        );
        $quotes = $response->getAllQuotes();
        $this->assertNotEmpty($quotes);
        $this->assertGreaterThan(
            150,
            $quotes[0]->dte,
            'dte=400 should select a far-dated expiration, which the near-term default (no dte) cannot satisfy'
        );
    }

    /**
     * Test real-time option chain with dte as a single string value.
     *
     * The dte parameter now also accepts a single value as a string (e.g. "400"),
     * which must behave the same way as the existing int form.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_realTimeWithDteStringSingleValue_success(): void
    {
        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            side: Side::CALL,
            strike_limit: 1,
            dte: '400',
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertSame('ok', $response->status);
        $this->assertCount(
            1,
            $response->getExpirationDates(),
            'A single string dte value should limit the chain to exactly one expiration'
        );
        $quotes = $response->getAllQuotes();
        $this->assertNotEmpty($quotes);
        $this->assertGreaterThan(
            150,
            $quotes[0]->dte,
            'dte="400" should select a far-dated expiration, which the near-term default (no dte) cannot satisfy'
        );
    }

    /**
     * Test real-time option chain with dte as a comma-separated list.
     *
     * A comma-separated list selects the closest expiration for each value,
     * deduplicated, so the result must never have more expirations than values
     * provided, and must have more than the single expiration the default
     * (no dte) chain returns.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_realTimeWithDteStringList_success(): void
    {
        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            side: Side::CALL,
            strike_limit: 1,
            dte: '15,30,45',
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertSame('ok', $response->status);
        $expirationCount = count($response->getExpirationDates());
        $this->assertGreaterThan(
            1,
            $expirationCount,
            'A three-value dte list should return more than the one expiration the default (no dte) chain returns'
        );
        $this->assertLessThanOrEqual(
            3,
            $expirationCount,
            'A three-value dte list should return at most one expiration per value, deduplicated'
        );
    }

    /**
     * Test real-time option chain with dte as a closed range.
     *
     * A closed range returns every expiration whose dte falls within the
     * bounds, inclusive, and must return more than the single expiration the
     * default (no dte) chain returns.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_realTimeWithDteStringClosedRange_success(): void
    {
        // Anchor the upper bound on the actual dte of the expiration closest to
        // 45, found via the single-value form.
        $anchorResponse = $this->client->options->option_chain(
            symbol: 'AAPL',
            side: Side::CALL,
            strike_limit: 1,
            dte: 45,
        );
        $anchorDte = $anchorResponse->getAllQuotes()[0]->dte;

        $response = $this->client->options->option_chain(
            symbol: 'AAPL',
            side: Side::CALL,
            strike_limit: 1,
            dte: "0-{$anchorDte}",
        );

        $this->assertInstanceOf(OptionChains::class, $response);
        $this->assertSame('ok', $response->status);
        $quotes = $response->getAllQuotes();
        $this->assertNotEmpty($quotes, 'Should have option chains within the dte range');

        $dtes = [];
        foreach ($quotes as $quote) {
            $this->assertGreaterThanOrEqual(0, $quote->dte, "dte {$quote->dte} should satisfy the 0-{$anchorDte} range");
            $this->assertLessThanOrEqual($anchorDte, $quote->dte, "dte {$quote->dte} should satisfy the 0-{$anchorDte} range");
            $dtes[] = $quote->dte;
        }
        $this->assertContains(
            $anchorDte,
            $dtes,
            'The closed range upper bound is inclusive, so the anchor expiration must be kept'
        );
        $this->assertGreaterThan(
            1,
            count($response->getExpirationDates()),
            'The dte range should return more than the one expiration the default (no dte) chain returns'
        );
    }

    /**
     * Test real-time option chain with dte as a comparison operator expression.
     *
     * >=N keeps the expiration at exactly N (inclusive), while <N leaves it out
     * (exclusive). A low anchor is used for >=, and a high anchor for <, so
     * that each filtered answer still spans more than the single expiration
     * the default (no dte) chain returns.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_realTimeWithDteStringComparison_success(): void
    {
        // Low anchor: the expiration closest to 7 days, found via the
        // single-value form.
        $lowAnchorResponse = $this->client->options->option_chain(
            symbol: 'AAPL',
            side: Side::CALL,
            strike_limit: 1,
            dte: 7,
        );
        $lowAnchorDte = $lowAnchorResponse->getAllQuotes()[0]->dte;

        $gteResponse = $this->client->options->option_chain(
            symbol: 'AAPL',
            side: Side::CALL,
            strike_limit: 1,
            dte: ">={$lowAnchorDte}",
        );
        $this->assertInstanceOf(OptionChains::class, $gteResponse);
        $this->assertSame('ok', $gteResponse->status);
        $gteDtes = array_map(fn($quote) => $quote->dte, $gteResponse->getAllQuotes());
        $this->assertNotEmpty($gteDtes);
        foreach ($gteDtes as $dte) {
            $this->assertGreaterThanOrEqual($lowAnchorDte, $dte, "dte {$dte} should satisfy >={$lowAnchorDte}");
        }
        $this->assertContains(
            $lowAnchorDte,
            $gteDtes,
            ">={$lowAnchorDte} is inclusive, so the anchor expiration must be kept"
        );
        $this->assertGreaterThan(
            1,
            count($gteResponse->getExpirationDates()),
            ">={$lowAnchorDte} should return more than the one expiration the default (no dte) chain returns"
        );

        // High anchor: the expiration closest to 400 days, found via the
        // single-value form.
        $highAnchorResponse = $this->client->options->option_chain(
            symbol: 'AAPL',
            side: Side::CALL,
            strike_limit: 1,
            dte: 400,
        );
        $highAnchorDte = $highAnchorResponse->getAllQuotes()[0]->dte;

        $ltResponse = $this->client->options->option_chain(
            symbol: 'AAPL',
            side: Side::CALL,
            strike_limit: 1,
            dte: "<{$highAnchorDte}",
        );
        $this->assertInstanceOf(OptionChains::class, $ltResponse);
        $this->assertSame('ok', $ltResponse->status);
        $ltDtes = array_map(fn($quote) => $quote->dte, $ltResponse->getAllQuotes());
        $this->assertNotEmpty($ltDtes);
        foreach ($ltDtes as $dte) {
            $this->assertLessThan($highAnchorDte, $dte, "dte {$dte} should satisfy <{$highAnchorDte}");
        }
        $this->assertNotContains(
            $highAnchorDte,
            $ltDtes,
            "<{$highAnchorDte} is exclusive, so the anchor expiration must be left out"
        );
        $this->assertGreaterThan(
            1,
            count($ltResponse->getExpirationDates()),
            "<{$highAnchorDte} should return more than the one expiration the default (no dte) chain returns"
        );
    }
}
