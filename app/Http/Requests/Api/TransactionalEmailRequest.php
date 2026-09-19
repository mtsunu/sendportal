<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TransactionalEmailRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['cc', 'bcc'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([
                    $field => [$this->input($field)],
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'to' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'html' => ['nullable', 'string'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['email', 'max:255'],
            'bcc' => ['nullable', 'array'],
            'bcc.*' => ['email', 'max:255'],
        ];
    }
}
