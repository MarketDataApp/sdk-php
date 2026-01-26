<?php

namespace MarketDataApp\Tests\Unit\MutualFunds;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\MutualFunds\Candle;
use MarketDataApp\Endpoints\Responses\MutualFunds\Candles;
use InvalidArgumentException;
use MarketDataApp\Enums\Format;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the Mutual Funds endpoints of the MarketDataApp.
 *
 * This class tests various scenarios of the candles endpoint for mutual funds data.
 */
class MutualFundsTest extends TestCase
{

    use MockResponses;

    /**
     * The client instance used for testing.
     *
     * @var Client
     */
    private Client $client;

    /**
     * Set up the test environment.
     *
     * This method is called before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        // Save original token state before clearing
        $this->saveMarketDataTokenState();
        
        // Clear MARKETDATA_TOKEN environment variable to ensure empty token is used.
        // This prevents real API calls during Client construction by ensuring
        // _setup_rate_limits() skips the /user/ endpoint validation call.
        $this->clearMarketDataToken();
        
        // Use empty token for unit tests to skip validation (tests use mocks anyway)
        $token = '';
        $client = new Client($token);
        $this->client = $client;
    }

    /**
     * Restore original environment variable state after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->restoreMarketDataTokenState();
        parent::tearDown();
    }

    /**
     * Test the candles endpoint with 'from' and 'to' parameters for a successful response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_fromTo_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's' => 'ok',
            't' => [1577941200, 1578027600, 1578286800, 1578373200, 1578459600, 1578546000, 1578632400],
            'o' => [300.69, 298.6, 299.65, 298.84, 300.32, 302.39, 301.53],
            'h' => [300.69, 298.6, 299.65, 298.84, 300.32, 302.39, 301.53],
            'l' => [300.69, 298.6, 299.65, 298.84, 300.32, 302.39, 301.53],
            'c' => [300.69, 298.6, 299.65, 298.84, 300.32, 302.39, 301.53]
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->mutual_funds->candles(
            symbol: 'VFINX',
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertCount(7, $response->candles);

        // Verify each item in the response is an object of the correct type and has the correct values.
        for ($i = 0; $i < count($response->candles); $i++) {
            $this->assertInstanceOf(Candle::class, $response->candles[$i]);
            $this->assertEquals($mocked_response['c'][$i], $response->candles[$i]->close);
            $this->assertEquals($mocked_response['h'][$i], $response->candles[$i]->high);
            $this->assertEquals($mocked_response['l'][$i], $response->candles[$i]->low);
            $this->assertEquals($mocked_response['o'][$i], $response->candles[$i]->open);
            $this->assertEquals(Carbon::parse($mocked_response['t'][$i]), $response->candles[$i]->timestamp);
        }
    }

    /**
     * Test the candles endpoint with CSV format for a successful response.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_csv_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = "s, t, o, h, l, c\r\n";
        $this->setMockResponses([new Response(200, [], $mocked_response)]);

        $response = $this->client->mutual_funds->candles(
            symbol: 'VFINX',
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(Format::CSV)
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals($mocked_response, $response->getCsv());
    }

    /**
     * Test CSV format initializes typed properties with defaults.
     *
     * BUG-019: CSV responses left typed properties (status, next_time) uninitialized,
     * causing PHP Error when accessed.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_csv_typedPropertiesInitialized(): void
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $this->setMockResponses([new Response(200, [], "s, t, o, h, l, c\r\n")]);

        $response = $this->client->mutual_funds->candles(
            symbol: 'VFINX',
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D',
            parameters: new Parameters(Format::CSV)
        );

        // Access typed properties - should not throw PHP Error
        $this->assertEquals('no_data', $response->status);
        $this->assertNull($response->next_time);
        $this->assertEmpty($response->candles);
    }

    /**
     * Test the candles endpoint for a successful response with no data.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_noData_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's' => 'no_data',
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->mutual_funds->candles(
            symbol: 'VFINX',
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEmpty($response->candles);
        $this->assertFalse(isset($response->next_time));
    }

    /**
     * Test the candles endpoint for a successful response with no data and next time.
     *
     * @return void
     * @throws GuzzleException
     * @throws ApiException
     */
    public function testCandles_noDataNextTime_success()
    {
        // Mock response: NOT from real API output (synthetic/test data)
        $mocked_response = [
            's'        => 'no_data',
            'nextTime' => 1663958094,
        ];
        $this->setMockResponses([new Response(200, [], json_encode($mocked_response))]);

        $response = $this->client->mutual_funds->candles(
            symbol: 'VFINX',
            from: '2022-09-01',
            to: '2022-09-05',
            resolution: 'D'
        );

        // Verify that the response is an object of the correct type.
        $this->assertInstanceOf(Candles::class, $response);
        $this->assertEquals($mocked_response['nextTime'], $response->next_time);
        $this->assertEmpty($response->candles);
    }

    /**
     * Test candles endpoint with invalid date range.
     */
    public function testCandles_invalidDateRange_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`from` date must be before `to` date');

        $this->client->mutual_funds->candles(
            symbol: 'VFINX',
            from: '2024-01-31',
            to: '2024-01-01',
            resolution: 'D'
        );
    }

    /**
     * Test candles endpoint with invalid resolution.
     */
    public function testCandles_invalidResolution_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid resolution format');

        $this->client->mutual_funds->candles(
            symbol: 'VFINX',
            from: '2024-01-01',
            resolution: 'invalid'
        );
    }

    /**
     * Test candles endpoint with invalid countback.
     */
    public function testCandles_invalidCountback_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`countback` must be a positive integer');

        $this->client->mutual_funds->candles(
            symbol: 'VFINX',
            from: '2024-01-01',
            resolution: 'D',
            countback: -5
        );
    }

    // ========================================================================
    // SYMBOL TRIMMING
    // Bug 019: MutualFunds::candles() should trim whitespace from symbols
    // ========================================================================

    /**
     * Test candles() trims whitespace from symbol.
     *
     * Bug 019: Symbols with leading/trailing whitespace should be trimmed
     * before being used in the URL path to avoid encoded spaces (%20).
     */
    public function testCandles_symbolWithWhitespace_isTrimmed(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $history = [];
        $this->setMockResponsesWithHistory([
            new Response(200, [], json_encode([
                's' => 'ok',
                'o' => [100.0],
                'h' => [101.0],
                'l' => [99.0],
                'c' => [100.5],
                't' => [1704153600],
            ])),
        ], $history);

        $this->client->mutual_funds->candles(
            symbol: ' VFIAX ',
            from: '2024-01-01',
            to: '2024-01-05',
            resolution: 'D'
        );

        $path = $history[0]['request']->getUri()->getPath();
        $this->assertEquals('v1/funds/candles/D/VFIAX/', $path);
        $this->assertStringNotContainsString('%20', $path, 'Path should not contain encoded space');
    }
}
