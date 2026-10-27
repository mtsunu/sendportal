# SES campaign delivery tracking

This SendPortal host (branded Rantak Mail in production) updates campaign reports from SES events. It does not change the separate `rantak-mail` application or the transactional-email API. No vendor package files are modified.

## Flow and stored state

SES configuration set → SNS Standard topic → HTTPS POST `/api/v1/webhooks/aws` → durable database inbox → `sendportal-webhook-process` queue → campaign message timestamps and host status table → Campaign / Recipients.

The existing AWS route is rebound to the host controller. It validates SNS signatures with the official AWS validator, restricts allowed topics to an explicitly assigned workspace, checks the SES sending account against the topic account, and checks provider/region/recipient/message ID when applying each event. Receipt does not require a browser login or workspace token. Until a trusted topic/workspace is configured the receiver returns 503; untrusted topics/signatures return 403. Do not connect SNS to the old vendor controller or an unverified endpoint.

`ses_delivery_events` stores normalized event metadata, recipients and limited diagnostic text; original email bodies, subjects and headers are not retained. It deduplicates equivalent event metadata. `ses_message_statuses` holds delay/reject/failure detail and the VDM reconciliation cursor. Existing package timestamp columns remain authoritative for existing report tabs. Application logs contain local event identifiers only; do not enable raw SNS payload logging.

Delivery means the receiving mail server accepted the email. It does not prove inbox placement. Send means SES accepted the send request. An empty timestamp means no corresponding event has been recorded. Opening/clicking requires tracking and is not definitive proof of a human action. Complaint is independent of prior delivery. Permanent bounce and complaint stop future mail to the matching subscriber through the existing unsubscribe fields; final transient bounces are recorded without permanent suppression.

## Production preflight and configuration

1. Verify the production checkout is this repository and identify the actual SES account, region, workspace ID and campaign Email Service. Do not infer them from local fixtures. Back up the database before migrations.
2. Deploy the reviewed code and lockfile with normal Composer platform/advisory checks enabled; run `php artisan migrate --force`. No `vendor:publish --force` for package views: the host owns a scoped Recipients override.
3. Create/use one SNS **Standard** topic in the sending SES region, scoped to this workspace. A single HTTPS subscription handles every campaign and event type. Leave raw message delivery OFF so the receiver gets the signed SNS envelope.
4. Restrict topic publication to `ses.amazonaws.com`, the expected SourceAccount and configuration-set SourceArn. Configure a subscription dead-letter SQS queue to retain events that SNS cannot deliver after retries, and monitor that queue. SQS, SNS, transfer, and optional logging charges are separate AWS usage.
5. Set the production environment:

   ```dotenv
   SES_SNS_TOPIC_ARN=arn:aws:sns:ap-southeast-1:123456789012:campaign-events
   SES_SNS_WORKSPACE_ID=1
   SES_VDM_RECONCILE_ENABLED=false
   QUEUE_CONNECTION=redis
   ```

   ARN/workspace values above are examples, not verified production identifiers. One topic maps to one workspace. For multiple independent workspaces, add explicit ARN → workspace ID entries in `config/sendportal-ses-events.php` and use distinct topics. Do not put API credentials in these mappings.
