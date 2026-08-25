<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => config('pos.admin_email')],
            [
                'full_name' => 'System Admin',
                'password_hash' => Hash::make((string) config('pos.admin_password')),
                'must_change_password' => true,
            ],
        );

        $admin->assignRole('Admin');
    }
}
