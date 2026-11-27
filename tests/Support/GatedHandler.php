<?php

namespace MarketDataApp\Tests\Support;

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Guzzle handler that holds every request open until it is completed, and counts the
 * requests open at once.
 *
 * A wait on a request completes it, as a real transport completes the request it is
 * driven for. A test can also complete the oldest open request itself.
 */
final class GatedHandler
{
    /**
     * @var callable(RequestInterface): (ResponseInterface|\Throwable)
     */
    private $respond;

    /**
     * @var array<int, array{0: RequestInterface, 1: Promise}> Open requests, oldest first.
     */
    private array $open = [];

    /**
     * @var list<string> Path of every request sent, in the order sent.
     */
    private array $paths = [];

    private int $peak = 0;

    /**
     * @param callable(RequestInterface): (ResponseInterface|\Throwable) $respond Gives the outcome of a request.
     */
    public function __construct(callable $respond)
    {
        $this->respond = $respond;
    }

    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        $id = count($this->paths);
        $this->paths[] = $request->getUri()->getPath();

        $promise = new Promise(function () use ($id): void {
            $this->complete($id);
        });
        $this->open[$id] = [$request, $promise];
        $this->peak = max($this->peak, count($this->open));

        return $promise;
    }

    /**
     * Complete the request that has been open longest.
     */
    public function completeOldest(): void
    {
        $this->complete(array_key_first($this->open));
    }

    /**
     * Number of requests open now.
     */
    public function open(): int
    {
        return count($this->open);
    }

    /**
     * Number of requests sent so far.
     */
    public function sent(): int
    {
        return count($this->paths);
    }

    /**
     * Largest number of requests that were open at once.
     */
    public function peak(): int
    {
        return $this->peak;
    }

    /**
     * Path of every request sent, in the order sent.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    private function complete(int $id): void
    {
        [$request, $promise] = $this->open[$id];
        unset($this->open[$id]);

        $outcome = ($this->respond)($request);
        if ($outcome instanceof \Throwable) {
            $promise->reject($outcome);
        } else {
            $promise->resolve($outcome);
        }
    }
}
