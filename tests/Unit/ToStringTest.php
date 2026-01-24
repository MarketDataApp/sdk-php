<?php

namespace MarketDataApp\Tests\Unit;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Markets\Status;
use MarketDataApp\Endpoints\Responses\Markets\Statuses;
use MarketDataApp\Endpoints\Responses\MutualFunds\Candle as MutualFundCandle;
use MarketDataApp\Endpoints\Responses\MutualFunds\Candles as MutualFundCandles;
use MarketDataApp\Endpoints\Responses\Options\Expirations;
use MarketDataApp\Endpoints\Responses\Options\Lookup;
use MarketDataApp\Endpoints\Responses\Options\OptionChains;
use MarketDataApp\Endpoints\Responses\Options\OptionQuote;
use MarketDataApp\Endpoints\Responses\Options\Quotes as OptionQuotes;
use MarketDataApp\Endpoints\Responses\Options\Strikes;
use MarketDataApp\Endpoints\Responses\Stocks\BulkCandles;
use MarketDataApp\Endpoints\Responses\Stocks\Candle;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Endpoints\Responses\Stocks\Earning;
use MarketDataApp\Endpoints\Responses\Stocks\Earnings;
use MarketDataApp\Endpoints\Responses\Stocks\News;
use MarketDataApp\Endpoints\Responses\Stocks\Prices;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Endpoints\Responses\Stocks\Quotes;
use MarketDataApp\Endpoints\Responses\Utilities\ApiStatus;
use MarketDataApp\Endpoints\Responses\Utilities\Headers;
use MarketDataApp\Endpoints\Responses\Utilities\ServiceStatus;
use MarketDataApp\Endpoints\Responses\Utilities\User;
use MarketDataApp\Enums\Format;
use MarketDataApp\Enums\Mode;
use MarketDataApp\Enums\Side;
use MarketDataApp\RateLimits;
use PHPUnit\Framework\TestCase;

/**
 * Tests for __toString() methods on all SDK response objects.
 */
class ToStringTest extends TestCase
{
    // ========== Individual Item Classes ==========

    public function testRateLimits_toString_returnsFormattedString(): void
    {
        $rateLimits = new RateLimits(
            limit: 100,
            remaining: 98,
            reset: Carbon::parse('2026-01-24 17:00:00'),
            consumed: 2
        );

        $output = (string) $rateLimits;

        $this->assertStringContainsString('Rate Limits:', $output);
        $this->assertStringContainsString('98', $output);
        $this->assertStringContainsString('100', $output);
        $this->assertStringContainsString('2', $output);
    }

    public function testParameters_toString_defaultFormat(): void
    {
        $params = new Parameters();

        $output = (string) $params;

        $this->assertStringContainsString('Parameters:', $output);
        $this->assertStringContainsString('format=json', $output);
    }

    public function testParameters_toString_withMultipleOptions(): void
    {
        $params = new Parameters(
            format: Format::CSV,
            mode: Mode::LIVE,
            add_headers: true
        );

        $output = (string) $params;

        $this->assertStringContainsString('format=csv', $output);
        $this->assertStringContainsString('mode=live', $output);
        $this->assertStringContainsString('add_headers=true', $output);
    }

    public function testStockCandle_toString_returnsFormattedString(): void
    {
        $candle = new Candle(
            open: 150.25,
            high: 152.50,
            low: 149.00,
            close: 151.75,
            volume: 54900000,
            timestamp: Carbon::parse('2026-01-24')
        );

        $output = (string) $candle;

        $this->assertStringContainsString('Jan 24, 2026', $output);
        $this->assertStringContainsString('$150.25', $output);
        $this->assertStringContainsString('$152.50', $output);
        $this->assertStringContainsString('$149.00', $output);
        $this->assertStringContainsString('$151.75', $output);
        $this->assertStringContainsString('54.9M', $output);
        // Daily candle should NOT show time
        $this->assertStringNotContainsString('AM', $output);
        $this->assertStringNotContainsString('PM', $output);
    }

