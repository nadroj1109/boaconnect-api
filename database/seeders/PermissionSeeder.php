<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use App\Constants\Permissions;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (Permissions::all() as $permission) {

            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => config('auth.defaults.guard'),
            ]);

        }
    }
}
