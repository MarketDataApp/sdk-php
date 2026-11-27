<?php

namespace MarketDataApp\Tests\Unit\Concurrency;

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use MarketDataApp\Concurrency\ConcurrencyLimiter;
use PHPUnit\Framework\TestCase;

/**
 * Edge paths of the request pool, with a pool of one slot and bare promises as transfers.
 *
 * The behaviour through the client, with 50 slots, is in ConcurrencyLimitTest.
 */
class ConcurrencyLimiterTest extends TestCase
{
    public function testALimitBelowOneIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ConcurrencyLimiter(0);
    }

    public function testASendThatThrowsWhileASlotIsFreeThrowsToTheCallerAndFreesTheSlot(): void
    {
        $limiter = new ConcurrencyLimiter(1);

        $error = $this->caught(fn () => $limiter->async(fn () => throw new \RuntimeException('cannot send')));

        $this->assertInstanceOf(\RuntimeException::class, $error);
        $this->assertTrue($this->startsAtOnce($limiter), 'the slot is free again');
    }

    public function testASendThatThrowsAfterWaitingRejectsItsPromiseAndPassesTheSlotOn(): void
    {
        $limiter = new ConcurrencyLimiter(1);
        $first = $limiter->async(fn () => $this->transfer('first'));
        $broken = $limiter->async(fn () => throw new \RuntimeException('cannot send'));
        $third = $limiter->async(fn () => $this->transfer('third'));

        $this->assertSame('first', $first->wait());

        $this->assertSame(PromiseInterface::REJECTED, $broken->getState());
        $this->assertInstanceOf(\RuntimeException::class, Utils::settle([$broken])->wait()[0]['reason']);
        $this->assertSame('third', $third->wait());
        $this->assertTrue($this->startsAtOnce($limiter));
    }

    public function testABlockingSendThatThrowsFreesTheSlot(): void
    {
        $limiter = new ConcurrencyLimiter(1);

        $error = $this->caught(fn () => $limiter->sync(fn () => throw new \RuntimeException('down')));

        $this->assertInstanceOf(\RuntimeException::class, $error);
        $this->assertTrue($this->startsAtOnce($limiter));
    }

    public function testABlockingCallOnAFullPoolDrivesTheOldestRequestUntilASlotFrees(): void
    {
        $limiter = new ConcurrencyLimiter(2);
        $first = $limiter->async(fn () => $this->transfer('first'));
        $second = $limiter->async(fn () => $this->transfer('second'));
        $statesWhenSent = null;

        $response = $limiter->sync(function () use ($first, $second, &$statesWhenSent) {
            $statesWhenSent = [$first->getState(), $second->getState()];
            return 'blocking';
        });

        $this->assertSame('blocking', $response);
        $this->assertSame([PromiseInterface::FULFILLED, PromiseInterface::PENDING], $statesWhenSent);
        $this->assertSame('second', $second->wait());
    }

    public function testACancelledRequestThatWaitsForASlotIsNeverSent(): void
    {
        $limiter = new ConcurrencyLimiter(1);
        $first = $limiter->async(fn () => $this->transfer('first'));
        $sent = false;
        $cancelled = $limiter->async(function () use (&$sent) {
            $sent = true;
            return $this->transfer('cancelled');
        });

        $cancelled->cancel();

        $this->assertSame('first', $first->wait());
        $this->assertFalse($sent);
        $this->assertSame(PromiseInterface::REJECTED, $cancelled->getState());
        $this->assertTrue($this->startsAtOnce($limiter));
    }

    public function testCancellingAStartedRequestCancelsItsTransferAndFreesTheSlot(): void
    {
        $limiter = new ConcurrencyLimiter(1);
        $transferCancelled = false;
        $transfer = new Promise(null, function () use (&$transferCancelled): void {
            $transferCancelled = true;
        });
        $request = $limiter->async(fn () => $transfer);

        $request->cancel();
        Utils::queue()->run();

        $this->assertTrue($transferCancelled);
        $this->assertSame(PromiseInterface::REJECTED, $request->getState());
        $this->assertTrue($this->startsAtOnce($limiter));
    }

    public function testAWaitNestedInsideTheOnlyRequestInFlightStartsOverTheLimitInsteadOfDeadlocking(): void
    {
        $limiter = new ConcurrencyLimiter(1);
        $blocking = null;
        $async = null;
        $outerTransfer = $this->transfer('outer', function () use ($limiter, &$blocking, &$async): void {
            $blocking = $limiter->sync(fn () => 'blocking');
            $async = $limiter->async(fn () => $this->transfer('async'))->wait();
        });
        $outer = $limiter->async(fn () => $outerTransfer);

        $this->assertSame('outer', $outer->wait());

        $this->assertSame('blocking', $blocking);
        $this->assertSame('async', $async);
        $this->assertTrue($this->startsAtOnce($limiter));
    }

    public function testANestedFirstWaitOnARequestThatAnOuterWaitIsDrivingCannotSettleIt(): void
    {
        $limiter = new ConcurrencyLimiter(1);
        $inner = null;
        $first = null;
        $firstTransfer = $this->transfer('first', function () use (&$first, &$inner): void {
            $inner = Utils::settle([$first])->wait()[0];
        });
        $first = $limiter->async(fn () => $firstTransfer);
        $second = $limiter->async(fn () => $this->transfer('second'));

        $this->assertSame('second', $second->wait());

        $this->assertSame(PromiseInterface::REJECTED, $inner['state']);
        $this->assertTrue($this->startsAtOnce($limiter));
    }

    public function testAnErrorWhileABlockingRequestWaitsTakesItOutOfLine(): void
    {
        $limiter = new ConcurrencyLimiter(1);
        $transfer = null;
        $transfer = new Promise(function () use (&$transfer): void {
            $transfer->resolve('first');
            throw new \RuntimeException('the wait failed');
        });
        $first = $limiter->async(fn () => $transfer);
        $sent = false;

        $error = $this->caught(fn () => $limiter->sync(function () use (&$sent) {
            $sent = true;
            return 'blocked';
        }));
        Utils::queue()->run();

        $this->assertInstanceOf(\RuntimeException::class, $error);
        $this->assertFalse($sent);
        $this->assertSame(PromiseInterface::FULFILLED, $first->getState());
        $this->assertTrue($this->startsAtOnce($limiter), 'no slot went to the request that left');
    }

    public function testAnErrorAfterABlockingRequestGotItsSlotGivesTheSlotBack(): void
    {
        $limiter = new ConcurrencyLimiter(1);
        $transfer = null;
        $transfer = new Promise(function () use (&$transfer): void {
            $transfer->resolve('first');
            Utils::queue()->add(static function (): void {
                throw new \RuntimeException('a queued task failed');
            });
        });
        $limiter->async(fn () => $transfer);
        $sent = false;

        $error = $this->caught(fn () => $limiter->sync(function () use (&$sent) {
            $sent = true;
            return 'blocked';
        }));

        $this->assertInstanceOf(\RuntimeException::class, $error);
        $this->assertFalse($sent);
        $this->assertTrue($this->startsAtOnce($limiter));
    }

    public function testARequestStartedInAnotherFiberIsNotDrivenFromThisOne(): void
    {
        $limiter = new ConcurrencyLimiter(1);
        $driven = false;
        $fiber = new \Fiber(function () use ($limiter, &$driven) {
            $transfer = $this->transfer('in the fiber', function () use (&$driven): void {
                $driven = true;
            });
            $request = $limiter->async(fn () => $transfer);
            \Fiber::suspend();
            return $request->wait();
        });
        $fiber->start();

        $this->assertSame('main', $limiter->sync(fn () => 'main'));
        $this->assertFalse($driven, 'a wait in the fiber may already be inside that transfer');

        $fiber->resume();
        $this->assertSame('in the fiber', $fiber->getReturn());
    }

    /**
     * A transfer that a wait completes, after it runs $whileOpen.
     */
    private function transfer(string $value, ?\Closure $whileOpen = null): Promise
    {
        $promise = null;
        $promise = new Promise(function () use (&$promise, $value, $whileOpen): void {
            if ($whileOpen !== null) {
                $whileOpen();
            }
            $promise->resolve($value);
        });

        return $promise;
    }

    /**
     * Whether a new request gets a slot at once, and then let it finish.
     */
    private function startsAtOnce(ConcurrencyLimiter $limiter): bool
    {
        $sent = false;
        $request = $limiter->async(function () use (&$sent) {
            $sent = true;
            return $this->transfer('probe');
        });
        $startedAtOnce = $sent;
        $request->wait();

        return $startedAtOnce;
    }

    private function caught(callable $call): ?\Throwable
    {
        try {
            $call();
        } catch (\Throwable $e) {
            return $e;
        }

        return null;
    }
}