    public function testStockCandle_toString_intradayShowsTime(): void
    {
        $candle = new Candle(
            open: 150.25,
            high: 152.50,
            low: 149.00,
            close: 151.75,
            volume: 54900000,
            timestamp: Carbon::parse('2026-01-24 09:35:00')
        );

        $output = (string) $candle;

        // Intraday candle should show time
        $this->assertStringContainsString('Jan 24, 2026', $output);
        $this->assertStringContainsString('9:35 AM', $output);
    }

    public function testMutualFundCandle_toString_returnsFormattedString(): void
    {
        $candle = new MutualFundCandle(
            open: 25.50,
            high: 25.75,
            low: 25.25,
            close: 25.60,
            timestamp: Carbon::parse('2026-01-24')
        );

        $output = (string) $candle;

        $this->assertStringContainsString('Jan 24, 2026', $output);
        $this->assertStringContainsString('$25.50', $output);
        $this->assertStringContainsString('$25.75', $output);
    }

    public function testEarning_toString_returnsFormattedString(): void
    {
        $earning = new Earning(
            symbol: 'AAPL',
            fiscal_year: 2024,
            fiscal_quarter: 4,
            date: Carbon::parse('2024-12-31'),
            report_date: Carbon::parse('2025-01-23'),
            report_time: 'after market close',
            currency: 'USD',
            reported_eps: 2.15,
            estimated_eps: 2.10,
            surprise_eps: 0.05,
            surprise_eps_pct: 0.0238,
            updated: Carbon::parse('2025-01-23 14:30:00')
        );

        $output = (string) $earning;

        // All properties must be included
        $this->assertStringContainsString('AAPL', $output);              // symbol
        $this->assertStringContainsString('Q4', $output);                // fiscal_quarter
        $this->assertStringContainsString('2024', $output);              // fiscal_year
        $this->assertStringContainsString('$2.15', $output);             // reported_eps
        $this->assertStringContainsString('$2.10', $output);             // estimated_eps
        $this->assertStringContainsString('Surprise:', $output);         // surprise_eps
        $this->assertStringContainsString('Period End:', $output);       // date label
        $this->assertStringContainsString('Dec 31, 2024', $output);      // date value
        $this->assertStringContainsString('Report:', $output);           // report_date label
        $this->assertStringContainsString('Jan 23, 2025', $output);      // report_date value
        $this->assertStringContainsString('after market close', $output);// report_time
        $this->assertStringContainsString('Currency: USD', $output);     // currency
        $this->assertStringContainsString('Updated:', $output);          // updated label
    }

    public function testEarning_toString_handlesNullEps(): void
    {
        $earning = new Earning(
            symbol: 'AAPL',
            fiscal_year: 2025,
            fiscal_quarter: 1,
            date: Carbon::parse('2025-03-31'),
            report_date: Carbon::parse('2025-04-23'),
            report_time: 'after market close',
            currency: null,
            reported_eps: null,
            estimated_eps: 2.20,
            surprise_eps: null,
            surprise_eps_pct: null,
            updated: Carbon::parse('2025-01-23')
        );

        $output = (string) $earning;

        $this->assertStringContainsString('AAPL', $output);
        $this->assertStringContainsString('N/A', $output);
    }

    public function testMarketStatus_toString_open(): void
    {
        $status = new Status(
            date: Carbon::parse('2026-01-24'),
            status: 'open'
        );

        $output = (string) $status;

        $this->assertStringContainsString('Jan 24, 2026', $output);
        $this->assertStringContainsString('open', $output);
    }

    public function testMarketStatus_toString_nullStatus(): void
    {
        $status = new Status(
            date: Carbon::parse('2026-01-24'),
            status: null
        );

        $output = (string) $status;

        $this->assertStringContainsString('unknown', $output);
    }

