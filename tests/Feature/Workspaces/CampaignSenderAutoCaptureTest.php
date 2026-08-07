<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use App\Models\ApiToken;
use App\Models\Sender;
use App\Services\Senders\CaptureCampaignSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use Sendportal\Base\Models\Campaign;
use Sendportal\Base\Models\CampaignStatus;
use Sendportal\Base\Models\EmailService;
use RuntimeException;
use Tests\TestCase;

class CampaignSenderAutoCaptureTest extends TestCase
{
    use RefreshDatabase;

    private const CAPTURE_WARNING = 'The campaign was saved, but its sender could not be saved automatically.';

    /**
     * @test
     *
     * SENDER-07, D-01, D-02, and D-08: a newly created campaign captures a
     * normalized sender without changing the campaign's submitted values.
     */
    public function a_direct_campaign_creation_captures_its_sender_identity(): void
    {
        $user = $this->createUserWithWorkspace();
        $workspaceId = $user->currentWorkspace()->id;

        $campaign = Campaign::create([
            'workspace_id' => $workspaceId,
            'name' => '  Product Launch  ',
            'from_name' => '  Acme Marketing  ',
            'from_email' => '  NEWS@ACME.TEST  ',
        ]);

        $this->assertDatabaseCount('senders', 1);
        $this->assertDatabaseHas('senders', [
            'workspace_id' => $workspaceId,
            'label' => 'Product Launch',
            'from_name' => 'Acme Marketing',
            'from_email' => 'news@acme.test',
        ]);

        $this->assertSame('  Acme Marketing  ', $campaign->fresh()->from_name);
        $this->assertSame('  NEWS@ACME.TEST  ', $campaign->fresh()->from_email);
    }

    /**
     * @test
     */
    public function a_direct_campaign_update_does_not_capture_a_new_sender(): void
    {
        $user = $this->createUserWithWorkspace();
        $workspaceId = $user->currentWorkspace()->id;
        $campaign = $this->createCampaign($workspaceId, [
            'name' => 'Original Campaign',
            'from_name' => 'Original Name',
            'from_email' => 'original@example.test',
        ]);

        $campaign->update([
            'from_name' => 'Edited Name',
            'from_email' => 'edited@example.test',
        ]);

        $this->assertDatabaseCount('senders', 1);
        $this->assertDatabaseHas('senders', [
            'workspace_id' => $workspaceId,
            'from_name' => 'Original Name',
            'from_email' => 'original@example.test',
        ]);
        $this->assertDatabaseMissing('senders', [
            'workspace_id' => $workspaceId,
            'from_name' => 'Edited Name',
            'from_email' => 'edited@example.test',
        ]);
    }

    /**
     * @test
     */
    public function the_package_web_campaign_store_uses_the_same_capture_boundary(): void
    {
        $user = $this->createUserWithWorkspace();
        $workspaceId = $user->currentWorkspace()->id;
        $this->actingAs($user);
        $payload = $this->webCampaignPayload($workspaceId, [
            'name' => 'Web Campaign',
            'from_name' => 'Web Sender',
            'from_email' => 'web@example.test',
        ]);

        $this->actingAs($user)
            ->post(route('sendportal.campaigns.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('sendportal_campaigns', [
            'workspace_id' => $workspaceId,
            'name' => 'Web Campaign',
            'from_name' => 'Web Sender',
            'from_email' => 'web@example.test',
        ]);
        $this->assertDatabaseHas('senders', [
            'workspace_id' => $workspaceId,
            'label' => 'Web Campaign',
            'from_name' => 'Web Sender',
            'from_email' => 'web@example.test',
        ]);
    }

