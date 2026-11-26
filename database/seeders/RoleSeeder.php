<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Constants\Roles;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (Roles::all() as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => config('auth.defaults.guard'),
            ]);
        }
    }
}