    public function testOptionQuote_toString_returnsFormattedString(): void
    {
        $quote = new OptionQuote(
            option_symbol: 'AAPL250221C00250000',
            underlying: 'AAPL',
            expiration: Carbon::parse('2025-02-21'),
            side: Side::CALL,
            strike: 250.00,
            first_traded: Carbon::parse('2024-01-15'),
            dte: 30,
            ask: 5.35,
            ask_size: 150,
            bid: 5.20,
            bid_size: 100,
            mid: 5.275,
            last: 5.25,
            volume: 1500,
            open_interest: 15234,
            underlying_price: 245.50,
            in_the_money: false,
            intrinsic_value: 0.00,
            extrinsic_value: 5.275,
            implied_volatility: 0.325,
            delta: 0.452,
            gamma: 0.032,
            theta: -0.085,
            vega: 0.21,
            updated: Carbon::parse('2026-01-24 15:30:00')
        );

        $output = (string) $quote;

        // All 24 properties must be included
        $this->assertStringContainsString('AAPL250221C00250000', $output);  // option_symbol
        $this->assertStringContainsString('Underlying: AAPL', $output);      // underlying
        $this->assertStringContainsString('Feb 21, 2025', $output);          // expiration
        $this->assertStringContainsString('CALL', $output);                  // side
        $this->assertStringContainsString('$250.00', $output);               // strike
        $this->assertStringContainsString('Jan 15, 2024', $output);          // first_traded
        $this->assertStringContainsString('30 DTE', $output);                // dte
        $this->assertStringContainsString('$5.35', $output);                 // ask
        $this->assertStringContainsString('150', $output);                   // ask_size
        $this->assertStringContainsString('$5.20', $output);                 // bid
        $this->assertStringContainsString('100', $output);                   // bid_size
        $this->assertStringContainsString('Mid:', $output);                  // mid label
        $this->assertStringContainsString('Last:', $output);                 // last label
        $this->assertStringContainsString('Volume:', $output);               // volume
        $this->assertStringContainsString('1,500', $output);                 // volume value
        $this->assertStringContainsString('OI:', $output);                   // open_interest label
        $this->assertStringContainsString('15,234', $output);                // open_interest value
        $this->assertStringContainsString('$245.50', $output);               // underlying_price
        $this->assertStringContainsString('OTM', $output);                   // in_the_money (false = OTM)
        $this->assertStringContainsString('Intrinsic:', $output);            // intrinsic_value label
        $this->assertStringContainsString('Extrinsic:', $output);            // extrinsic_value label
        $this->assertStringContainsString('IV:', $output);                   // implied_volatility
        $this->assertStringContainsString('Delta:', $output);                // delta
        $this->assertStringContainsString('Gamma:', $output);                // gamma
        $this->assertStringContainsString('Theta:', $output);                // theta
        $this->assertStringContainsString('Vega:', $output);                 // vega
        $this->assertStringContainsString('Updated:', $output);              // updated
        $this->assertStringContainsString('First Traded:', $output);         // first_traded label
    }

    public function testServiceStatus_toString_returnsFormattedString(): void
    {
        $status = new ServiceStatus(
            service: 'API',
            status: 'online',
            online: true,
            uptime_percentage_30d: 99.95,
            uptime_percentage_90d: 99.90,
            updated: Carbon::parse('2026-01-24 15:30:00')
        );

        $output = (string) $status;

        // All properties must be included
        $this->assertStringContainsString('API', $output);             // service
        $this->assertStringContainsString('online', $output);          // status
        $this->assertStringContainsString('online: true', $output);    // online boolean
        $this->assertStringContainsString('99.95%', $output);          // uptime_percentage_30d
        $this->assertStringContainsString('99.90%', $output);          // uptime_percentage_90d
        $this->assertStringContainsString('updated:', $output);        // updated label
        $this->assertStringContainsString('Jan 24, 2026', $output);    // updated value
    }

    public function testHeaders_toString_returnsFormattedString(): void
    {
        $response = (object) [
            'Content-Type' => 'application/json',
            'X-Api-RateLimit-Limit' => '100',
        ];

        $headers = new Headers($response);

        $output = (string) $headers;

        $this->assertStringContainsString('Headers:', $output);
        $this->assertStringContainsString('Content-Type', $output);
        $this->assertStringContainsString('application/json', $output);
    }

    public function testUser_toString_delegatesToRateLimits(): void
    {
        $rateLimits = new RateLimits(
            limit: 100,
            remaining: 98,
            reset: Carbon::parse('2026-01-24 17:00:00'),
            consumed: 2
        );

        $user = new User($rateLimits);

        $output = (string) $user;

        $this->assertStringContainsString('User:', $output);
        $this->assertStringContainsString('Rate Limits:', $output);
    }

    // ========== Collection Classes ==========

