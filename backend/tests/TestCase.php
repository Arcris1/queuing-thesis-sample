<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    /**
     * Freeze "now" to a fixed date so date-dependent tests (which use
     * `whereDate(... , today())` and fixtures dated 2026-06-18) are
     * deterministic regardless of the real wall-clock date. Tests that need
     * their own clock still override this via Carbon::setTestNow()/travelTo().
     */
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-18 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
