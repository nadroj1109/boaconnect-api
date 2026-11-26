<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\DB;

class FixPrenatalPermissionSpellingSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        $permissions = [
            'prenetal_monitoring.view' => 'prenatal_monitoring.view',
            'prenetal_monitoring.create' => 'prenatal_monitoring.create',
            'prenetal_monitoring.edit' => 'prenatal_monitoring.edit',
            'prenetal_monitoring.delete' => 'prenatal_monitoring.delete',
        ];

        DB::transaction(function () use ($permissions, $guard) {
            foreach ($permissions as $oldName => $newName) {
                $oldPermission = Permission::where('name', $oldName)
                    ->where('guard_name', $guard)
                    ->first();

                if (! $oldPermission) {
                    continue;
                }

                $newPermission = Permission::firstOrCreate([
                    'name' => $newName,
                    'guard_name' => $guard,
                ]);

                // Transfer existing role assignments.
                foreach ($oldPermission->roles as $role) {
                    $role->givePermissionTo($newPermission);
                    $role->revokePermissionTo($oldPermission);
                }

                $oldPermission->delete();
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