    public function testCandles_toString_returnsFormattedSummary(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $response = (object) [
            's' => 'ok',
            'o' => [150.00, 151.00, 152.00],
            'h' => [151.00, 152.00, 153.00],
            'l' => [149.00, 150.00, 151.00],
            'c' => [150.50, 151.50, 152.50],
            'v' => [1000000, 1100000, 1200000],
            't' => [1706054400, 1706140800, 1706227200],
        ];

        $candles = new Candles($response);

        $output = (string) $candles;

        $this->assertStringContainsString('Candles:', $output);
        $this->assertStringContainsString('3 candles', $output);
        $this->assertStringContainsString('status: ok', $output);
    }

    public function testCandles_toString_truncatesLargeCollections(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $response = (object) [
            's' => 'ok',
            'o' => [150.00, 151.00, 152.00, 153.00, 154.00],
            'h' => [151.00, 152.00, 153.00, 154.00, 155.00],
            'l' => [149.00, 150.00, 151.00, 152.00, 153.00],
            'c' => [150.50, 151.50, 152.50, 153.50, 154.50],
            'v' => [1000000, 1100000, 1200000, 1300000, 1400000],
            't' => [1706054400, 1706140800, 1706227200, 1706313600, 1706400000],
        ];

        $candles = new Candles($response);

        $output = (string) $candles;

        $this->assertStringContainsString('5 candles', $output);
        $this->assertStringContainsString('... and 2 more', $output);
    }

    public function testCandles_toString_emptyCollection(): void
    {
        $candles = Candles::createMerged('no_data', []);

        $output = (string) $candles;

        $this->assertStringContainsString('0 candles', $output);
        $this->assertStringContainsString('status: no_data', $output);
    }

    public function testStockQuote_toString_returnsFormattedString(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $response = (object) [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'ask' => [248.80],
            'askSize' => [200],
            'bid' => [248.70],
            'bidSize' => [600],
            'mid' => [248.75],
            'last' => [248.65],
            'change' => [0.97],
            'changepct' => [0.0039],
            'volume' => [54900000],
            'updated' => [1706122800],
        ];

        $quote = new Quote($response);

        $output = (string) $quote;

        // All properties must be included
        $this->assertStringContainsString('AAPL', $output);           // symbol
        $this->assertStringContainsString('$248.65', $output);        // last
        $this->assertStringContainsString('+0.39%', $output);         // change_percent
        $this->assertStringContainsString('Change:', $output);        // change label
        $this->assertStringContainsString('Bid:', $output);           // bid
        $this->assertStringContainsString('600', $output);            // bid_size
        $this->assertStringContainsString('Ask:', $output);           // ask
        $this->assertStringContainsString('200', $output);            // ask_size
        $this->assertStringContainsString('Mid:', $output);           // mid
        $this->assertStringContainsString('$248.75', $output);        // mid value
        $this->assertStringContainsString('Volume:', $output);        // volume
        $this->assertStringContainsString('Updated:', $output);       // updated
    }

    public function testStockQuotes_toString_returnsFormattedSummary(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $response = (object) [
            's' => 'ok',
            'symbol' => ['AAPL', 'MSFT'],
            'ask' => [248.80, 420.50],
            'askSize' => [200, 300],
            'bid' => [248.70, 420.30],
            'bidSize' => [600, 400],
            'mid' => [248.75, 420.40],
            'last' => [248.65, 420.35],
            'change' => [0.97, 1.25],
            'changepct' => [0.0039, 0.003],
            'volume' => [54900000, 32000000],
            'updated' => [1706122800, 1706122800],
        ];

        $quotes = new Quotes($response);

        $output = (string) $quotes;

        $this->assertStringContainsString('Quotes:', $output);
        $this->assertStringContainsString('2 symbols', $output);
    }

    public function testEarnings_toString_returnsFormattedSummary(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $response = (object) [
            's' => 'ok',
            'symbol' => ['AAPL', 'AAPL'],
            'fiscalYear' => [2024, 2024],
            'fiscalQuarter' => [4, 3],
            'date' => [1735603200, 1727740800],
            'reportDate' => [1737590400, 1729900800],
            'reportTime' => ['after market close', 'after market close'],
            'currency' => ['USD', 'USD'],
            'reportedEPS' => [2.15, 1.95],
            'estimatedEPS' => [2.10, 1.90],
            'surpriseEPS' => [0.05, 0.05],
            'surpriseEPSpct' => [0.0238, 0.0263],
            'updated' => [1737590400, 1729900800],
        ];

        $earnings = new Earnings($response);

        $output = (string) $earnings;

        $this->assertStringContainsString('Earnings:', $output);
        $this->assertStringContainsString('2 records', $output);
        $this->assertStringContainsString('status: ok', $output);
    }

