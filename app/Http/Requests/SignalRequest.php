<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSdpBytes = (int) config('videochat.signal_sdp_max_bytes');

        return [
            'room_uuid' => ['required', 'uuid'],
            'sequence' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:offer,answer,ice-candidate,hangup,media-state,ice-restart'],
            'payload' => ['required', 'array'],
            'payload.sdp' => ['sometimes', 'string', 'max:'.$maxSdpBytes],
            'payload.candidate' => ['sometimes', 'array'],
            'payload.candidate.candidate' => ['sometimes', 'string', 'max:2500'],
            'payload.audio' => ['sometimes', 'boolean'],
            'payload.video' => ['sometimes', 'boolean'],
        ];
    }
}
