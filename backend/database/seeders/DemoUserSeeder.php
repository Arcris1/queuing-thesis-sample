<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Known demo login accounts for the local Docker deployment. Idempotent — safe
 * to run on every boot. Password for all three is "password".
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'Admin User', 'email' => 'admin@smartqueue.test', 'role' => Role::Admin, 'student_no' => null],
            ['name' => 'Staff User', 'email' => 'staff@smartqueue.test', 'role' => Role::Staff, 'student_no' => null],
            ['name' => 'Student User', 'email' => 'student@smartqueue.test', 'role' => Role::Student, 'student_no' => '2026-0001'],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'role' => $account['role'],
                    'student_no' => $account['student_no'],
                    'password' => Hash::make('password'),
                ],
            );
        }
    }
}
