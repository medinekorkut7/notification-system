<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListNotificationsRequest extends FormRequest
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
            'status' => ['nullable', 'string'],
            'channel' => ['nullable', 'string', 'in:' . implode(',', config('notifications.channels'))],
            'priority' => ['nullable', 'string', 'in:' . implode(',', config('notifications.priorities'))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'batch_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