    public function testOptionQuotes_toString_returnsFormattedSummary(): void
    {
        $quotes = OptionQuotes::createMerged('ok', [
            new OptionQuote(
                option_symbol: 'AAPL250221C00250000',
                underlying: 'AAPL',
                expiration: Carbon::parse('2025-02-21'),
                side: Side::CALL,
                strike: 250.00,
                first_traded: Carbon::parse('2024-01-15'),
                dte: 30,
                ask: 5.35,
                ask_size: 150,
                bid: 5.20,
                bid_size: 100,
                mid: 5.275,
                last: 5.25,
                volume: 1500,
                open_interest: 15234,
                underlying_price: 245.50,
                in_the_money: false,
                intrinsic_value: 0.00,
                extrinsic_value: 5.275,
                implied_volatility: 0.325,
                delta: 0.452,
                gamma: 0.032,
                theta: -0.085,
                vega: 0.21,
                updated: Carbon::parse('2026-01-24')
            ),
        ]);

        $output = (string) $quotes;

        $this->assertStringContainsString('Option Quotes:', $output);
        $this->assertStringContainsString('1 quote', $output);
        $this->assertStringContainsString('AAPL', $output);
        $this->assertStringContainsString('CALL', $output);
    }

    public function testMarketStatuses_toString_returnsFormattedSummary(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $response = (object) [
            's' => 'ok',
            'date' => [1706054400, 1706140800],
            'status' => ['open', 'closed'],
        ];

        $statuses = new Statuses($response);

        $output = (string) $statuses;

        $this->assertStringContainsString('Market Statuses:', $output);
        $this->assertStringContainsString('2 dates', $output);
    }

    public function testApiStatus_toString_returnsFormattedSummary(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $response = (object) [
            's' => 'ok',
            'service' => ['API', 'Website'],
            'status' => ['online', 'online'],
            'online' => [true, true],
            'uptimePct30d' => [99.95, 99.99],
            'uptimePct90d' => [99.90, 99.95],
            'updated' => [1706122800, 1706122800],
        ];

        $status = new ApiStatus($response);

        $output = (string) $status;

        $this->assertStringContainsString('API Status:', $output);
        $this->assertStringContainsString('2 services', $output);
        $this->assertStringContainsString('API:', $output);
        $this->assertStringContainsString('Website:', $output);
    }

    // ========== FormatsForDisplay Trait Tests ==========

    public function testFormatVolume_thousands(): void
    {
        $candle = new Candle(100, 100, 100, 100, 1500, Carbon::now());
        $output = (string) $candle;
        $this->assertStringContainsString('1.5K', $output);
    }

    public function testFormatVolume_millions(): void
    {
        $candle = new Candle(100, 100, 100, 100, 2500000, Carbon::now());
        $output = (string) $candle;
        $this->assertStringContainsString('2.5M', $output);
    }

    public function testFormatVolume_billions(): void
    {
        $candle = new Candle(100, 100, 100, 100, 1500000000, Carbon::now());
        $output = (string) $candle;
        $this->assertStringContainsString('1.5B', $output);
    }

    public function testFormatPercent_positive(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $response = (object) [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'mid' => [248.75],
            'change' => [0.97],
            'changepct' => [0.0325], // 3.25%
            'updated' => [1706122800],
        ];

        $prices = new Prices($response);

        $output = (string) $prices;

        $this->assertStringContainsString('+3.25%', $output);
    }

    public function testFormatPercent_negative(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $response = (object) [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'mid' => [248.75],
            'change' => [-0.97],
            'changepct' => [-0.0150], // -1.50%
            'updated' => [1706122800],
        ];

        $prices = new Prices($response);

        $output = (string) $prices;

        $this->assertStringContainsString('-1.50%', $output);
    }
}
