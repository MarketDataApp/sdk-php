<?php

namespace MarketDataApp\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Throwaway. Fails on purpose, to prove the `Tests passed` aggregate context
 * fails when the matrix fails. Delete with this branch.
 */
class FailProbeTest extends TestCase
{
    public function test_this_must_fail(): void
    {
        $this->assertTrue(false, 'deliberate failure');
    }
}
