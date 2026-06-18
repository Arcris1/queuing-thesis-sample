<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Office → Queue Group → Service hierarchy + Windows (plan §5.1 / §5.2).
        $this->call(OfficeServiceSeeder::class);

        // Baseline accounts for development / demos.
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'student_no' => null,
        ]);

        User::factory()->staff()->create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'student_no' => null,
        ]);

        User::factory()->student()->create([
            'name' => 'Test Student',
            'email' => 'student@example.com',
            'student_no' => '2026-00001',
        ]);
    }
}
