<?php

namespace MarketDataApp\Endpoints\Responses\Options;

use Carbon\Carbon;
use MarketDataApp\Endpoints\Responses\ResponseBase;
use MarketDataApp\Enums\Side;

/**
 * Represents a collection of option chains with associated data.
 */
class OptionChains extends ResponseBase
{

    /**
     * Status of the option chains request. Will always be ok when there is the quote requested.
     *
     * @var string
     */
    public string $status = 'no_data';

    /**
     * Time of the next quote if there is no data in the requested period, but there is data in a subsequent period.
     *
     * @var Carbon|null
     */
    public ?Carbon $next_time = null;

    /**
     * Time of the previous quote if there is no data in the requested period, but there is data in a previous period.
     *
     * @var Carbon|null
     */
    public ?Carbon $prev_time = null;

    /**
     * Multidimensional array of OptionQuote objects organized by date.
     *
     * @var array<string, OptionQuote[]>
     */
    public array $option_chains = [];

    /**
     * Constructs a new OptionChains instance from the given response object.
     *
     * @param object $response The response object containing option chains data.
     */
    public function __construct(object $response)
    {
        parent::__construct($response);
        if (!$this->isJson()) {
            return;
        }

        // Convert to array for easier access to keys with spaces (human-readable format)
        $responseArray = (array) $response;

        // Determine if this is human-readable format (has "Symbol" key) or regular format (has "s" status)
        $isHumanReadable = isset($responseArray['Symbol']);

        // Convert the response to this object.
        if ($isHumanReadable) {
            // Human-readable format - no "s" status field, always has data when successful
            $this->status = 'ok';

            // Use minimum array length across all required fields to prevent out-of-bounds access
            $count = min(
                count($responseArray['Symbol'] ?? []),
                count($responseArray['Underlying'] ?? []),
                count($responseArray['Expiration Date'] ?? []),
                count($responseArray['Option Side'] ?? []),
                count($responseArray['Strike'] ?? []),
                count($responseArray['First Traded'] ?? []),
                count($responseArray['Days To Expiration'] ?? []),
                count($responseArray['Ask'] ?? []),
                count($responseArray['Ask Size'] ?? []),
                count($responseArray['Bid'] ?? []),
                count($responseArray['Bid Size'] ?? []),
                count($responseArray['Mid'] ?? []),
                count($responseArray['Volume'] ?? []),
                count($responseArray['Open Interest'] ?? []),
                count($responseArray['Underlying Price'] ?? []),
                count($responseArray['In The Money'] ?? []),
                count($responseArray['Intrinsic Value'] ?? []),
                count($responseArray['Extrinsic Value'] ?? []),
                count($responseArray['Date'] ?? [])
            );
            for ($i = 0; $i < $count; $i++) {
                $expiration = Carbon::parse($responseArray['Expiration Date'][$i]);
                $this->option_chains[$expiration->toDateString()][] = new OptionQuote(
                    option_symbol: $responseArray['Symbol'][$i],
                    underlying: $responseArray['Underlying'][$i],
                    expiration: $expiration,
                    side: Side::from($responseArray['Option Side'][$i]),
                    strike: $responseArray['Strike'][$i],
                    first_traded: Carbon::parse($responseArray['First Traded'][$i]),
                    dte: $responseArray['Days To Expiration'][$i],
                    ask: $responseArray['Ask'][$i],
                    ask_size: $responseArray['Ask Size'][$i],
                    bid: $responseArray['Bid'][$i],
                    bid_size: $responseArray['Bid Size'][$i],
                    mid: $responseArray['Mid'][$i],
                    last: $responseArray['Last'][$i] ?? null,
                    volume: $responseArray['Volume'][$i],
                    open_interest: $responseArray['Open Interest'][$i],
                    underlying_price: $responseArray['Underlying Price'][$i],
                    in_the_money: $responseArray['In The Money'][$i],
                    intrinsic_value: $responseArray['Intrinsic Value'][$i],
                    extrinsic_value: $responseArray['Extrinsic Value'][$i],
                    implied_volatility: $responseArray['IV'][$i] ?? null,
                    delta: $responseArray['Delta'][$i] ?? null,
                    gamma: $responseArray['Gamma'][$i] ?? null,
                    theta: $responseArray['Theta'][$i] ?? null,
                    vega: $responseArray['Vega'][$i] ?? null,
                    updated: Carbon::parse($responseArray['Date'][$i]),
                );
            }
        } else {
            // Regular format
            $this->status = $response->s;

            switch ($this->status) {
                case 'ok':
                    // Use minimum array length across all required fields to prevent out-of-bounds access
                    $count = min(
                        count($response->optionSymbol ?? []),
                        count($response->underlying ?? []),
                        count($response->expiration ?? []),
                        count($response->side ?? []),
                        count($response->strike ?? []),
                        count($response->firstTraded ?? []),
                        count($response->dte ?? []),
                        count($response->ask ?? []),
                        count($response->askSize ?? []),
                        count($response->bid ?? []),
                        count($response->bidSize ?? []),
                        count($response->mid ?? []),
                        count($response->volume ?? []),
                        count($response->openInterest ?? []),
                        count($response->underlyingPrice ?? []),
                        count($response->inTheMoney ?? []),
                        count($response->intrinsicValue ?? []),
                        count($response->extrinsicValue ?? []),
                        count($response->updated ?? [])
                    );
                    for ($i = 0; $i < $count; $i++) {
                        $expiration = Carbon::parse($response->expiration[$i]);
                        $this->option_chains[$expiration->toDateString()][] = new OptionQuote(
                            option_symbol: $response->optionSymbol[$i],
                            underlying: $response->underlying[$i],
                            expiration: $expiration,
                            side: Side::from($response->side[$i]),
                            strike: $response->strike[$i],
                            first_traded: Carbon::parse($response->firstTraded[$i]),
                            dte: $response->dte[$i],
                            ask: $response->ask[$i],
                            ask_size: $response->askSize[$i],
                            bid: $response->bid[$i],
                            bid_size: $response->bidSize[$i],
                            mid: $response->mid[$i],
                            last: ($response->last ?? null) === null ? null : $response->last[$i],
                            volume: $response->volume[$i],
                            open_interest: $response->openInterest[$i],
                            underlying_price: $response->underlyingPrice[$i],
                            in_the_money: $response->inTheMoney[$i],
                            intrinsic_value: $response->intrinsicValue[$i],
                            extrinsic_value: $response->extrinsicValue[$i],
                            implied_volatility: ($response->iv ?? null) === null ? null : $response->iv[$i],
                            delta: ($response->delta ?? null) === null ? null : $response->delta[$i],
                            gamma: ($response->gamma ?? null) === null ? null : $response->gamma[$i],
                            theta: ($response->theta ?? null) === null ? null : $response->theta[$i],
                            vega: ($response->vega ?? null) === null ? null : $response->vega[$i],
                            updated: Carbon::parse($response->updated[$i]),
                        );
                    }
                    break;

                case 'no_data':
                    if (isset($response->nextTime)) {
                        $this->next_time = Carbon::parse($response->nextTime);
                    }

                    if (isset($response->prevTime)) {
                        $this->prev_time = Carbon::parse($response->prevTime);
                    }
                    break;
            }
        }
    }

