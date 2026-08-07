<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Observers\CampaignObserver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttachSenderCaptureWarningHeader
{
    private const WARNING_HEADER = 'The campaign was saved, but its sender could not be saved automatically.';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->attributes->get(CampaignObserver::WARNING_ATTRIBUTE) === true) {
            $response->headers->set('X-SendPortal-Warning', self::WARNING_HEADER);
        }

        return $response;
    }
}
