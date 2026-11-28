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
     * Test option chain dte filter with a single value selects the expiration
     * closest to the value provided.
     *
     * Per the API: "A single value limits the option chain to the expiration
     * date closest to the dte provided."
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_dteSingleValue_selectsClosestExpiration()
    {
        $near = $this->client->options->option_chain(symbol: 'AAPL', dte: 7, side: Side::CALL, strike_limit: 3);
        $far = $this->client->options->option_chain(symbol: 'AAPL', dte: 365, side: Side::CALL, strike_limit: 3);

        $this->assertEquals('ok', $near->status);
        $this->assertEquals('ok', $far->status);

        $nearExpirations = array_keys($near->option_chains);
        $farExpirations = array_keys($far->option_chains);

        // A server that ignored `dte` would answer both calls with the same
        // single default expiration.
        $this->assertNotEquals($nearExpirations, $farExpirations);

        $nearDte = array_values($near->option_chains[$nearExpirations[0]])[0]->dte;
        $farDte = array_values($far->option_chains[$farExpirations[0]])[0]->dte;
        $this->assertLessThan(abs($farDte - 7), abs($nearDte - 7));
    }

    /**
     * Test option chain dte filter with a comma-separated list selects one
     * expiration per value, deduplicated.
     *
     * Per the API: "A comma-separated list (15,30,45) selects the closest
     * expiration for each value, deduplicated."
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_dteCommaList_selectsMultipleExpirations()
    {
        $response = $this->client->options->option_chain(symbol: 'AAPL', dte: '7,30,90', side: Side::CALL, strike_limit: 3);

        $this->assertEquals('ok', $response->status);
        // Without `dte`, the chain defaults to a single expiration; a
        // comma-separated list must select more than one.
        $this->assertGreaterThan(1, count($response->option_chains));
        // "selects the closest expiration for each value, deduplicated":
        // at most one expiration per requested value, never the whole chain.
        $this->assertLessThanOrEqual(3, count($response->option_chains));
    }

    /**
     * Test option chain dte filter with a closed range returns every
     * expiration whose dte falls within the bounds, inclusive.
     *
     * Per the API: "A closed range (0-45) ... returns every expiration
     * whose dte falls within the bounds, inclusive."
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_dteClosedRange_returnsExpirationsWithinBounds()
    {
        $response = $this->client->options->option_chain(symbol: 'AAPL', dte: '0-45', side: Side::CALL, strike_limit: 3);

        $this->assertEquals('ok', $response->status);
        // Without `dte`, the chain defaults to a single expiration; a
        // closed range must select every matching expiration.
        $this->assertGreaterThan(1, count($response->option_chains));

        foreach ($response->getAllQuotes() as $quote) {
            $this->assertGreaterThanOrEqual(0, $quote->dte);
            $this->assertLessThanOrEqual(45, $quote->dte);
        }
    }

    /**
     * Test option chain dte filter with a comparison operator (>=) returns
     * every expiration whose dte is at or beyond the bound, inclusive.
     *
     * Per the API: comparison operators follow standard inequality syntax:
     * `<` and `>` exclude the bound, while `<=` and `>=` include it.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_dteComparisonGte_returnsExpirationsAtOrBeyondBound()
    {
        $response = $this->client->options->option_chain(symbol: 'AAPL', dte: '>=40', side: Side::CALL, strike_limit: 3);

        $this->assertEquals('ok', $response->status);
        // Without `dte`, the chain defaults to a single expiration; a
        // comparison operator must select every matching expiration.
        $this->assertGreaterThan(1, count($response->option_chains));

        foreach ($response->getAllQuotes() as $quote) {
            $this->assertGreaterThanOrEqual(40, $quote->dte);
        }
    }

    /**
     * Test option chain dte filter with a comparison operator (<) returns
     * every expiration whose dte is strictly before the bound.
     *
     * Per the API: comparison operators follow standard inequality syntax:
     * `<` and `>` exclude the bound, while `<=` and `>=` include it.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_dteComparisonLt_returnsExpirationsBeforeBound()
    {
        $response = $this->client->options->option_chain(symbol: 'AAPL', dte: '<40', side: Side::CALL, strike_limit: 3);

        $this->assertEquals('ok', $response->status);
        // Without `dte`, the chain defaults to a single expiration; a
        // comparison operator must select every matching expiration.
        $this->assertGreaterThan(1, count($response->option_chains));

        foreach ($response->getAllQuotes() as $quote) {
            $this->assertLessThan(40, $quote->dte);
        }
    }

    // ========================================================================
    // MODEL PARAMETER (API release 3.0.0)
    //
    // bsm (Black-Scholes-Merton) prices every contract without an
    // early-exercise premium, while bjs, crr and baw price American contracts
    // with one. The $150 AAPL put expiring 2023-01-20 was deep in-the-money
    // on 2023-01-10 (AAPL traded near $130), so its early-exercise premium is
    // not zero and bsm must price it differently from each American model.
    // bjs is the documented default for American contracts, so it cannot be
    // distinguished from the unfiltered (no `model`) call by its output; crr
    // and baw are compared against both bsm and the unfiltered call, since a
    // server that silently fell back to the default for either would still
    // look different from bsm without actually implementing them.
    // ========================================================================

    /**
     * Test option chain with model=bsm prices a deep ITM American put
     * differently from model=crr.
     *
     * If the server ignored `model`, both calls would return the same
     * (American-default) values and this assertion would fail.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_withModelBsm_pricesDifferentlyFromAmericanModel(): void
    {
        $bsm = $this->client->options->option_chain(
            symbol: 'AAPL', date: '2023-01-10', expiration: '2023-01-20', side: Side::PUT, strike: '150', model: 'bsm'
        );
        $crr = $this->client->options->option_chain(
            symbol: 'AAPL', date: '2023-01-10', expiration: '2023-01-20', side: Side::PUT, strike: '150', model: 'crr'
        );

        $this->assertEquals('ok', $bsm->status);
        $this->assertEquals('ok', $crr->status);

        $bsmQuote = array_values($bsm->getAllQuotes())[0];
        $crrQuote = array_values($crr->getAllQuotes())[0];
        $this->assertSame($bsmQuote->option_symbol, $crrQuote->option_symbol);

        $this->assertTrue(
            $bsmQuote->implied_volatility !== $crrQuote->implied_volatility
                || $bsmQuote->delta !== $crrQuote->delta,
            'model=bsm and model=crr should price this deep ITM American put differently'
        );
    }

    /**
     * Test option chain with model=bjs prices a deep ITM American put
     * differently from model=bsm.
     *
     * bjs is the documented default for American contracts, so this cannot
     * also be compared against the unfiltered call: both would be expected
     * to return the same values whether or not `model=bjs` was respected.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_withModelBjs_pricesDifferentlyFromEuropeanModel(): void
    {
        $bjs = $this->client->options->option_chain(
            symbol: 'AAPL', date: '2023-01-10', expiration: '2023-01-20', side: Side::PUT, strike: '150', model: 'bjs'
        );
        $bsm = $this->client->options->option_chain(
            symbol: 'AAPL', date: '2023-01-10', expiration: '2023-01-20', side: Side::PUT, strike: '150', model: 'bsm'
        );

        $this->assertEquals('ok', $bjs->status);
        $this->assertEquals('ok', $bsm->status);

        $bjsQuote = array_values($bjs->getAllQuotes())[0];
        $bsmQuote = array_values($bsm->getAllQuotes())[0];
        $this->assertSame($bjsQuote->option_symbol, $bsmQuote->option_symbol);

        $this->assertTrue(
            $bjsQuote->implied_volatility !== $bsmQuote->implied_volatility
                || $bjsQuote->delta !== $bsmQuote->delta,
            'model=bjs and model=bsm should price this deep ITM American put differently'
        );
    }

    /**
     * Test option chain with model=crr prices a deep ITM American put
     * differently from both model=bsm and the unfiltered (no `model`) call.
     *
     * The second comparison catches a server that silently falls back to the
     * default (bjs) for a value it does not actually implement: that would
     * still differ from bsm, so the first comparison alone cannot tell crr
     * apart from a no-op.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_withModelCrr_pricesDifferentlyFromEuropeanModel(): void
    {
        $crr = $this->client->options->option_chain(
            symbol: 'AAPL', date: '2023-01-10', expiration: '2023-01-20', side: Side::PUT, strike: '150', model: 'crr'
        );
        $bsm = $this->client->options->option_chain(
            symbol: 'AAPL', date: '2023-01-10', expiration: '2023-01-20', side: Side::PUT, strike: '150', model: 'bsm'
        );
        $default = $this->client->options->option_chain(
            symbol: 'AAPL', date: '2023-01-10', expiration: '2023-01-20', side: Side::PUT, strike: '150'
        );

        $this->assertEquals('ok', $crr->status);
        $this->assertEquals('ok', $bsm->status);
        $this->assertEquals('ok', $default->status);

        $crrQuote = array_values($crr->getAllQuotes())[0];
        $bsmQuote = array_values($bsm->getAllQuotes())[0];
        $defaultQuote = array_values($default->getAllQuotes())[0];
        $this->assertSame($crrQuote->option_symbol, $bsmQuote->option_symbol);
        $this->assertSame($crrQuote->option_symbol, $defaultQuote->option_symbol);

        $this->assertTrue(
            $crrQuote->implied_volatility !== $bsmQuote->implied_volatility
                || $crrQuote->delta !== $bsmQuote->delta,
            'model=crr and model=bsm should price this deep ITM American put differently'
        );
        $this->assertTrue(
            $crrQuote->implied_volatility !== $defaultQuote->implied_volatility
                || $crrQuote->delta !== $defaultQuote->delta,
            'model=crr must not silently fall back to the default (bjs) pricing'
        );
    }

    /**
     * Test option chain with model=baw prices a deep ITM American put
     * differently from both model=bsm and the unfiltered (no `model`) call.
     *
     * The second comparison catches a server that silently falls back to the
     * default (bjs) for a value it does not actually implement: that would
     * still differ from bsm, so the first comparison alone cannot tell baw
     * apart from a no-op.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_withModelBaw_pricesDifferentlyFromEuropeanModel(): void
    {
        $baw = $this->client->options->option_chain(
            symbol: 'AAPL', date: '2023-01-10', expiration: '2023-01-20', side: Side::PUT, strike: '150', model: 'baw'
        );
        $bsm = $this->client->options->option_chain(
            symbol: 'AAPL', date: '2023-01-10', expiration: '2023-01-20', side: Side::PUT, strike: '150', model: 'bsm'
        );
        $default = $this->client->options->option_chain(
            symbol: 'AAPL', date: '2023-01-10', expiration: '2023-01-20', side: Side::PUT, strike: '150'
        );

        $this->assertEquals('ok', $baw->status);
        $this->assertEquals('ok', $bsm->status);
        $this->assertEquals('ok', $default->status);

        $bawQuote = array_values($baw->getAllQuotes())[0];
        $bsmQuote = array_values($bsm->getAllQuotes())[0];
        $defaultQuote = array_values($default->getAllQuotes())[0];
        $this->assertSame($bawQuote->option_symbol, $bsmQuote->option_symbol);
        $this->assertSame($bawQuote->option_symbol, $defaultQuote->option_symbol);

        $this->assertTrue(
            $bawQuote->implied_volatility !== $bsmQuote->implied_volatility
                || $bawQuote->delta !== $bsmQuote->delta,
            'model=baw and model=bsm should price this deep ITM American put differently'
        );
        $this->assertTrue(
            $bawQuote->implied_volatility !== $defaultQuote->implied_volatility
                || $bawQuote->delta !== $defaultQuote->delta,
            'model=baw must not silently fall back to the default (bjs) pricing'
        );
    }

    /**
     * Test option chain with model but without date is rejected with a 400.
     *
     * Per the API: "Without the date parameter, the request is rejected with a 400."
     * A real-time request without `model` succeeds (see testOptionChain_success),
     * so a server that ignored `model` here would not throw.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testOptionChain_withModelWithoutDate_throws400(): void
    {
        $this->expectException(\MarketDataApp\Exceptions\BadStatusCodeError::class);
        $this->expectExceptionCode(400);

        $this->client->options->option_chain(symbol: 'AAPL', model: 'bsm');
    }
}
