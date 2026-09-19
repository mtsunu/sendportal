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
class AuthConfigDisabledTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function setUp(): void
    {
        putenv('SENDPORTAL_REGISTER=false');
        putenv('SENDPORTAL_PASSWORD_RESET=false');
        $_ENV['SENDPORTAL_REGISTER'] = 'false';
        $_ENV['SENDPORTAL_PASSWORD_RESET'] = 'false';
        $_SERVER['SENDPORTAL_REGISTER'] = 'false';
        $_SERVER['SENDPORTAL_PASSWORD_RESET'] = 'false';
        Env::enablePutenv();

        parent::setUp();
    }

    /** @test */
    public function the_registration_routes_result_in_404()
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();

        $this->get('email/verify')->assertNotFound();
        $this->get('email/verify/{id}/{hash}')->assertNotFound();
        $this->post('email/resend')->assertNotFound();
    }

    /** @test */
    public function the_password_reset_routes_result_in_404()
    {
        $this->get('password/reset')->assertNotFound();
        $this->post('password/email')->assertNotFound();
        $this->get('password/reset/123')->assertNotFound();
        $this->post('password/reset')->assertNotFound();
    }
}
