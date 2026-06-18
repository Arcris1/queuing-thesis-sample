<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\AuthTokenData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuthTokenData
 */
class AuthTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuthTokenData $token */
        $token = $this->resource;

        return [
            'access_token' => $token->accessToken,
            'token_type' => $token->tokenType,
            'expires_in' => $token->expiresIn,
            'user' => new UserResource($token->user),
        ];
    }
}
