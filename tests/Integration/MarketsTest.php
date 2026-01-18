<?php

namespace MarketDataApp\Tests\Integration;

use Carbon\Carbon;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Markets\Status;
use MarketDataApp\Endpoints\Responses\Markets\Statuses;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Markets endpoints.
 */
class MarketsTest extends TestCase
{
    /**
     * @var Client The client instance used for testing.
     */
    private Client $client;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        // Use the same robust token detection as Settings class
        $token = getenv('MARKETDATA_TOKEN');
        if ($token === false || $token === '') {
            $token = $_ENV['MARKETDATA_TOKEN'] ?? $_SERVER['MARKETDATA_TOKEN'] ?? null;
        }
        if ($token === null || $token === '') {
            $this->markTestSkipped('MARKETDATA_TOKEN environment variable not set');
        }
        $client = new Client($token);
        $this->client = $client;
    }

    /**
     * Test markets status with human-readable format.
     * Verifies that the API returns human-readable JSON keys with spaces.
     */
    public function testStatus_humanReadable_returnsHumanReadableKeys()
    {
        $response = $this->client->markets->status(
            parameters: new Parameters(use_human_readable: true)
        );

        $this->assertInstanceOf(Statuses::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertNotEmpty($response->statuses);
        $this->assertInstanceOf(Status::class, $response->statuses[0]);
        $this->assertInstanceOf(Carbon::class, $response->statuses[0]->date);
        $this->assertTrue(in_array($response->statuses[0]->status, ['open', 'closed']));
    }
}
