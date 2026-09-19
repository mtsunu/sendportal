<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TransactionalEmailRequest;
use App\Mail\TransactionalEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class TransactionalEmailController extends Controller
{
    public function store(TransactionalEmailRequest $request): JsonResponse
    {
        $data = $request->validated();
        $mail = Mail::to($data['to']);

        if (! empty($data['cc'])) {
            $mail->cc($data['cc']);
        }

        if (! empty($data['bcc'])) {
            $mail->bcc($data['bcc']);
        }

        $mail->queue(new TransactionalEmail(
            emailSubject: $data['subject'],
            body: $data['body'],
            htmlBody: $data['html'] ?? null,
        ));

        return response()->json([
            'status' => 'queued',
            'message' => 'Transactional email queued.',
        ], Response::HTTP_ACCEPTED);
    }
}
