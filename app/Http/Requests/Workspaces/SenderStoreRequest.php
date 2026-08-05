<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SenderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentWorkspace() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'label' => trim((string) $this->input('label')),
            'from_name' => trim((string) $this->input('from_name')),
            'from_email' => strtolower(trim((string) $this->input('from_email'))),
        ]);
    }

    public function rules(): array
    {
        $workspaceId = $this->user()?->currentWorkspace()?->id;

        return [
            'label' => ['required', 'string', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'from_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('senders', 'from_email')->where(
                    fn ($query) => $query
                        ->where('workspace_id', $workspaceId)
                        ->where('from_name', $this->input('from_name'))
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => __('The label field is required.'),
            'from_name.required' => __('The From Name field is required.'),
            'from_email.email' => __('Enter a valid email address.'),
            'from_email.unique' => __('This sender already exists in the current workspace.'),
        ];
    }
}
