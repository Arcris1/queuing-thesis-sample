<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\User;

final readonly class AuthTokenData
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
        public User $user,
        public string $tokenType = 'bearer',
    ) {}
}