    /**
     * Convert the option chains to a flat Quotes object.
     *
     * This flattens all option quotes from all expiration dates into a single
     * Quotes container, useful when you want to treat a chain as a simple
     * collection of quotes.
     *
     * @return Quotes A Quotes object containing all option quotes from this chain.
     */
    public function toQuotes(): Quotes
    {
        return Quotes::createMerged(
            $this->status,
            $this->getAllQuotes(),
            $this->next_time ?? null,
            $this->prev_time ?? null
        );
    }

    /**
     * Get all option quotes as a flat array.
     *
     * @return OptionQuote[] All option quotes from all expiration dates.
     */
    public function getAllQuotes(): array
    {
        $allQuotes = [];
        foreach ($this->option_chains as $quotes) {
            $allQuotes = array_merge($allQuotes, $quotes);
        }

        return $allQuotes;
    }

    /**
     * Get all expiration dates in the chain.
     *
     * @return string[] Array of expiration date strings (YYYY-MM-DD format).
     */
    public function getExpirationDates(): array
    {
        return array_keys($this->option_chains);
    }

    /**
     * Get option quotes for a specific expiration date.
     *
     * @param string $date The expiration date in YYYY-MM-DD format.
     *
     * @return OptionQuote[] Array of option quotes for the given date, or empty array if not found.
     */
    public function getQuotesByExpiration(string $date): array
    {
        return $this->option_chains[$date] ?? [];
    }

    /**
     * Get the total count of option quotes across all expirations.
     *
     * @return int The total number of option quotes.
     */
    public function count(): int
    {
        $count = 0;
        foreach ($this->option_chains as $quotes) {
            $count += count($quotes);
        }

        return $count;
    }

    /**
     * Get only call options from the chain.
     *
     * @return OptionQuote[] Array of call option quotes.
     */
    public function getCalls(): array
    {
        return array_filter($this->getAllQuotes(), fn(OptionQuote $q) => $q->side === Side::CALL);
    }

    /**
     * Get only put options from the chain.
     *
     * @return OptionQuote[] Array of put option quotes.
     */
    public function getPuts(): array
    {
        return array_filter($this->getAllQuotes(), fn(OptionQuote $q) => $q->side === Side::PUT);
    }

    /**
     * Get option quotes for a specific strike price.
     *
     * @param float $strike The strike price to filter by.
     *
     * @return OptionQuote[] Array of option quotes with the given strike price.
     */
    public function getByStrike(float $strike): array
    {
        return array_filter($this->getAllQuotes(), fn(OptionQuote $q) => $q->strike === $strike);
    }

    /**
     * Get all unique strike prices in the chain, sorted ascending.
     *
     * @return float[] Array of unique strike prices.
     */
    public function getStrikes(): array
    {
        $strikes = array_unique(array_map(fn(OptionQuote $q) => $q->strike, $this->getAllQuotes()));
        sort($strikes);

        return array_values($strikes);
    }

    /**
     * Returns a string representation of the option chains collection.
     *
     * @return string Human-readable option chains summary.
     */
    public function __toString(): string
    {
        if (!$this->isJson()) {
            return "Option Chains - Non-JSON format, use getCsv() or getHtml()";
        }

        $expirationCount = count($this->option_chains);
        $totalContracts = $this->count();
        $lines = [sprintf(
            "Option Chains: %d expiration%s, %d total contract%s (status: %s)",
            $expirationCount,
            $expirationCount === 1 ? '' : 's',
            $totalContracts,
            $totalContracts === 1 ? '' : 's',
            $this->status
        )];

        $displayCount = 0;
        foreach ($this->option_chains as $date => $quotes) {
            if ($displayCount >= 3) {
                break;
            }
            $callCount = count(array_filter($quotes, fn($q) => $q->side === Side::CALL));
            $putCount = count($quotes) - $callCount;
            $lines[] = sprintf("  %s: %d contracts (%d calls, %d puts)", $date, count($quotes), $callCount, $putCount);
            $displayCount++;
        }

        if ($expirationCount > 3) {
            $lines[] = sprintf("  ... and %d more expiration(s)", $expirationCount - 3);
        }

        return implode("\n", $lines);
    }
}
