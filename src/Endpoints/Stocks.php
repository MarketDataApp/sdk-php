<?php

namespace MarketDataApp\Endpoints;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Responses\Stocks\BulkCandles;
use MarketDataApp\Endpoints\Responses\Stocks\BulkQuotes;
use MarketDataApp\Endpoints\Responses\Stocks\Candles;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Endpoints\Responses\Stocks\Quotes;
use MarketDataApp\Exceptions\ApiException;

class Stocks
{

    private Client $client;
    public const BASE_URL = "v1/stocks/";

    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * Get bulk candle data for stocks. This endpoint returns bulk daily candle data for multiple stocks. Unlike the
     * standard candles endpoint, this endpoint returns a single daily for each symbol provided. The typical use-case
     * for this endpoint is to get a complete market snapshot during trading hours, though it can also be used for bulk
     * snapshots of historical daily candles.
     *
     * @param array $symbols The ticker symbols to return in the response, separated by commas. The symbols parameter
     * may be omitted if the snapshot parameter is set to true.
     *
     * @param string $resolution The duration of each candle. Only daily candles are supported at this time.
     * Daily Resolutions: (daily, D, 1D, 2D, ...)
     *
     * @param bool $snapshot Returns candles for all available symbols for the date indicated. The symbols parameter can
     * be omitted if snapshot is set to true.
     *
     * @param Carbon|null $date The date of the candles to be returned. If no date is specified, during market hours the
     * candles returned will be from the current session. If the market is closed the candles will be from the most
     * recent session.
     *
     * @param bool $adjustsplits Adjust historical data for historical splits and reverse splits. Market Data uses
     * the CRSP methodology for adjustment. Daily candles default: true.
     *
     * @return BulkCandles
     * @throws ApiException
     * @throws GuzzleException
     */
    public function bulkCandles(
        array $symbols = [],
        string $resolution = 'D',
        bool $snapshot = false,
        Carbon $date = null,
        bool $adjustsplits = false,
    ): BulkCandles {
        if (empty($symbols) && !$snapshot) {
            throw new \InvalidArgumentException('Either symbols or snapshot must be set');
        }

        $symbols = implode(',', array_map('trim', $symbols));
        return new BulkCandles($this->client->execute(self::BASE_URL . "bulkcandles/{$resolution}/",
            compact('symbols', 'date', 'snapshot', 'adjustsplits')
        ));
    }

    /**
     * Get historical price candles for an index.
     *
     * @param string $symbol The company's ticker symbol.
     * @param Carbon $from The leftmost candle on a chart (inclusive). If you use countback, to is not required.
     * @param Carbon|null $to The rightmost candle on a chart (inclusive).
     * @param string $resolution The duration of each candle.
     * Minutely Resolutions: (minutely, 1, 3, 5, 15, 30, 45, ...) Hourly Resolutions: (hourly, H, 1H, 2H, ...)
     * Daily Resolutions: (daily, D, 1D, 2D, ...)
     * Weekly Resolutions: (weekly, W, 1W, 2W, ...)
     * Monthly Resolutions: (monthly, M, 1M, 2M, ...)
     * Yearly Resolutions:(yearly, Y, 1Y, 2Y, ...)
     *
     * @param int|null $countback Will fetch a number of candles before (to the left of) to. If you use from, countback
     * is not required.
     *
     * @param string|null $exchange Use to specify the exchange of the ticker. This is useful when you need to specify
     *  a stock that quotes on several exchanges with the same symbol. You may specify the exchange using the EXCHANGE
     * ACRONYM, MIC CODE, or two digit YAHOO FINANCE EXCHANGE CODE. If no exchange is specified symbols will be matched
     * to US exchanges first.
     *
     * @param bool $extended Include extended hours trading sessions when returning intraday candles. Daily resolutions
     * never return extended hours candles. The default is false.
     *
     * @param string|null $country Use to specify the country of the exchange (not the country of the company) in
     * conjunction with the symbol argument. This argument is useful when you know the ticker symbol and the country of
     * the exchange, but not the exchange code. Use the two digit ISO 3166 country code. If no country is specified, US
     * exchanges will be assumed.
     *
     * @param bool $adjustsplits Adjust historical data for for historical splits and reverse splits. Market Data uses
     * the CRSP methodology for adjustment. Daily candles default: true. Intraday candles default: false.
     *
     * @param bool $adjustdividends CAUTION: Adjusted dividend data is planned for the future, but not yet implemented.
     * All data is currently returned unadjusted for dividends. Market Data uses the CRSP methodology for adjustment.
     * Daily candles default: true. Intraday candles default: false.
     *
     * @return Candles
     * @throws GuzzleException|ApiException
     */
    public function candles(
        string $symbol,
        Carbon $from,
        Carbon $to = null,
        string $resolution = 'D',
        int $countback = null,
        string $exchange = null,
        bool $extended = false,
        string $country = null,
        bool $adjustsplits = false,
        bool $adjustdividends = false,
    ): Candles {
        return new Candles($this->client->execute(self::BASE_URL . "candles/{$resolution}/{$symbol}/",
            compact('from', 'to', 'countback', 'exchange', 'extended', 'country', 'adjustsplits', 'adjustdividends')
        ));
    }

    /**
     * Get a real-time price quote for a stock.
     *
     * @param string $symbol The company's ticker symbol.
     * @param bool $fifty_two_week Enable the output of 52-week high and 52-week low data in the quote output. By
     * default this parameter is false if omitted.
     *
     * @throws GuzzleException|ApiException
     */
    public function quote(string $symbol, bool $fifty_two_week = false): Quote
    {
        return new Quote($this->client->execute(self::BASE_URL . "quotes/{$symbol}",
            ['52week' => $fifty_two_week]));
    }

    /**
     * Get a real-time price quote for a multiple stocks by doing parallel requests.
     *
     * @param array $symbols The ticker symbols to return in the response.
     * @param bool $fifty_two_week Enable the output of 52-week high and 52-week low data in the quote output. By
     * default this parameter is false if omitted.
     *
     * @throws \Throwable
     */
    public function quotes(array $symbols, bool $fifty_two_week = false): Quotes|BulkQuotes
    {
        // Execute standard quotes in parallel
        $calls = [];
        foreach ($symbols as $symbol) {
            $calls[] = ["/stocks/quotes/$symbol", ['52week' => $fifty_two_week]];
        }

        return new Quotes($this->client->executeInParallel($calls));
    }

    /**
     * Get a real-time price quote for a multiple stocks in a single API request.
     *
     * The bulkquotes endpoint is designed to return hundreds of symbols at once or full market snapshots. Response
     * times for less than 50 symbols will be quicker using the standard quotes endpoint and sending your requests in
     * parallel.
     *
     * @param array $symbols The ticker symbols to return in the response, separated by commas. The symbols parameter
     * may be omitted if the snapshot parameter is set to true.
     *
     * @param bool $snapshot Returns a full market snapshot with quotes for all symbols when set to true. The symbols
     * parameter may be omitted if the snapshot parameter is set.
     *
     * @throws GuzzleException
     * @throws \Exception
     */
    public function bulkQuotes(array $symbols = [], bool $snapshot = false): BulkQuotes
    {
        if (empty($symbols) && !$snapshot) {
            throw new \InvalidArgumentException('Either symbols or snapshot must be set');
        }

        return new BulkQuotes($this->client->execute(self::BASE_URL . "bulkquotes",
            ['symbols' => implode(',', $symbols), 'snapshot' => $snapshot]));
    }
}
