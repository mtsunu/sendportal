<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Env;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AuthConfigEnabledTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function setUp(): void
    {
        putenv('SENDPORTAL_REGISTER=true');
        putenv('SENDPORTAL_PASSWORD_RESET=true');
        $_ENV['SENDPORTAL_REGISTER'] = 'true';
        $_ENV['SENDPORTAL_PASSWORD_RESET'] = 'true';
        $_SERVER['SENDPORTAL_REGISTER'] = 'true';
        $_SERVER['SENDPORTAL_PASSWORD_RESET'] = 'true';
        Env::enablePutenv();

        parent::setUp();
    }

    /** @test */
    public function the_registration_routes_result_in_200()
    {
        $this->get('/register')->assertOk();
    }

    /** @test */
    public function the_password_reset_routes_result_in_200()
    {
        $this->get('password/reset')->assertOk();
    }
}
