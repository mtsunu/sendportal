<?php

declare(strict_types=1);

use App\Http\Controllers\Api\TransactionalEmailController;
use App\Http\Middleware\AttachSenderCaptureWarningHeader;
use App\Http\Middleware\RequireWorkspace;
use Illuminate\Support\Facades\Route;
use Sendportal\Base\Facades\Sendportal;

Route::middleware([
    config('sendportal-host.throttle_middleware'),
    RequireWorkspace::class,
    AttachSenderCaptureWarningHeader::class,
])->group(function () {
    // Auth'd API routes (workspace-level auth!).
    Route::post('v1/notifications/email', [TransactionalEmailController::class, 'store'])
        ->name('sendportal.api.notifications.email');

    Sendportal::apiRoutes();
});

// Non-auth'd API routes.
Sendportal::publicApiRoutes();