6. Run `php artisan config:cache` after setting environment values. Make sure Horizon consumes `sendportal-webhook-process` (already present in host Horizon config). Restart workers with `php artisan horizon:terminate` using the normal process manager.
7. Add the HTTPS subscription `https://rantak.pelayanserver.com/api/v1/webhooks/aws` only after confirming this is the deployed app URL and the receiver/configuration is ready. The verified subscription confirmation is automatic. The public endpoint must be reachable without login, WAF challenges or redirect. API responses must not cache. Use SNS retry policy for HTTP 429/5xx and a DLQ.
8. In SES Configuration sets, add an enabled SNS event destination for Send, Delivery, DeliveryDelay, Bounce, Complaint, Reject and RenderingFailure. Optionally include Open/Click only when wanted. In SendPortal Email Services, ensure the SES `configuration_set_name` exactly matches that set. The existing host adapter already sends that field. Attaching a set in AWS alone does not attach it to application sends.
9. Ensure cron runs `php artisan schedule:run` every minute. `ses:process-events` recovers stored unprocessed events in batches, including notifications that arrived before `markSent()` saved the SES Message ID and events whose immediate queue dispatch failed. Unmatched events are retried for 30 days; monitor pending/old inbox entries and failed jobs. A 2xx response is issued only after persistence, not before.
10. Verify end to end with a controlled test campaign addressed only to an approved test mailbox / SES mailbox simulator. Do not replay real production complaint/bounce events against active subscribers for connectivity testing. Confirm the SNS subscription, queue consumption, DB timestamps and Campaign / Recipients search. Test SNS delivery failure/retry and DLQ handling. This runbook is not evidence that these production steps have occurred.

## Historical reconciliation (optional, automatic once enabled)

VDM must be enabled in the same SES account/region. Grant the campaign Email Service's existing AWS principal only the additional read permission `ses:GetMessageInsights` required for this operation. Do not grant full SES administration. Confirm API access before setting `SES_VDM_RECONCILE_ENABLED=true`, then rebuild configuration cache.

The scheduler runs `ses:reconcile-delivery --limit=25` every fifteen minutes when enabled. It uses the campaign Email Service's region/credentials, selects unresolved SES campaign messages sent in the last 30 days with a stored provider ID, and applies provider-returned events through the same pipeline. It never sends or retries an email. Open/click counts are not backfilled to avoid counting the same interactions through two sources. Delivered, bounced and complained messages are excluded from this unresolved-message backfill; future complaints still arrive through SNS.

Requests are serialized by a shared cache lock and spaced at least 1.1 seconds apart within a batch (API quota is one GetMessageInsights operation per second). Other applications/callers sharing the SES account must coordinate the same quota separately. Retries in the AWS client are disabled for this command; failed lookups are checked again no sooner than an hour. Maximum CLI batch size is 50. Use a shared cache in a multi-server deployment. Do not delete the inbox/state tables while jobs are active.

VDM availability and retention constrain recovery. Older messages or missing historical data remain unknown; do not infer delivery or resend mail. API query/other AWS costs follow the selected SES plan and current AWS pricing. SNS events are the ongoing source; VDM polling is optional recovery.

## UI and local validation

Campaign / Recipients preserves Subscriber, Subject, Delivered, Opened, Clicked, Bounced and Complained. It adds Status and Detail, displaying event timestamps with date, time and application timezone. The email search uses case-insensitive literal substring matching, is scoped to current workspace/campaign, keeps the search through pagination, and resets to page one on a new search. It reads the database; loading a report does not call AWS. An already-open page shows the latest database state on normal reload; no manual "check status" AWS action is required.

Focused tests:

```sh
DB_CONNECTION=sqlite DB_DATABASE=:memory: QUEUE_CONNECTION=sync vendor/bin/phpunit tests/Feature/Ses tests/Feature/Workspaces/CampaignSenderIntegrityTest.php tests/Feature/Api/TransactionalEmailControllerTest.php --do-not-cache-result
```

The older SES test `no_vendor_core_or_composer_manifest_was_changed` checks Git working-tree cleanliness of composer files. It temporarily reports a failure while this intentional new validator dependency is uncommitted; verify it again after committing the scoped manifest and lockfile. Existing dependency versions must remain unchanged.

AWS references: [SES SNS destination](https://docs.aws.amazon.com/ses/latest/dg/event-publishing-add-event-destination-sns.html), [SNS signature validation](https://docs.aws.amazon.com/sns/latest/dg/sns-verify-signature-of-message.html), [SNS retries](https://docs.aws.amazon.com/sns/latest/dg/sns-message-delivery-retries.html), [VDM message API](https://docs.aws.amazon.com/ses/latest/APIReference-V2/API_GetMessageInsights.html).
