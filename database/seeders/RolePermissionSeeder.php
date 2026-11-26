<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Constants\RolePermissions;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (RolePermissions::matrix() as $roleName => $permissions) {

            $role = Role::findByName(
                $roleName,
                config('auth.defaults.guard')
            );

            $role->syncPermissions($permissions);
        }
    }
}
