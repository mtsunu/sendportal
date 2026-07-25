<?php

declare(strict_types=1);

namespace Tests\Feature\Ses;

use App\Mail\ThrottledSesAdapter;
use Aws\Result;
use Sendportal\Base\Factories\MailAdapterFactory;
use Sendportal\Base\Services\Messages\MessageTrackingOptions;

/**
 * End-to-end tracer slice (SES-01/SES-05 wiring): the boot() rebind routes SES to
 * ThrottledSesAdapter, and one paced send flows through the cached rate read, the
 * Redis limiter, the SES call, and message-id resolution.
 */
final class ThrottledSesAdapterTracerTest extends SesTestCase
{
    use SesAdapterTestSupport;

    /** @test */
    public function factory_returns_throttled_adapter_for_an_ses_service_after_boot(): void
    {
        $service = $this->sesEmailService();

        $adapter = (new MailAdapterFactory())->adapter($service);

        self::assertInstanceOf(ThrottledSesAdapter::class, $adapter);
    }

    /** @test */
    public function a_single_paced_send_returns_the_mocked_message_id_end_to_end(): void
    {
        $this->redisAvailableOrSkip();

        $client = $this->sesClientMock();
        $client->shouldReceive('getSendQuota')
            ->andReturn(new Result(['MaxSendRate' => 5]));
        $client->shouldReceive('sendEmail')
            ->once()
            ->andReturn(new Result(['MessageId' => 'tracer-id']));

        $adapter = $this->throttledAdapter($client);

        $messageId = $adapter->send(
            'from@example.com',
            'From Name',
            'to@example.com',
            'Subject line',
            new MessageTrackingOptions(),
            '<p>Hello</p>'
        );

        self::assertSame('tracer-id', $messageId);
    }
}
