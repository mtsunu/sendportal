<?php

declare(strict_types=1);

namespace Tests\Feature\Ses;

use App\Mail\ThrottledSesAdapter;
use Aws\CommandInterface;
use Aws\Result;
use Aws\Ses\Exception\SesException;
use Aws\Ses\SesClient;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Sendportal\Base\Models\EmailService;
use Sendportal\Base\Models\EmailServiceType;

/**
 * Shared fixtures for the SES throttling suite: builders for an SES EmailService,
 * a mocked SesClient (seeded getSendQuota()/sendEmail() plus on-demand SesException),
 * a wired ThrottledSesAdapter, and a Redis availability guard.
 */
trait SesAdapterTestSupport
{
    /** The exact AWS wording for the rate-exceeded Throttling case. */
    protected string $rateExceededMessage = 'Maximum sending rate exceeded.';

    /** The exact AWS wording for the daily-quota Throttling case. */
    protected string $dailyQuotaMessage = 'Daily message quota exceeded.';

    /**
     * Region/key/secret settings. A shared $suffix yields the same derived throttle
     * key across multiple adapters (used to simulate a cross-process fleet).
     */
    protected function throttleSettings(string $suffix = 'default'): array
    {
        return [
            'region' => 'us-east-1',
            'key' => 'AKIA-TEST-' . $suffix,
            'secret' => 'secret-' . $suffix,
        ];
    }

    /**
     * An unsaved SES EmailService (no DB): enough for MailAdapterFactory::adapter(),
     * which reads type_id and settings only.
     */
    protected function sesEmailService(array $settings = [], string $suffix = 'default'): EmailService
    {
        $service = new EmailService();
        $service->type_id = EmailServiceType::SES;
        $service->settings = array_merge($this->throttleSettings($suffix), $settings);

        return $service;
    }

    /**
     * A bare Mockery SesClient. Callers add getSendQuota()/sendEmail() expectations.
     */
    protected function sesClientMock(): MockInterface
    {
        return Mockery::mock(SesClient::class);
    }

    /**
     * A convenience SesClient that reports the given MaxSendRate and returns the
     * given message id for every sendEmail() call.
     */
    protected function sesClient(float|int $maxSendRate = 5, string $messageId = 'msg-id'): MockInterface
    {
        $client = $this->sesClientMock();
        $client->shouldReceive('getSendQuota')
            ->andReturn(new Result(['MaxSendRate' => $maxSendRate]));
        $client->shouldReceive('sendEmail')
            ->andReturn(new Result(['MessageId' => $messageId]));

        return $client;
    }

    /**
     * A ThrottledSesAdapter wired to the given mocked SesClient.
     */
    protected function throttledAdapter(SesClient $client, array $settings = [], string $suffix = 'default'): ThrottledSesAdapter
    {
        $adapter = new ThrottledSesAdapter(array_merge($this->throttleSettings($suffix), $settings));
        $adapter->setClient($client);

        return $adapter;
    }

    /**
     * Construct a Throttling+rate SesException whose getAwsErrorMessage() returns the
     * exact AWS text the production sub-branch matches.
     */
    protected function rateExceededException(): SesException
    {
        return $this->sesException($this->rateExceededMessage, 'Throttling');
    }

    /**
     * Construct a Throttling+daily-quota SesException.
     */
    protected function dailyQuotaException(): SesException
    {
        return $this->sesException($this->dailyQuotaMessage, 'Throttling');
    }

    /**
     * Construct an arbitrary SesException with both the constructor message and
     * context['message'] set to $awsMessage (so getAwsErrorMessage() returns it),
     * and context['code'] set to $code (so getAwsErrorCode() returns it).
     */
    protected function sesException(string $awsMessage, string $code): SesException
    {
        /** @var CommandInterface $command */
        $command = Mockery::mock(CommandInterface::class);

        return new SesException($awsMessage, $command, [
            'code' => $code,
            'message' => $awsMessage,
        ]);
    }

    /**
     * Skip the current test cleanly when the default Redis connection is unreachable.
     */
    protected function redisAvailableOrSkip(): void
    {
        try {
            Redis::connection('default')->ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('requires Redis (Horizon default connection): ' . $e->getMessage());
        }
    }
}
