<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_student_and_returns_jwt(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Jane Student',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'student_no' => '2026-12345',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                    'user' => ['id', 'name', 'email', 'student_no', 'role', 'role_label'],
                ],
            ])
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.user.role', Role::Student->value)
            ->assertJsonMissingPath('data.user.password');

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'student_no' => '2026-12345',
            'role' => Role::Student->value,
        ]);

        $this->assertNotEmpty($response->json('data.access_token'));
    }

    public function test_register_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/register', [
            'name' => 'Dupe',
            'email' => 'taken@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_register_requires_password_confirmation(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Mismatch',
            'email' => 'mismatch@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Different123!',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_login_with_valid_credentials_returns_token(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('secret-pass-123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'secret-pass-123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['access_token', 'token_type', 'expires_in', 'user' => ['id', 'email']],
            ])
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.user.email', 'login@example.com');

        $this->assertIsInt($response->json('data.expires_in'));
        $this->assertGreaterThan(0, $response->json('data.expires_in'));
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('correct-pass'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-pass',
        ])->assertUnauthorized();
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_logout_invalidates_token(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertNoContent();

        // The invalidated token can no longer access a protected route.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertUnauthorized();
    }
}
