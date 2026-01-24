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

    // ========== Additional Coverage Tests ==========

    public function testFormatVolume_smallNumbers(): void
    {
        // Test volume < 1000 (no suffix)
        $candle = new Candle(100, 100, 100, 100, 500, Carbon::now());
        $output = (string) $candle;
        $this->assertStringContainsString('500', $output);
    }

    public function testOptionQuote_toString_withNullGreeks(): void
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
            last: null,
            volume: 1500,
            open_interest: 15234,
            underlying_price: 245.50,
            in_the_money: true,
            intrinsic_value: 0.00,
            extrinsic_value: 5.275,
            implied_volatility: null,
            delta: null,
            gamma: null,
            theta: null,
            vega: null,
            updated: Carbon::parse('2026-01-24 15:30:00')
        );

        $output = (string) $quote;

        $this->assertStringContainsString('ITM', $output);
        $this->assertStringContainsString('N/A', $output);
    }

    public function testParameters_toString_withDateFormatAndColumns(): void
    {
        $params = new Parameters(
            format: Format::CSV,
            use_human_readable: true,
            date_format: \MarketDataApp\Enums\DateFormat::SPREADSHEET,
            columns: ['open', 'high', 'low', 'close']
        );

        $output = (string) $params;

        $this->assertStringContainsString('date_format=', $output);
        $this->assertStringContainsString('human_readable=true', $output);
        $this->assertStringContainsString('columns=[open,high,low,close]', $output);
    }

    public function testMarketStatuses_toString_truncatesLargeCollections(): void
    {
        // Mock response with 5 dates (more than 3)
        $response = (object) [
            's' => 'ok',
            'date' => [1706054400, 1706140800, 1706227200, 1706313600, 1706400000],
            'status' => ['open', 'closed', 'open', 'open', 'closed'],
        ];

        $statuses = new Statuses($response);
        $output = (string) $statuses;

        $this->assertStringContainsString('5 dates', $output);
        $this->assertStringContainsString('... and 2 more', $output);
    }

    public function testMutualFundCandles_toString_returnsFormattedSummary(): void
    {
        $response = (object) [
            's' => 'ok',
            'o' => [25.50, 25.75, 26.00],
            'h' => [25.75, 26.00, 26.25],
            'l' => [25.25, 25.50, 25.75],
            'c' => [25.60, 25.90, 26.10],
            't' => [1706054400, 1706140800, 1706227200],
        ];

        $candles = new MutualFundCandles($response);
        $output = (string) $candles;

        $this->assertStringContainsString('MutualFunds Candles:', $output);
        $this->assertStringContainsString('3 candles', $output);
        $this->assertStringContainsString('status: ok', $output);
    }

    public function testMutualFundCandles_toString_truncatesLargeCollections(): void
    {
        $response = (object) [
            's' => 'ok',
            'o' => [25.50, 25.75, 26.00, 26.25, 26.50],
            'h' => [25.75, 26.00, 26.25, 26.50, 26.75],
            'l' => [25.25, 25.50, 25.75, 26.00, 26.25],
            'c' => [25.60, 25.90, 26.10, 26.35, 26.55],
            't' => [1706054400, 1706140800, 1706227200, 1706313600, 1706400000],
        ];

        $candles = new MutualFundCandles($response);
        $output = (string) $candles;

        $this->assertStringContainsString('5 candles', $output);
        $this->assertStringContainsString('... and 2 more', $output);
    }

    public function testExpirations_toString_returnsFormattedSummary(): void
    {
        $response = (object) [
            's' => 'ok',
            'expirations' => [1706054400, 1706140800, 1706227200],
            'updated' => 1706122800,
        ];

        $expirations = new Expirations($response);
        $output = (string) $expirations;

        $this->assertStringContainsString('Expirations:', $output);
        $this->assertStringContainsString('3 dates', $output);
    }

    public function testExpirations_toString_truncatesLargeCollections(): void
    {
        $response = (object) [
            's' => 'ok',
            'expirations' => [1706054400, 1706140800, 1706227200, 1706313600, 1706400000, 1706486400, 1706572800],
            'updated' => 1706122800,
        ];

        $expirations = new Expirations($response);
        $output = (string) $expirations;

        $this->assertStringContainsString('7 dates', $output);
        $this->assertStringContainsString('... and 2 more', $output);
    }

    public function testLookup_toString_returnsFormattedString(): void
    {
        $response = (object) [
            's' => 'ok',
            'optionSymbol' => 'AAPL250221C00250000',
        ];

        $lookup = new Lookup($response);
        $output = (string) $lookup;

        $this->assertStringContainsString('Lookup:', $output);
        $this->assertStringContainsString('AAPL250221C00250000', $output);
    }

    public function testOptionChains_toString_returnsFormattedSummary(): void
    {
        // Mock response with one option chain
        $response = (object) [
            's' => 'ok',
            'optionSymbol' => ['AAPL250221C00250000'],
            'underlying' => ['AAPL'],
            'expiration' => [1740096000], // 2025-02-21
            'side' => ['call'],
            'strike' => [250.00],
            'firstTraded' => [1705276800],
            'dte' => [30],
            'ask' => [5.35],
            'askSize' => [150],
            'bid' => [5.20],
            'bidSize' => [100],
            'mid' => [5.275],
            'last' => [5.25],
            'volume' => [1500],
            'openInterest' => [15234],
            'underlyingPrice' => [245.50],
            'inTheMoney' => [false],
            'intrinsicValue' => [0.00],
            'extrinsicValue' => [5.275],
            'iv' => [0.325],
            'delta' => [0.452],
            'gamma' => [0.032],
            'theta' => [-0.085],
            'vega' => [0.21],
            'updated' => [1706122800],
        ];

        $chains = new OptionChains($response);
        $output = (string) $chains;

        $this->assertStringContainsString('Option Chains:', $output);
        $this->assertStringContainsString('1 expiration', $output);
        $this->assertStringContainsString('1 total contract', $output);
        $this->assertStringContainsString('1 calls', $output);
    }

    public function testOptionChains_toString_truncatesLargeCollections(): void
    {
        // Mock response with 4 different expirations
        $response = (object) [
            's' => 'ok',
            'optionSymbol' => ['AAPL250221C00250000', 'AAPL250321C00250000', 'AAPL250421C00250000', 'AAPL250521C00250000'],
            'underlying' => ['AAPL', 'AAPL', 'AAPL', 'AAPL'],
            'expiration' => [1740096000, 1742774400, 1745280000, 1747958400], // Feb, Mar, Apr, May 2025
            'side' => ['call', 'call', 'call', 'call'],
            'strike' => [250.00, 250.00, 250.00, 250.00],
            'firstTraded' => [1705276800, 1705276800, 1705276800, 1705276800],
            'dte' => [30, 58, 89, 119],
            'ask' => [5.35, 6.50, 7.25, 8.00],
            'askSize' => [150, 150, 150, 150],
            'bid' => [5.20, 6.35, 7.10, 7.85],
            'bidSize' => [100, 100, 100, 100],
            'mid' => [5.275, 6.425, 7.175, 7.925],
            'last' => [5.25, 6.40, 7.15, 7.90],
            'volume' => [1500, 1200, 800, 500],
            'openInterest' => [15234, 12000, 8000, 5000],
            'underlyingPrice' => [245.50, 245.50, 245.50, 245.50],
            'inTheMoney' => [false, false, false, false],
            'intrinsicValue' => [0.00, 0.00, 0.00, 0.00],
            'extrinsicValue' => [5.275, 6.425, 7.175, 7.925],
            'iv' => [0.325, 0.320, 0.315, 0.310],
            'delta' => [0.452, 0.480, 0.500, 0.515],
            'gamma' => [0.032, 0.028, 0.025, 0.022],
            'theta' => [-0.085, -0.075, -0.065, -0.055],
            'vega' => [0.21, 0.25, 0.28, 0.30],
            'updated' => [1706122800, 1706122800, 1706122800, 1706122800],
        ];

        $chains = new OptionChains($response);
        $output = (string) $chains;

        $this->assertStringContainsString('4 expirations', $output);
        $this->assertStringContainsString('... and 1 more expiration(s)', $output);
    }

    public function testOptionQuotes_toString_truncatesLargeCollections(): void
    {
        $makeQuote = fn() => new OptionQuote(
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
        );

        $quotes = OptionQuotes::createMerged('ok', [
            $makeQuote(), $makeQuote(), $makeQuote(), $makeQuote(), $makeQuote()
        ]);
        $output = (string) $quotes;

        $this->assertStringContainsString('5 quotes', $output);
        $this->assertStringContainsString('... and 2 more', $output);
    }

    public function testStrikes_toString_returnsFormattedSummary(): void
    {
        $response = (object) [
            's' => 'ok',
            '2025-02-21' => [245.0, 250.0, 255.0],
            '2025-03-21' => [240.0, 245.0, 250.0, 255.0],
        ];

        $strikes = new Strikes($response);
        $output = (string) $strikes;

        $this->assertStringContainsString('Strikes:', $output);
        $this->assertStringContainsString('2 dates', $output);
        $this->assertStringContainsString('7 total strikes', $output);
    }

    public function testStrikes_toString_truncatesLargeCollections(): void
    {
        $response = (object) [
            's' => 'ok',
            '2025-02-21' => [245.0, 250.0],
            '2025-03-21' => [245.0, 250.0],
            '2025-04-21' => [245.0, 250.0],
            '2025-05-21' => [245.0, 250.0],
            '2025-06-21' => [245.0, 250.0],
        ];

        $strikes = new Strikes($response);
        $output = (string) $strikes;

        $this->assertStringContainsString('5 dates', $output);
        $this->assertStringContainsString('... and 2 more date(s)', $output);
    }

    public function testBulkCandles_toString_returnsFormattedSummary(): void
    {
        $response = (object) [
            's' => 'ok',
            'symbol' => ['AAPL', 'AAPL', 'MSFT'],
            'o' => [150.00, 151.00, 400.00],
            'h' => [151.00, 152.00, 405.00],
            'l' => [149.00, 150.00, 398.00],
            'c' => [150.50, 151.50, 402.00],
            'v' => [1000000, 1100000, 500000],
            't' => [1706054400, 1706140800, 1706054400],
        ];

        $candles = new BulkCandles($response);
        $output = (string) $candles;

        $this->assertStringContainsString('BulkCandles:', $output);
        $this->assertStringContainsString('3 candles', $output);
        $this->assertStringContainsString('status: ok', $output);
    }

    public function testBulkCandles_toString_truncatesLargeCollections(): void
    {
        $response = (object) [
            's' => 'ok',
            'symbol' => ['AAPL', 'AAPL', 'AAPL', 'AAPL', 'AAPL'],
            'o' => [150.00, 151.00, 152.00, 153.00, 154.00],
            'h' => [151.00, 152.00, 153.00, 154.00, 155.00],
            'l' => [149.00, 150.00, 151.00, 152.00, 153.00],
            'c' => [150.50, 151.50, 152.50, 153.50, 154.50],
            'v' => [1000000, 1100000, 1200000, 1300000, 1400000],
            't' => [1706054400, 1706140800, 1706227200, 1706313600, 1706400000],
        ];

        $candles = new BulkCandles($response);
        $output = (string) $candles;

        $this->assertStringContainsString('5 candles', $output);
        $this->assertStringContainsString('... and 2 more', $output);
    }

    public function testEarnings_toString_truncatesLargeCollections(): void
    {
        $response = (object) [
            's' => 'ok',
            'symbol' => ['AAPL', 'AAPL', 'AAPL', 'AAPL', 'AAPL'],
            'fiscalYear' => [2024, 2024, 2024, 2024, 2023],
            'fiscalQuarter' => [4, 3, 2, 1, 4],
            'date' => [1735603200, 1727740800, 1719705600, 1711756800, 1704067200],
            'reportDate' => [1737590400, 1729900800, 1721865600, 1713916800, 1706227200],
            'reportTime' => ['after market close', 'after market close', 'after market close', 'after market close', 'after market close'],
            'currency' => ['USD', 'USD', 'USD', 'USD', 'USD'],
            'reportedEPS' => [2.15, 1.95, 1.85, 1.75, 2.10],
            'estimatedEPS' => [2.10, 1.90, 1.80, 1.70, 2.05],
            'surpriseEPS' => [0.05, 0.05, 0.05, 0.05, 0.05],
            'surpriseEPSpct' => [0.0238, 0.0263, 0.0278, 0.0294, 0.0244],
            'updated' => [1737590400, 1729900800, 1721865600, 1713916800, 1706227200],
        ];

        $earnings = new Earnings($response);
        $output = (string) $earnings;

        $this->assertStringContainsString('5 records', $output);
        $this->assertStringContainsString('... and 2 more', $output);
    }

    public function testNews_toString_returnsFormattedString(): void
    {
        $response = (object) [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'headline' => ['Apple Reports Record Q4 Earnings'],
            'content' => ['Apple Inc. today announced financial results for its fiscal 2024 fourth quarter ended September 28, 2024.'],
            'source' => ['https://www.apple.com/newsroom/'],
            'publicationDate' => [1706122800],
        ];

        $news = new News($response);
        $output = (string) $news;

        $this->assertStringContainsString('AAPL:', $output);
        $this->assertStringContainsString('Apple Reports Record Q4 Earnings', $output);
        $this->assertStringContainsString('Published:', $output);
        $this->assertStringContainsString('Source:', $output);
        $this->assertStringContainsString('Content:', $output);
    }

    public function testNews_toString_truncatesLongContent(): void
    {
        $longContent = str_repeat('This is test content. ', 50); // > 200 chars
        $response = (object) [
            's' => 'ok',
            'symbol' => ['AAPL'],
            'headline' => ['Apple Reports Record Q4 Earnings'],
            'content' => [$longContent],
            'source' => ['https://www.apple.com/newsroom/'],
            'publicationDate' => [1706122800],
        ];

        $news = new News($response);
        $output = (string) $news;

        $this->assertStringContainsString('Content:', $output);
        $this->assertStringContainsString('...', $output);
    }

    public function testPrices_toString_truncatesLargeCollections(): void
    {
        $response = (object) [
            's' => 'ok',
            'symbol' => ['AAPL', 'MSFT', 'GOOGL', 'AMZN', 'META'],
            'mid' => [248.75, 420.40, 175.25, 185.50, 510.25],
            'change' => [0.97, 1.25, -0.50, 2.15, -1.75],
            'changepct' => [0.0039, 0.003, -0.0028, 0.0117, -0.0034],
            'updated' => [1706122800, 1706122800, 1706122800, 1706122800, 1706122800],
        ];

        $prices = new Prices($response);
        $output = (string) $prices;

        $this->assertStringContainsString('5 symbols', $output);
        $this->assertStringContainsString('... and 2 more', $output);
    }

    public function testStockQuote_toString_with52WeekRange(): void
    {
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
            '52weekHigh' => [280.50],
            '52weekLow' => [165.25],
        ];

        $quote = new Quote($response);
        $output = (string) $quote;

        $this->assertStringContainsString('52-Week Range:', $output);
        $this->assertStringContainsString('$165.25', $output);
        $this->assertStringContainsString('$280.50', $output);
    }

    public function testStockQuotes_toString_truncatesLargeCollections(): void
    {
        $response = (object) [
            's' => 'ok',
            'symbol' => ['AAPL', 'MSFT', 'GOOGL', 'AMZN', 'META'],
            'ask' => [248.80, 420.50, 175.50, 186.00, 511.00],
            'askSize' => [200, 300, 400, 500, 600],
            'bid' => [248.70, 420.30, 175.00, 185.00, 509.50],
            'bidSize' => [600, 400, 500, 600, 700],
            'mid' => [248.75, 420.40, 175.25, 185.50, 510.25],
            'last' => [248.65, 420.35, 175.10, 185.25, 509.75],
            'change' => [0.97, 1.25, -0.50, 2.15, -1.75],
            'changepct' => [0.0039, 0.003, -0.0028, 0.0117, -0.0034],
            'volume' => [54900000, 32000000, 28000000, 45000000, 38000000],
            'updated' => [1706122800, 1706122800, 1706122800, 1706122800, 1706122800],
        ];

        $quotes = new Quotes($response);
        $output = (string) $quotes;

        $this->assertStringContainsString('5 symbols', $output);
        $this->assertStringContainsString('... and 2 more', $output);
    }

    public function testHeaders_toString_withArrayValue(): void
    {
        $response = (object) [
            'Content-Type' => 'application/json',
            'Accept-Encoding' => ['gzip', 'deflate'],
        ];

        $headers = new Headers($response);
        $output = (string) $headers;

        $this->assertStringContainsString('Accept-Encoding: gzip, deflate', $output);
    }
}
