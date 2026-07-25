<?php

declare(strict_types=1);

namespace Tests\Feature\Ses;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;
use Tests\CreatesApplication;

/**
 * Base test case for the SES throttling suite.
 *
 * These tests exercise ThrottledSesAdapter with a mocked SesClient and (for the
 * limiter tests) a live Redis connection. None of them touch the host database,
 * so — unlike Tests\TestCase — this base boots the application WITHOUT running
 * migrations, keeping the suite runnable wherever Redis is available.
 */
abstract class SesTestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function tearDown(): void
    {
        if (class_exists(Mockery::class)) {
            Mockery::close();
        }

        parent::tearDown();
    }
}
