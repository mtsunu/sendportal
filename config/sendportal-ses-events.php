<?php

declare(strict_types=1);

return [
    // One trusted SNS topic belongs to one workspace. Add further mappings here
    // when independent workspaces use different SES event destinations.
    'topics' => env('SES_SNS_TOPIC_ARN') ? [
        env('SES_SNS_TOPIC_ARN') => (int) env('SES_SNS_WORKSPACE_ID', 0),
    ] : [],

    'queue' => 'sendportal-webhook-process',
    'reconcile_enabled' => (bool) env('SES_VDM_RECONCILE_ENABLED', false),
];
