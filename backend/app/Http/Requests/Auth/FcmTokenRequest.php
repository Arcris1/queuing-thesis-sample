<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Store the authenticated user's FCM device token (task 020) so push can reach
 * them when the app is backgrounded/offline. Sent null to clear (e.g. logout on
 * device). Auth is the route's `auth:api` guard — any logged-in user owns their
 * own token.
 */
class FcmTokenRequest extends FormRequest
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
            'fcm_token' => ['present', 'nullable', 'string', 'max:512'],
        ];
    }

    public function fcmToken(): ?string
    {
        /** @var string|null $token */
        $token = $this->validated('fcm_token');

        return $token;
    }
}