    /**
     * @test
     */
    public function the_package_api_campaign_store_uses_the_same_capture_boundary(): void
    {
        $user = $this->createUserWithWorkspace();
        $workspaceId = $user->currentWorkspace()->id;
        $token = ApiToken::factory()->create(['workspace_id' => $workspaceId]);
        $this->actingAs($user);
        $payload = $this->apiCampaignPayload($workspaceId, [
            'name' => 'API Campaign',
            'from_name' => 'API Sender',
            'from_email' => 'api@example.test',
        ]);

        $this->withToken($token->api_token)
            ->postJson(route('sendportal.api.campaigns.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.from_name', 'API Sender')
            ->assertJsonPath('data.from_email', 'api@example.test');

        $this->assertDatabaseHas('sendportal_campaigns', [
            'workspace_id' => $workspaceId,
            'name' => 'API Campaign',
            'from_name' => 'API Sender',
            'from_email' => 'api@example.test',
        ]);
        $this->assertDatabaseHas('senders', [
            'workspace_id' => $workspaceId,
            'label' => 'API Campaign',
            'from_name' => 'API Sender',
            'from_email' => 'api@example.test',
        ]);
    }

    /**
     * @test
     *
     * D-03, D-04, and A5: web capture failure is non-blocking and exposes
     * fixed warning/log data without sender or exception text.
     */
    public function a_web_capture_failure_commits_the_campaign_and_flashes_a_safe_warning(): void
    {
        $user = $this->createUserWithWorkspace();
        $workspaceId = $user->currentWorkspace()->id;
        $this->actingAs($user);
        $payload = $this->webCampaignPayload($workspaceId, [
            'name' => 'Failure Web Campaign',
            'from_name' => 'Secret From Name',
            'from_email' => 'secret-web@example.test',
        ]);
        $this->bindFailingCaptureService();
        Log::spy();

        $this->post(route('sendportal.campaigns.store'), $payload)
            ->assertRedirect()
            ->assertSessionHas('warning', self::CAPTURE_WARNING);

        $campaign = Campaign::query()->where('workspace_id', $workspaceId)->firstOrFail();
        $this->assertSame('Secret From Name', $campaign->from_name);
        $this->assertSame('secret-web@example.test', $campaign->from_email);
        $this->assertDatabaseCount('senders', 0);
        $this->assertSafeWarningLog($campaign->id, $workspaceId);
    }

    /**
     * @test
     *
     * D-03, D-04, and A5: API failure keeps the package response contract and
     * uses only the additive fixed warning header.
     */
    public function an_api_capture_failure_commits_the_campaign_and_adds_a_safe_warning_header(): void
    {
        $user = $this->createUserWithWorkspace();
        $workspaceId = $user->currentWorkspace()->id;
        $token = ApiToken::factory()->create(['workspace_id' => $workspaceId]);
        $this->actingAs($user);
        $payload = $this->apiCampaignPayload($workspaceId, [
            'name' => 'Failure API Campaign',
            'from_name' => 'Secret API Name',
            'from_email' => 'secret-api@example.test',
        ]);
        $this->bindFailingCaptureService();
        Log::spy();

        $response = $this->withToken($token->api_token)
            ->postJson(route('sendportal.api.campaigns.store'), $payload);

        $response->assertCreated()
            ->assertHeader('X-SendPortal-Warning', self::CAPTURE_WARNING)
            ->assertJsonPath('data.name', 'Failure API Campaign')
            ->assertJsonPath('data.from_name', 'Secret API Name')
            ->assertJsonPath('data.from_email', 'secret-api@example.test');

        $campaign = Campaign::query()->where('workspace_id', $workspaceId)->firstOrFail();
        $this->assertDatabaseCount('senders', 0);
        $this->assertSafeWarningLog($campaign->id, $workspaceId);
    }

    /**
     * @test
     */
    public function every_campaign_status_and_immediate_send_mode_can_capture_a_sender(): void
    {
        $user = $this->createUserWithWorkspace();
        $workspaceId = $user->currentWorkspace()->id;
        $cases = [
            ['status_id' => CampaignStatus::STATUS_DRAFT],
            ['scheduled_at' => now()->addHour()],
            ['status_id' => CampaignStatus::STATUS_QUEUED],
            ['status_id' => CampaignStatus::STATUS_SENDING],
            ['status_id' => CampaignStatus::STATUS_SENT],
            ['save_as_draft' => false],
        ];

        foreach ($cases as $index => $overrides) {
            $this->createCampaign($workspaceId, array_merge([
                'name' => 'Mode Campaign '.$index,
                'from_name' => 'Mode Sender '.$index,
                'from_email' => 'mode-'.$index.'@example.test',
            ], $overrides));
        }

        $this->assertSame(count($cases), Sender::where('workspace_id', $workspaceId)->count());
        foreach ($cases as $index => $unused) {
            $this->assertDatabaseHas('senders', [
                'workspace_id' => $workspaceId,
                'label' => 'Mode Campaign '.$index,
                'from_name' => 'Mode Sender '.$index,
                'from_email' => 'mode-'.$index.'@example.test',
            ]);
        }
    }

    /**
     * @test
     */
    public function normalized_duplicates_are_silent_and_preserve_the_existing_label(): void
    {
        $user = $this->createUserWithWorkspace();
        $workspaceId = $user->currentWorkspace()->id;
        $user->currentWorkspace()->senders()->create([
            'label' => 'User Managed Label',
            'from_name' => 'Acme Team',
            'from_email' => 'team@example.test',
        ]);

        $campaign = $this->createCampaign($workspaceId, [
            'name' => 'Automatic Label Must Not Win',
            'from_name' => '  Acme Team  ',
            'from_email' => ' TEAM@EXAMPLE.TEST ',
        ]);

        $this->assertDatabaseCount('senders', 1);
        $this->assertDatabaseHas('senders', [
            'workspace_id' => $workspaceId,
            'label' => 'User Managed Label',
            'from_name' => 'Acme Team',
            'from_email' => 'team@example.test',
        ]);
        $this->assertSame('  Acme Team  ', $campaign->fresh()->from_name);
        $this->assertSame(' TEAM@EXAMPLE.TEST ', $campaign->fresh()->from_email);
    }

    /**
     * @test
     */
    public function deleting_a_sender_allows_a_later_campaign_to_recreate_the_pair(): void
    {
        $user = $this->createUserWithWorkspace();
        $workspace = $user->currentWorkspace();
        $sender = $workspace->senders()->create([
            'label' => 'First Label',
            'from_name' => 'Acme Team',
            'from_email' => 'team@example.test',
        ]);

        $this->createCampaign($workspace->id, [
            'name' => 'Duplicate Campaign',
            'from_name' => 'Acme Team',
            'from_email' => 'team@example.test',
        ]);
        $sender->delete();

        $this->createCampaign($workspace->id, [
            'name' => 'Recreated Campaign',
            'from_name' => ' Acme Team ',
            'from_email' => ' TEAM@EXAMPLE.TEST ',
        ]);

        $this->assertDatabaseCount('senders', 1);
        $this->assertDatabaseHas('senders', [
            'workspace_id' => $workspace->id,
            'label' => 'Recreated Campaign',
            'from_name' => 'Acme Team',
            'from_email' => 'team@example.test',
        ]);
    }

    /**
     * @test
     * @dataProvider blankSenderInputProvider
     */
    public function blank_sender_fields_are_a_silent_no_op(?string $fromName, ?string $fromEmail): void
    {
        $user = $this->createUserWithWorkspace();

        $this->createCampaign($user->currentWorkspace()->id, [
            'from_name' => $fromName,
            'from_email' => $fromEmail,
        ]);

        $this->assertDatabaseCount('senders', 0);
    }

    /**
     * @return array<int, array{fromName: string|null, fromEmail: string|null}>
     */
    public static function blankSenderInputProvider(): array
    {
        return [
            'empty name' => ['fromName' => '', 'fromEmail' => 'valid@example.test'],
            'null name' => ['fromName' => null, 'fromEmail' => 'valid@example.test'],
            'whitespace name' => ['fromName' => '   ', 'fromEmail' => 'valid@example.test'],
            'empty email' => ['fromName' => 'Valid Name', 'fromEmail' => ''],
            'null email' => ['fromName' => 'Valid Name', 'fromEmail' => null],
            'whitespace email' => ['fromName' => 'Valid Name', 'fromEmail' => '   '],
        ];
    }

    /**
     * @test
     */
    public function campaign_labels_are_bounded_to_255_utf8_code_points(): void
    {
        $user = $this->createUserWithWorkspace();
        $name = str_repeat('🚀', 300);

        $this->createCampaign($user->currentWorkspace()->id, [
            'name' => $name,
            'from_name' => 'Long Label Sender',
            'from_email' => 'long-label@example.test',
        ]);

        $sender = Sender::query()->firstOrFail();
        $this->assertSame(255, Str::length($sender->label));
    }

    /**
     * @test
     */
    public function concurrent_campaign_creations_converge_to_one_sender_on_supported_drivers(): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            $this->markTestSkipped('Cross-process concurrency requires MySQL or PostgreSQL.');
        }

