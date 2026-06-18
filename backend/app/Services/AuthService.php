<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\AuthTokenData;
use App\DTOs\LoginData;
use App\DTOs\RegisterData;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

final class AuthService
{
    public function __construct(
        private readonly JWTGuard $guard,
    ) {}

    /**
     * Register a new user and issue a JWT for them.
     */
    public function register(RegisterData $data): AuthTokenData
    {
        $user = DB::transaction(
            fn (): User => User::create($data->toAttributes()),
        );

        $token = $this->guard->login($user);

        return $this->tokenData($token, $user);
    }

    /**
     * Attempt to authenticate the given credentials.
     *
     * @throws InvalidCredentialsException
     */
    public function login(LoginData $data): AuthTokenData
    {
        $token = $this->guard->attempt($data->credentials());

        if ($token === false) {
            throw new InvalidCredentialsException();
        }

        /** @var User $user */
        $user = $this->guard->user();

        return $this->tokenData($token, $user);
    }

    /**
     * Invalidate the currently authenticated token.
     */
    public function logout(): void
    {
        $this->guard->logout();
    }

    /**
     * Store (or clear) the user's FCM device token for push delivery (task 020).
     */
    public function updateFcmToken(User $user, ?string $token): User
    {
        $user->update(['fcm_token' => $token]);

        return $user;
    }

    private function tokenData(string $token, User $user): AuthTokenData
    {
        return new AuthTokenData(
            accessToken: $token,
            expiresIn: $this->guard->factory()->getTTL() * 60,
            user: $user,
        );
    }
}
