<?php

namespace MarketDataApp\Tests\Support;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use MarketDataApp\Client;

/**
 * Client that starts asynchronous requests as a subclass can, and whose retries do not sleep.
 *
 * With $holdBackoff set, the backoff of each asynchronous retry is a timer that the test
 * fires itself, as an event loop fires a timer.
 */
final class AsyncClient extends Client
{
    public bool $holdBackoff = false;

    /**
     * @var list<Promise> Backoff timers, oldest first.
     */
    public array $timers = [];

    /**
     * @var (\Closure(): void)|null Runs during each backoff of a blocking request.
     */
    public ?\Closure $duringBlockingBackoff = null;

    public function startAsync(string $method, array $arguments = []): PromiseInterface
    {
        return $this->async($method, $arguments);
    }

    public function fireTimers(): void
    {
        foreach ($this->timers as $timer) {
            if ($timer->getState() === PromiseInterface::PENDING) {
                $timer->resolve(null);
            }
        }
    }

    protected function createDelayedPromise(float $delay): PromiseInterface
    {
        if (!$this->holdBackoff) {
            return Create::promiseFor(null);
        }

        $timer = new Promise(static function () use (&$timer): void {
            $timer->resolve(null);
        });
        $this->timers[] = $timer;

        return $timer;
    }

    protected function waitForRetry(int $attempt): void
    {
        if ($this->duringBlockingBackoff !== null) {
            ($this->duringBlockingBackoff)();
        }
    }
}