        if (! function_exists('proc_open')) {
            $this->markTestSkipped('The proc_open process primitive is unavailable.');
        }

        $connection = $this->concurrencyConnection();
        $fixture = $this->createCommittedConcurrencyFixture($connection);
        $scriptPath = tempnam(sys_get_temp_dir(), 'sender-capture-script-');
        $barrierPath = tempnam(sys_get_temp_dir(), 'sender-capture-barrier-');
        $readyPaths = [];
        $processes = [];

        if ($scriptPath === false || $barrierPath === false) {
            $this->fail('Unable to create concurrency test files.');
        }

        file_put_contents($scriptPath, $this->concurrencyChildScript());
        unlink($barrierPath);

        try {
            foreach ([1, 2] as $processNumber) {
                $readyPath = tempnam(sys_get_temp_dir(), 'sender-capture-ready-');
                $logPath = tempnam(sys_get_temp_dir(), 'sender-capture-log-');

                if ($readyPath === false || $logPath === false) {
                    $this->fail('Unable to create concurrency process files.');
                }

                unlink($readyPath);
                $readyPaths[] = $readyPath;
                $descriptors = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ];
                $pipes = [];
                $process = proc_open([
                    PHP_BINARY,
                    '-f',
                    $scriptPath,
                    '--',
                    base_path(),
                    (string) $fixture['workspace_id'],
                    $readyPath,
                    $barrierPath,
                    $logPath,
                ], $descriptors, $pipes, base_path());

                if (! is_resource($process)) {
                    $this->fail('Unable to start concurrency child process.');
                }

                fclose($pipes[0]);
                $processes[] = [
                    'process' => $process,
                    'pipes' => $pipes,
                    'log' => $logPath,
                ];
            }

