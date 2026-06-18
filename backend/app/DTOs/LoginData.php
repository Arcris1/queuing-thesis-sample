<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Http\Requests\Auth\LoginRequest;

final readonly class LoginData
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}

    public static function fromRequest(LoginRequest $request): self
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return new self(
            email: (string) $validated['email'],
            password: (string) $validated['password'],
        );
    }

    /**
     * Credentials array suitable for the JWT guard's attempt().
     *
     * @return array<string, string>
     */
    public function credentials(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
        ];
    }
}
