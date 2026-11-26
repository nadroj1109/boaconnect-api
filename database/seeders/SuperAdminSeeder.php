<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Constants\Roles;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@boaconnect.com'],
            [
                'name' => 'Super Administrator',
                'password' => bcrypt('password')
            ]
        );

        $user->assignRole(Roles::SUPER_ADMIN);
    }
}
