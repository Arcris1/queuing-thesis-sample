<?php

declare(strict_types=1);

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a QR check-in (task 014).
 *
 * QR PAYLOAD CONTRACT
 * -------------------
 * The office prints/displays a QR code that encodes a JSON payload:
 *
 *     {"t":"qms-checkin","ticket_number":"A-007"}
 *
 * The mobile app decodes the QR, extracts `ticket_number`, and posts it here
 * together with the device's raw GPS coordinates. The server then proves the
 * ticket belongs to the authenticated student and that the device is within the
 * office radius (Haversine, server-side) before marking arrival. For the
 * student self-check-in flow the identifier is simply the human-readable ticket
 * number; a signed/expiring token can replace it later without changing this
 * endpoint's shape. The `t` envelope/decoding is a client concern — the API
 * contract is just `{ ticket_number, latitude, longitude }`.
 */
class CheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ticket_number' => ['required', 'string', 'max:32'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ticket_number.required' => 'A scanned ticket number is required.',
            'latitude.between' => 'Latitude must be between -90 and 90 degrees.',
            'longitude.between' => 'Longitude must be between -180 and 180 degrees.',
        ];
    }
}
