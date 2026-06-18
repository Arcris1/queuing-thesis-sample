<?php

declare(strict_types=1);

namespace App\Http\Requests\Heartbeat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the periodic heartbeat (task 015, plan §9). Everything is optional so
 * the ping stays cheap; coordinates, when present, are raw GPS only — distance is
 * always decided server-side (plan §8), never trusted from the client.
 */
class HeartbeatRequest extends FormRequest
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
            'battery_level' => ['sometimes', 'integer', 'between:0,100'],
            'network_status' => ['sometimes', 'string', 'max:32'],
            // Coordinates are paired: if one is sent the other is required so a
            // heartbeat can cleanly double as a location ping. `required_with` is
            // listed first (not behind `sometimes`) so the pairing is enforced even
            // when only one coordinate is present.
            'latitude' => ['required_with:longitude', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_with:latitude', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'battery_level.between' => 'Battery level must be between 0 and 100.',
            'latitude.between' => 'Latitude must be between -90 and 90 degrees.',
            'longitude.between' => 'Longitude must be between -180 and 180 degrees.',
        ];
    }
}
