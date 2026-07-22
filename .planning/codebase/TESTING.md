# Testing Patterns

**Analysis Date:** 2026-07-22

## Test Framework

**Runner:**
- PHPUnit 10.5, declared in `composer.json` and configured by `phpunit.xml.dist`.
- Config: `phpunit.xml.dist` discovers all tests under `tests/`, bootstraps Laravel through Composer, and uses the testing application configuration.

**Assertion Library:**
- PHPUnit assertions plus Laravel's `TestResponse`, database, session, and authentication assertions, as used in `tests/Feature/Workspaces/WorkspacesControllerTest.php` and `tests/TestSupportTrait.php`.

**Run Commands:**
```bash
vendor/bin/phpunit                         # Run the whole suite using phpunit.xml.dist
vendor/bin/phpunit --filter WorkspacesControllerTest  # Run one test class
vendor/bin/phpunit --group workspace_user_test        # Run the named PHPUnit group
```

## Test File Organization

**Location:**
- Keep tests in the dedicated `tests/` tree. Place HTTP, database, auth, invitation, and Livewire behavior in `tests/Feature/<Domain>/`; see `tests/Feature/Workspaces/WorkspaceInvitationsControllerTest.php` and `tests/Feature/Setup/SetupTest.php`.
- Place pure PHPUnit tests that do not require the Laravel base test case in `tests/Unit/`, as illustrated by `tests/Unit/ExampleTest.php`.
- Put shared app bootstrapping in `tests/TestCase.php`, helpers in `tests/TestSupportTrait.php`, and application construction in `tests/CreatesApplication.php`.

**Naming:**
- Name test classes after the subject plus `Test`, for example `tests/Feature/Auth/WorkspaceApiTokenTest.php` and `tests/Feature/Workspaces/SwitchWorkspaceTest.php`.
- Write behavior-oriented public method names in snake_case and tag them `/** @test */`, as in `tests/Feature/Invitations/ExistingUserInvitationTest.php`.

**Structure:**
```
tests/
├── Feature/
│   ├── Auth/             # auth configuration and API token HTTP behavior
│   ├── Invitations/      # invitation registration and acceptance flows
│   ├── Setup/            # setup controller and Livewire component flows
│   └── Workspaces/       # workspace authorization and management flows
├── Unit/                 # isolated PHPUnit tests
├── CreatesApplication.php
├── TestCase.php
└── TestSupportTrait.php
```

## Test Structure

**Suite Organization:**
```php
/** @test */
public function a_user_can_create_a_new_workspace()
{
    // given
    $user = $this->createUserWithWorkspace();
    $newWorkspaceName = $this->faker->company();

    // when
    $this->loginUser($user);
    $response = $this->post(route('workspaces.store'), ['name' => $newWorkspaceName]);

    // then
    $response->assertRedirect(route('workspaces.index'));
    $this->assertDatabaseHas('workspaces', ['name' => $newWorkspaceName]);
}
```
Use the same given/when/then arrangement from `tests/Feature/Workspaces/WorkspacesControllerTest.php` for behavior tests.

**Patterns:**
- Extend `Tests\\TestCase` for Laravel feature tests. It runs migrations in `setUp()`, disables Mix assets, and enables exception handling in `tests/TestCase.php`.
- Add `RefreshDatabase` to every feature test that writes database state, as in `tests/Feature/Workspaces/WorkspaceUserControllerTest.php`; use `WithFaker` when generating realistic input, as in `tests/Feature/Invitations/NewUserInvitationTest.php`.
- Arrange models with Laravel factories, authenticate via `actingAs()` or `loginUser()`, issue HTTP requests through `$this->get()`, `$this->post()`, or `$this->delete()`, and assert responses plus persisted state. See `tests/Feature/Workspaces/WorkspaceInvitationsControllerTest.php`.
- Call `parent::setUp()` before local test setup when the environment must be booted first, as in `tests/Feature/Workspaces/WorkspaceInvitationsControllerTest.php`; set environment-dependent flags before it only when routes/config need them during boot, as in `tests/Feature/Auth/AuthConfigDisabledTest.php`.

## Mocking

**Framework:**
- Use Laravel's container mock helper, backed by Mockery (declared in `composer.json`), for injected collaborators.
- Use Laravel facades to fake side effects: `Mail::fake()` in `tests/Feature/Workspaces/WorkspaceInvitationsControllerTest.php` and `Event::fake()` in `tests/Feature/Invitations/NewUserInvitationTest.php`.

