<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:128'],
            'channel' => ['sometimes', 'string', 'in:' . implode(',', config('notifications.channels'))],
            'content' => ['sometimes', 'string'],
            'default_variables' => ['sometimes', 'array'],
        ];
    }
}