            $this->waitForFiles($readyPaths);
            file_put_contents($barrierPath, 'go');

            foreach ($processes as $processData) {
                $stdout = stream_get_contents($processData['pipes'][1]);
                $stderr = stream_get_contents($processData['pipes'][2]);
                fclose($processData['pipes'][1]);
                fclose($processData['pipes'][2]);
                $exitCode = proc_close($processData['process']);

                $this->assertSame(0, $exitCode, $stderr ?: $stdout);
                $this->assertStringContainsString('campaign_id', $stdout);
                $this->assertStringNotContainsString(
                    'campaign_sender_auto_capture_failed',
                    (string) file_get_contents($processData['log'])
                );
            }

            $this->assertSame(1, $connection->table('senders')
                ->where('workspace_id', $fixture['workspace_id'])
                ->where('from_name', 'Concurrent Sender')
                ->where('from_email', 'concurrent@example.test')
                ->count());
            $this->assertSame(2, $connection->table('sendportal_campaigns')
                ->where('workspace_id', $fixture['workspace_id'])
                ->count());
        } finally {
            foreach ($processes as $processData) {
                if (is_resource($processData['process'])) {
                    proc_terminate($processData['process']);
                    foreach ([1, 2] as $pipeNumber) {
                        if (is_resource($processData['pipes'][$pipeNumber])) {
                            fclose($processData['pipes'][$pipeNumber]);
                        }
                    }
                    proc_close($processData['process']);
                }

                @unlink($processData['log']);
            }

            $this->deleteCommittedConcurrencyFixture($connection, $fixture);
            @unlink($scriptPath);
            @unlink($barrierPath);
            foreach ($readyPaths as $readyPath) {
                @unlink($readyPath);
            }
        }
    }

    private function createCampaign(int $workspaceId, array $overrides = []): Campaign
    {
        return Campaign::create(array_merge([
            'workspace_id' => $workspaceId,
            'name' => 'Campaign '.Str::random(8),
            'subject' => 'Campaign subject',
            'from_name' => 'Campaign Sender',
            'from_email' => Str::lower(Str::random(8)).'@example.test',
        ], $overrides));
    }

    private function webCampaignPayload(int $workspaceId, array $overrides = []): array
    {
        $emailService = EmailService::factory()->create(['workspace_id' => $workspaceId]);

        return array_merge([
            'name' => 'Web Campaign',
            'subject' => 'Web subject',
            'from_name' => 'Web Sender',
            'from_email' => 'web@example.test',
            'email_service_id' => $emailService->id,
            'content' => 'Web content',
        ], $overrides);
    }

    private function apiCampaignPayload(int $workspaceId, array $overrides = []): array
    {
        $emailService = EmailService::factory()->create(['workspace_id' => $workspaceId]);

        return array_merge([
            'name' => 'API Campaign',
            'subject' => 'API subject',
            'from_name' => 'API Sender',
            'from_email' => 'api@example.test',
            'email_service_id' => $emailService->id,
            'content' => 'API content',
            'send_to_all' => 1,
            'scheduled_at' => now()->toISOString(),
        ], $overrides);
    }

    private function bindFailingCaptureService(): void
    {
        $mock = Mockery::mock(CaptureCampaignSender::class);
        $mock->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('secret sender payload must not be logged', 409));

        $this->app->instance(CaptureCampaignSender::class, $mock);
    }

    private function assertSafeWarningLog(int $campaignId, int $workspaceId): void
    {
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('campaign_sender_auto_capture_failed', Mockery::on(static function (array $context) use ($campaignId, $workspaceId): bool {
                return $context['campaign_id'] === $campaignId
                    && $context['workspace_id'] === $workspaceId
                    && $context['exception_class'] === RuntimeException::class
                    && $context['exception_code'] === 409
                    && ! array_key_exists('message', $context)
                    && ! array_key_exists('from_name', $context)
                    && ! array_key_exists('from_email', $context);
            }));
    }

    private function concurrencyConnection(): \Illuminate\Database\Connection
    {
        $default = config('database.default');

        config([
            'database.connections.sender_capture_concurrency' => config('database.connections.'.$default),
        ]);
        DB::purge('sender_capture_concurrency');

        return DB::connection('sender_capture_concurrency');
    }

    /**
     * @param \Illuminate\Database\Connection $connection
     * @return array{user_id: int, workspace_id: int}
     */
    private function createCommittedConcurrencyFixture(\Illuminate\Database\Connection $connection): array
    {
        $timestamp = now();
        $userId = (int) $connection->table('users')->insertGetId([
            'name' => 'Concurrency Owner',
            'email' => 'concurrency-'.Str::lower(Str::random(12)).'@example.test',
            'email_verified_at' => $timestamp,
            'password' => 'not-used',
            'remember_token' => Str::random(10),
            'locale' => 'en',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $workspaceId = (int) $connection->table('workspaces')->insertGetId([
            'owner_id' => $userId,
            'name' => 'Concurrency Workspace',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $connection->table('workspace_users')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'role' => 'owner',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return [
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
        ];
    }

    /**
     * @param array{user_id: int, workspace_id: int} $fixture
     */
    private function deleteCommittedConcurrencyFixture(\Illuminate\Database\Connection $connection, array $fixture): void
    {
        $connection->table('sendportal_campaigns')
            ->where('workspace_id', $fixture['workspace_id'])
            ->delete();
        $connection->table('senders')
            ->where('workspace_id', $fixture['workspace_id'])
            ->delete();
        $connection->table('workspace_users')
            ->where('workspace_id', $fixture['workspace_id'])
            ->delete();
        $connection->table('workspaces')->where('id', $fixture['workspace_id'])->delete();
        $connection->table('users')->where('id', $fixture['user_id'])->delete();
    }

    private function waitForFiles(array $paths): void
    {
        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            if (collect($paths)->every(static fn (string $path): bool => is_file($path))) {
                return;
            }

            usleep(10_000);
        }

        $this->fail('Concurrency child processes did not reach the start barrier.');
    }

    private function concurrencyChildScript(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

$root = $argv[1];
$workspaceId = (int) $argv[2];
$readyPath = $argv[3];
$barrierPath = $argv[4];
$logPath = $argv[5];

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config([
    'logging.default' => 'single',
    'logging.channels.single.path' => $logPath,
]);

file_put_contents($readyPath, 'ready');
while (! is_file($barrierPath)) {
    usleep(10_000);
}

try {
    $campaign = Sendportal\Base\Models\Campaign::create([
        'workspace_id' => $workspaceId,
        'name' => 'Concurrent Campaign',
        'subject' => 'Concurrent subject',
        'from_name' => 'Concurrent Sender',
        'from_email' => 'concurrent@example.test',
    ]);

    echo json_encode(['campaign_id' => $campaign->getKey()], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(1);
}
PHP;
    }
}
