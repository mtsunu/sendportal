<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TransactionalEmailRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'to' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'html' => ['nullable', 'string'],
        ];
    }
}
