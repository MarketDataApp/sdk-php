<?php

namespace MarketDataApp\Concurrency;

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;

/**
 * The pool of request slots that every request of one client shares.
 *
 * A request takes a slot for each attempt and gives it back when the attempt settles, before
 * any handler of the attempt runs, so a retry that waits out its backoff holds no slot. A
 * request that finds every slot taken waits in line, and starts the moment a slot frees.
 *
 * PHP has no scheduler: a promise moves only while something waits on it. A wait for a slot
 * therefore drives the requests in flight until one finishes. A Guzzle promise accepts one
 * waiter at a time, so a wait never drives a transfer that a wait further up the stack is
 * already inside, nor one started in another fiber. When no request in flight can be driven,
 * the waiting request starts over the limit instead of blocking forever.
 *
 * @internal
 */
final class ConcurrencyLimiter
{
    /**
     * Slots taken.
     */
    private int $active = 0;

    /**
     * @var array<int, \Closure(): void> Requests waiting for a slot, oldest first. Each is called once a slot is taken for it.
     */
    private array $waiting = [];

    /**
     * @var array<int, array{0: PromiseInterface, 1: ?\Fiber}> Transfers holding a slot, with the fiber that started them.
     */
    private array $transfers = [];

    /**
     * @var array<int, true> Transfers that a wait is inside.
     */
    private array $driving = [];

    private int $nextId = 0;

    /**
     * @param int $limit Requests in flight at most.
     */
    public function __construct(private readonly int $limit)
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException('The concurrency limit must be at least 1.');
        }
    }

    /**
     * Send a request in a slot, at once or when a slot frees.
     *
     * @param callable(): PromiseInterface $send Starts the transfer. What it throws when a slot is free at once
     *                                           reaches the caller; what it throws later rejects the promise.
     *
     * @return PromiseInterface Settles as the transfer settles, once its slot is free again.
     */
    public function async(callable $send): PromiseInterface
    {
        $id = $this->nextId++;
        $fiber = \Fiber::getCurrent();
        $result = null;
        $result = new Promise(
            function () use ($id, &$result): void {
                $this->waitUntil($id, static fn (): bool => $result->getState() !== PromiseInterface::PENDING);
            },
            function () use ($id): void {
                if (isset($this->transfers[$id])) {
                    $this->transfers[$id][0]->cancel();
                }
            }
        );

        if ($this->active < $this->limit) {
            $this->active++;
            $this->start($id, $fiber, $send, $result, true);
        } else {
            $this->waiting[$id] = function () use ($id, $fiber, $send, $result): void {
                if ($result->getState() !== PromiseInterface::PENDING) {
                    $this->active--;
                    return;
                }
                $this->start($id, $fiber, $send, $result, false);
            };
        }

        return $result;
    }

    /**
     * Run a blocking request in a slot, and wait for a slot when every slot is taken.
     *
     * @template T
     *
     * @param callable(): T $send Sends the request and returns its response.
     *
     * @return T
     */
    public function sync(callable $send): mixed
    {
        $this->acquire();
        try {
            return $send();
        } finally {
            $this->release();
        }
    }

    private function start(int $id, ?\Fiber $fiber, callable $send, Promise $result, bool $rethrow): void
    {
        try {
            $transfer = $send();
        } catch (\Throwable $e) {
            $this->active--;
            if ($rethrow) {
                throw $e;
            }
            $result->reject($e);
            return;
        }

        $this->transfers[$id] = [$transfer, $fiber];
        $transfer->then(
            function ($value) use ($id, $result): void {
                $this->finish($id);
                $result->resolve($value);
            },
            function ($reason) use ($id, $result): void {
                $this->finish($id);
                $result->reject($reason);
            }
        );
    }

    private function finish(int $id): void
    {
        unset($this->transfers[$id]);
        $this->release();
    }

    private function release(): void
    {
        $this->active--;
        while ($this->active < $this->limit && $this->waiting !== []) {
            $this->grant(array_key_first($this->waiting));
        }
    }

    private function grant(int $id): void
    {
        $grant = $this->waiting[$id];
        unset($this->waiting[$id]);
        $this->active++;
        $grant();
    }

    private function acquire(): void
    {
        if ($this->active < $this->limit) {
            $this->active++;
            return;
        }

        $id = $this->nextId++;
        $granted = false;
        $this->waiting[$id] = static function () use (&$granted): void {
            $granted = true;
        };

        $acquired = false;
        try {
            $this->waitUntil($id, static function () use (&$granted): bool {
                return $granted;
            });
            $acquired = true;
        } finally {
            if (!$acquired) {
                if ($granted) {
                    $this->release();
                } else {
                    unset($this->waiting[$id]);
                }
            }
        }
    }

    /**
     * Drive the requests in flight until $done holds, for the request or the blocking call $id.
     */
    private function waitUntil(int $id, callable $done): void
    {
        while (!$done()) {
            $next = $this->drivable($id);
            if ($next !== null) {
                $this->drive($next);
            } elseif (isset($this->waiting[$id])) {
                $this->grant($id);
            } else {
                // Reached only when a wait further up the stack, or in another fiber, is inside its own transfer.
                return;
            }
        }
    }

    private function drivable(int $id): ?int
    {
        if (isset($this->transfers[$id]) && !isset($this->driving[$id])) {
            return $id;
        }

        $fiber = \Fiber::getCurrent();
        foreach ($this->transfers as $other => [, $startedIn]) {
            if ($startedIn === $fiber && !isset($this->driving[$other])) {
                return $other;
            }
        }

        return null;
    }

    private function drive(int $id): void
    {
        $this->driving[$id] = true;
        try {
            $this->transfers[$id][0]->wait(false);
        } finally {
            unset($this->driving[$id]);
        }
        Utils::queue()->run();
    }
}
