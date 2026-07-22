<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_uuid' => ['required', 'uuid'],
            'reason' => ['required', 'in:nudity_or_sexual_content,harassment_or_threats,hate_speech,suspected_minor,spam_or_scam,dangerous_or_illegal_behavior,other'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
