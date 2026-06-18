<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\Role;
use App\Http\Requests\Auth\RegisterRequest;

final readonly class RegisterData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public Role $role,
        public ?string $studentNo = null,
    ) {}

    public static function fromRequest(RegisterRequest $request): self
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return new self(
            name: (string) $validated['name'],
            email: (string) $validated['email'],
            password: (string) $validated['password'],
            role: Role::Student,
            studentNo: isset($validated['student_no']) ? (string) $validated['student_no'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'role' => $this->role,
            'student_no' => $this->studentNo,
        ];
    }
}
