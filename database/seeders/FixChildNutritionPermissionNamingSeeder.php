<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\DB;

class FixChildNutritionPermissionNamingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        $permissions = [
            'malnourished_children.view' => 'child_nutrition_monitoring.view',
            'malnourished_children.create' => 'child_nutrition_monitoring.create',
            'malnourished_children.edit' => 'child_nutrition_monitoring.edit',
            'malnourished_children.delete' => 'child_nutrition_monitoring.delete',
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