**Patterns:**
```php
$this->mock(Env::class, function ($mock) {
    $mock->shouldReceive('check')->once()->andReturn(false);
});

Livewire::test(Setup::class)
    ->assertSet('active', 0);
```
Follow the concrete dependency mock in `tests/Feature/Setup/SetupTest.php` for a setup-step failure branch.

**What to Mock:**
- Mock a container-resolved dependency when a branch depends on an exceptional, unavailable, or expensive collaborator, such as `App\\Setup\\Env` in `tests/Feature/Setup/SetupTest.php`.
- Fake mail/events before a flow that can dispatch them, as shown in `tests/Feature/Workspaces/WorkspaceInvitationsControllerTest.php` and `tests/Feature/Invitations/NewUserInvitationTest.php`.

**What NOT to Mock:**
- Do not mock Eloquent models, workspace membership services, routing, or database state for ordinary feature flows. Use the real factories, test database, and service code in `tests/Feature/Workspaces/WorkspacesControllerTest.php`.
- Do not mock authentication for a normal HTTP request; use `actingAs()` or the shared `loginUser()` helper in `tests/TestSupportTrait.php`.

## Fixtures and Factories

**Test Data:**
```php
/** @var Workspace $workspace */
$workspace = Workspace::factory()->create();
$user = User::factory()->create();
(new AddWorkspaceMember())->handle($workspace, $user, Workspace::ROLE_MEMBER);
```
This is the established way to create domain state for a membership test in `tests/Feature/Workspaces/WorkspaceInvitationsControllerTest.php`.

**Location:**
- Define reusable Eloquent factory defaults in `database/factories/`, including `database/factories/WorkspaceFactory.php`, `database/factories/UserFactory.php`, `database/factories/InvitationFactory.php`, and `database/factories/ApiTokenFactory.php`.
- Reuse workflow-level helpers from `tests/TestSupportTrait.php`: `createUserWithWorkspace()`, `createUserAndWorkspace()`, `createWorkspaceUser()`, `loginUser()`, and `assertLoginRedirect()`.

## Coverage

**Requirements:** No coverage threshold or reporting command is configured. `phpunit.xml.dist` contains a source include for `src/`, but this repository's application code resides in `app/`, so do not treat it as meaningful application coverage configuration.

**View Coverage:**
```bash
vendor/bin/phpunit --coverage-text  # Available only when the local PHP coverage driver is installed
```

## Test Types

**Unit Tests:**
- Unit-test infrastructure is available through plain `PHPUnit\\Framework\\TestCase` in `tests/Unit/ExampleTest.php`, but the current file is only a starter assertion. Add isolated unit tests here when Laravel bootstrapping and persistence are unnecessary.

**Integration Tests:**
- The dominant suite is Laravel feature/integration testing. It exercises routes, middleware, controllers, validation, models, and the database together, e.g. `tests/Feature/Auth/WorkspaceApiTokenTest.php` and `tests/Feature/Workspaces/WorkspaceRequiredTest.php`.
- CI runs the suite against MySQL and PostgreSQL in `.github/workflows/ci.yml`; write database assertions that remain portable across both engines.

**E2E Tests:**
- Not used. No browser-runner configuration or browser test files are present; validate web behavior at the Laravel HTTP/Livewire level in `tests/Feature/`.

## Common Patterns

**Async Testing:**
```php
Mail::fake();
$response = $this->post(route('users.invitations.store'), ['email' => $email]);
$response->assertRedirect(route('users.index'));
```
Fake queue-like side effects before the request and assert the synchronous state/result, matching `tests/Feature/Workspaces/WorkspaceInvitationsControllerTest.php`. The current suite does not use Promise, queue-job, or browser-wait assertions.

**Error Testing:**
```php
$response = $this->post(route('register'), $postData);

$response->assertRedirect();
$response->assertSessionHasErrors('invitation', 'The invitation is no longer valid.');
self::assertNull(User::where('email', $postData['email'])->first());
```
Use the response assertion plus the absence of unintended persistence, following `tests/Feature/Invitations/NewUserInvitationTest.php`. For authorization concealment, assert `404` as in `tests/Feature/Workspaces/WorkspaceUserControllerTest.php`; for API access, assert `assertUnauthorized()` as in `tests/Feature/Auth/WorkspaceApiTokenTest.php`.

---

*Testing analysis: 2026-07-22*
