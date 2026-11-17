<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate([
            'email' => 'beadsPatternAdminUser@gmail.com',
            'login' => 'BeadsPatternAdminUser',
        ], [
            'name' => 'Admin User',
            'firstName' => 'Admin',
            'lastName' => 'User',
            'password' => Hash::make('BeadsPatternAdminUserPassword_123'),
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);
    }
}
