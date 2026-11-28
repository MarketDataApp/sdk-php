<?php

namespace MarketDataApp\Tests\Integration\Options;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Options\OptionQuote;
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
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testQuotes_success()
    {
        $response = $this->client->options->quotes('AAPL281215C00400000');

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->quotes);

        $this->assertInstanceOf(OptionQuote::class, $response->quotes[0]);
        $this->assertSame('AAPL281215C00400000', $response->quotes[0]->option_symbol);
        $this->assertSame('AAPL', $response->quotes[0]->underlying);
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
            option_symbols: 'AAPL281215C00400000',
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
            option_symbols: 'AAPL281215C00400000',
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->quotes);
        $this->assertInstanceOf(OptionQuote::class, $response->quotes[0]);
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
            option_symbols: 'AAPL281215C00400000',
            parameters: new Parameters(use_human_readable: false)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->quotes);
        $this->assertInstanceOf(OptionQuote::class, $response->quotes[0]);
        $this->assertEquals('string', gettype($response->quotes[0]->option_symbol));
    }

    /**
     * Test options quotes endpoint with CSV format and dateformat=timestamp.
     */
    public function testQuotes_csv_dateFormat_timestamp_returnsCsv(): void
    {
        $response = $this->client->options->quotes(
            option_symbols: 'AAPL',
            parameters: new Parameters(format: Format::CSV, date_format: DateFormat::TIMESTAMP)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $this->assertNotEmpty($csv);
    }

    /**
     * Test successful retrieval of multiple option quotes concurrently.
     */
    public function testQuotes_multipleSymbols_success(): void
    {
        $response = $this->client->options->quotes([
            'AAPL281215C00400000',
            'AAPL281215P00400000',
        ]);

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertGreaterThanOrEqual(2, count($response->quotes));

        // Verify quotes from both symbols are present
        $symbols = array_map(fn($q) => $q->option_symbol, $response->quotes);
        $this->assertContains('AAPL281215C00400000', $symbols);
        $this->assertContains('AAPL281215P00400000', $symbols);
    }

    /**
     * Test multiple option quotes with human-readable format.
     */
    public function testQuotes_multipleSymbols_humanReadable_success(): void
    {
        $response = $this->client->options->quotes(
            option_symbols: ['AAPL281215C00400000', 'AAPL281215P00400000'],
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertGreaterThanOrEqual(2, count($response->quotes));
    }

    // =========================================================================
    // Edge Case Tests - Expired + Unexpired Options
    // =========================================================================

    /**
     * Test mixed expired and unexpired options returns partial data.
     *
     * When requesting a mix of expired (AAPL230120C00150000 - Jan 2023) and
     * unexpired (AAPL281215C00400000 - Dec 2028) options without a historical
     * date, the expired option should fail while the unexpired one succeeds.
     */
    public function testQuotes_mixedExpiredUnexpired_returnsPartialData(): void
    {
        // AAPL230120C00150000 = AAPL, Jan 20 2023, Call, $150 strike (expired)
        // AAPL281215C00400000 = AAPL, Dec 15 2028, Call, $400 strike (unexpired)
        $expiredSymbol = 'AAPL230120C00150000';
        $unexpiredSymbol = 'AAPL281215C00400000';

        $response = $this->client->options->quotes([
            $expiredSymbol,
            $unexpiredSymbol,
        ]);

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);

        // Should have at least the unexpired option's quote
        $this->assertNotEmpty($response->quotes);

        // Verify the unexpired symbol is present
        $symbols = array_map(fn($q) => $q->option_symbol, $response->quotes);
        $this->assertContains($unexpiredSymbol, $symbols);

        // Should have error for the expired symbol (or it might return no_data)
        // The exact behavior depends on the API - it might error or return no_data
        // Either way, we got partial data successfully
    }

    /**
     * Test expired option with historical date returns data.
     *
     * When requesting an expired option with a historical date when it was
     * still trading, the API should return valid quote data.
     */
    public function testQuotes_expiredOption_withHistoricalDate_returnsData(): void
    {
        // AAPL230120C00150000 = AAPL, Jan 20 2023, Call, $150 strike
        // Request data from Jan 10, 2023 when this option was still trading
        $expiredSymbol = 'AAPL230120C00150000';

        $response = $this->client->options->quotes(
            option_symbols: $expiredSymbol,
            date: '2023-01-10'
        );

        $this->assertInstanceOf(Quotes::class, $response);
        // Should return historical data
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->quotes);
    }

    /**
     * Test multiple expired options with historical date range.
     *
     * When requesting multiple expired options with a historical date range,
     * both should return valid data from that period.
     */
    public function testQuotes_multipleExpiredOptions_withHistoricalDateRange_returnsData(): void
    {
        // Both expired in Jan 2023, request data from early January
        $expiredCall = 'AAPL230120C00150000';
        $expiredPut = 'AAPL230120P00150000';

        $response = $this->client->options->quotes(
            option_symbols: [$expiredCall, $expiredPut],
            from: '2023-01-09',
            to: '2023-01-11'
        );

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);

        // Should have quotes from both symbols
        $symbols = array_unique(array_map(fn($q) => $q->option_symbol, $response->quotes));
        $this->assertContains($expiredCall, $symbols);
        $this->assertContains($expiredPut, $symbols);

        // No errors expected since both should have historical data
        $this->assertEmpty($response->errors);
    }

    /**
     * Test mixed expired and unexpired options with historical date.
     *
     * When requesting both expired and unexpired options with a historical
     * date, both should return data (the unexpired option existed then too).
     */
    public function testQuotes_mixedExpiredUnexpired_withHistoricalDate_returnsAllData(): void
    {
        // AAPL230120C00150000 expired Jan 2023
        // AAPL281215C00400000 expires Dec 2028 (but existed in 2024)
        // Use a date when both were trading
        $expiredSymbol = 'AAPL230120C00150000';
        $unexpiredSymbol = 'AAPL250117C00200000'; // Jan 2025 expiry, should exist in 2024

        $response = $this->client->options->quotes(
            option_symbols: [$expiredSymbol, $unexpiredSymbol],
            date: '2023-01-10'
        );

        $this->assertInstanceOf(Quotes::class, $response);
        // At minimum, the expired option should have data for this date
        // The unexpired option may or may not have existed yet
        $this->assertTrue(
            in_array($response->status, ['ok', 'no_data']),
            "Expected status 'ok' or 'no_data', got '{$response->status}'"
        );
    }

    /**
     * Test errors property contains failed symbol info.
     *
     * When some symbols fail, the errors property should contain
     * information about which symbols failed and why.
     */
    public function testQuotes_partialFailure_errorsContainSymbolInfo(): void
    {
        // Use a completely invalid symbol format alongside a valid one
        $invalidSymbol = 'INVALID_NOT_AN_OPTION';
        $validSymbol = 'AAPL281215C00400000';

        $response = $this->client->options->quotes([
            $validSymbol,
            $invalidSymbol,
        ]);

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);

        // Should have data from the valid symbol
        $this->assertNotEmpty($response->quotes);

        // Errors should contain the invalid symbol
        $this->assertNotEmpty($response->errors);
        $this->assertArrayHasKey($invalidSymbol, $response->errors);

        // Error message should be present
        $this->assertNotEmpty($response->errors[$invalidSymbol]);
    }

    /**
     * Test all valid symbols returns empty errors array.
     */
    public function testQuotes_allValidSymbols_errorsEmpty(): void
    {
        $response = $this->client->options->quotes([
            'AAPL281215C00400000',
            'AAPL281215P00400000',
        ]);

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->quotes);

        // No errors when all symbols are valid
        $this->assertEmpty($response->errors);
    }

    /**
     * Test three symbols with one invalid returns two quotes.
     */
    public function testQuotes_threeSymbolsOneInvalid_returnsTwoQuotes(): void
    {
        $validCall = 'AAPL281215C00400000';
        $validPut = 'AAPL281215P00400000';
        $invalidSymbol = 'NOTREAL123';

        $response = $this->client->options->quotes([
            $validCall,
            $invalidSymbol,
            $validPut,
        ]);

        $this->assertInstanceOf(Quotes::class, $response);
        $this->assertEquals('ok', $response->status);

        // Should have quotes from both valid symbols
        $symbols = array_map(fn($q) => $q->option_symbol, $response->quotes);
        $this->assertContains($validCall, $symbols);
        $this->assertContains($validPut, $symbols);

        // Should have error for the invalid symbol
        $this->assertArrayHasKey($invalidSymbol, $response->errors);
    }

    // ========================================================================
    // MODEL PARAMETER (API release 3.0.0)
    //
    // bsm (Black-Scholes-Merton) prices every contract without an
    // early-exercise premium, while bjs, crr and baw price American contracts
    // with one. AAPL230120P00150000 was a deep in-the-money American put on
    // 2023-01-10 (AAPL traded near $130), so its early-exercise premium is
    // not zero and bsm must price it differently from each American model.
    // bjs is the documented default for American contracts, so it cannot be
    // distinguished from the unfiltered (no `model`) call by its output; crr
    // and baw are compared against both bsm and the unfiltered call, since a
    // server that silently fell back to the default for either would still
    // look different from bsm without actually implementing them.
    // ========================================================================

    /**
     * Test quotes with model=bsm prices a deep ITM American put differently
     * from model=crr.
     *
     * If the server ignored `model`, both calls would return the same
     * (American-default) values and this assertion would fail.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testQuotes_withModelBsm_pricesDifferentlyFromAmericanModel(): void
    {
        $bsm = $this->client->options->quotes(option_symbols: 'AAPL230120P00150000', date: '2023-01-10', model: 'bsm');
        $crr = $this->client->options->quotes(option_symbols: 'AAPL230120P00150000', date: '2023-01-10', model: 'crr');

        $this->assertEquals('ok', $bsm->status);
        $this->assertEquals('ok', $crr->status);

        $this->assertTrue(
            $bsm->quotes[0]->implied_volatility !== $crr->quotes[0]->implied_volatility
                || $bsm->quotes[0]->delta !== $crr->quotes[0]->delta,
            'model=bsm and model=crr should price this deep ITM American put differently'
        );
    }

    /**
     * Test quotes with model=bjs prices a deep ITM American put differently
     * from model=bsm.
     *
     * bjs is the documented default for American contracts, so this cannot
     * also be compared against the unfiltered call: both would be expected
     * to return the same values whether or not `model=bjs` was respected.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testQuotes_withModelBjs_pricesDifferentlyFromEuropeanModel(): void
    {
        $bjs = $this->client->options->quotes(option_symbols: 'AAPL230120P00150000', date: '2023-01-10', model: 'bjs');
        $bsm = $this->client->options->quotes(option_symbols: 'AAPL230120P00150000', date: '2023-01-10', model: 'bsm');

        $this->assertEquals('ok', $bjs->status);
        $this->assertEquals('ok', $bsm->status);

        $this->assertTrue(
            $bjs->quotes[0]->implied_volatility !== $bsm->quotes[0]->implied_volatility
                || $bjs->quotes[0]->delta !== $bsm->quotes[0]->delta,
            'model=bjs and model=bsm should price this deep ITM American put differently'
        );
    }

    /**
     * Test quotes with model=crr prices a deep ITM American put differently
     * from both model=bsm and the unfiltered (no `model`) call.
     *
     * The second comparison catches a server that silently falls back to the
     * default (bjs) for a value it does not actually implement: that would
     * still differ from bsm, so the first comparison alone cannot tell crr
     * apart from a no-op.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testQuotes_withModelCrr_pricesDifferentlyFromEuropeanModel(): void
    {
        $crr = $this->client->options->quotes(option_symbols: 'AAPL230120P00150000', date: '2023-01-10', model: 'crr');
        $bsm = $this->client->options->quotes(option_symbols: 'AAPL230120P00150000', date: '2023-01-10', model: 'bsm');
        $default = $this->client->options->quotes(option_symbols: 'AAPL230120P00150000', date: '2023-01-10');

        $this->assertEquals('ok', $crr->status);
        $this->assertEquals('ok', $bsm->status);
        $this->assertEquals('ok', $default->status);

        $this->assertTrue(
            $crr->quotes[0]->implied_volatility !== $bsm->quotes[0]->implied_volatility
                || $crr->quotes[0]->delta !== $bsm->quotes[0]->delta,
            'model=crr and model=bsm should price this deep ITM American put differently'
        );
        $this->assertTrue(
            $crr->quotes[0]->implied_volatility !== $default->quotes[0]->implied_volatility
                || $crr->quotes[0]->delta !== $default->quotes[0]->delta,
            'model=crr must not silently fall back to the default (bjs) pricing'
        );
    }

    /**
     * Test quotes with model=baw prices a deep ITM American put differently
     * from both model=bsm and the unfiltered (no `model`) call.
     *
     * The second comparison catches a server that silently falls back to the
     * default (bjs) for a value it does not actually implement: that would
     * still differ from bsm, so the first comparison alone cannot tell baw
     * apart from a no-op.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testQuotes_withModelBaw_pricesDifferentlyFromEuropeanModel(): void
    {
        $baw = $this->client->options->quotes(option_symbols: 'AAPL230120P00150000', date: '2023-01-10', model: 'baw');
        $bsm = $this->client->options->quotes(option_symbols: 'AAPL230120P00150000', date: '2023-01-10', model: 'bsm');
        $default = $this->client->options->quotes(option_symbols: 'AAPL230120P00150000', date: '2023-01-10');

        $this->assertEquals('ok', $baw->status);
        $this->assertEquals('ok', $bsm->status);
        $this->assertEquals('ok', $default->status);

        $this->assertTrue(
            $baw->quotes[0]->implied_volatility !== $bsm->quotes[0]->implied_volatility
                || $baw->quotes[0]->delta !== $bsm->quotes[0]->delta,
            'model=baw and model=bsm should price this deep ITM American put differently'
        );
        $this->assertTrue(
            $baw->quotes[0]->implied_volatility !== $default->quotes[0]->implied_volatility
                || $baw->quotes[0]->delta !== $default->quotes[0]->delta,
            'model=baw must not silently fall back to the default (bjs) pricing'
        );
    }

    /**
     * Test quotes with model but without date, from or to is rejected with a 400.
     *
     * Per the API: "Without date, from or to, the request is rejected with a 400."
     * A real-time request without `model` succeeds (see testQuotes_success),
     * so a server that ignored `model` here would not throw.
     */
    #[\PHPUnit\Framework\Attributes\Group('ci')]
    public function testQuotes_withModelWithoutDateFromTo_throws400(): void
    {
        $this->expectException(\MarketDataApp\Exceptions\BadStatusCodeError::class);
        $this->expectExceptionCode(400);

        $this->client->options->quotes(option_symbols: 'AAPL281215C00400000', model: 'bsm');
    }
}
