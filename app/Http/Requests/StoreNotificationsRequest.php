<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreNotificationsRequest extends FormRequest
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
            'batch' => ['nullable', 'array'],
            'batch.idempotency_key' => ['nullable', 'string', 'max:128'],
            'batch.correlation_id' => ['nullable', 'string', 'max:128'],
            'batch.metadata' => ['nullable', 'array'],
            'notifications' => ['required', 'array', 'min:1', 'max:1000'],
            'notifications.*.recipient' => ['required', 'string', 'max:255'],
            'notifications.*.channel' => ['required', 'string', 'in:' . implode(',', config('notifications.channels'))],
            'notifications.*.priority' => ['nullable', 'string', 'in:' . implode(',', config('notifications.priorities'))],
            'notifications.*.content' => ['required_without:notifications.*.template_id', 'string'],
            'notifications.*.template_id' => ['nullable', 'uuid'],
            'notifications.*.variables' => ['nullable', 'array'],
            'notifications.*.idempotency_key' => ['nullable', 'string', 'max:128'],
            'notifications.*.correlation_id' => ['nullable', 'string', 'max:128'],
            'notifications.*.scheduled_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $notifications = $this->input('notifications', []);
            $limits = config('notifications.content_limits');

            foreach ($notifications as $index => $notification) {
                $channel = $notification['channel'] ?? null;
                $content = $notification['content'] ?? '';
                $templateId = $notification['template_id'] ?? null;

                if (!$content && !$templateId) {
                    $validator->errors()->add(
                        "notifications.$index.content",
                        'Either content or template_id is required.'
                    );
                }

                if ($channel && isset($limits[$channel]) && mb_strlen($content) > $limits[$channel]) {
                    $validator->errors()->add(
                        "notifications.$index.content",
                        "Content exceeds {$limits[$channel]} characters for {$channel}."
                    );
                }
            }
        });
    }
}
