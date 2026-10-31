<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::transaction(function () {
            foreach (array_keys(config('posyandu.permissions')) as $name) {
                Permission::findOrCreate($name, 'web');
            }
            foreach (config('posyandu.role_permissions') as $name => $permissions) {
                $role = Role::findOrCreate($name, 'web');
                $current = $role->permissions()->pluck('name')->sort()->values()->all();
                $desired = collect($permissions)->sort()->values()->all();
                if ($current !== $desired) {
                    $role->syncPermissions($permissions);
                    AuditLog::create([
                        'user_id' => null,
                        'action' => 'permission_matrix.applied',
                        'resource_type' => 'Role',
                        'resource_id' => $role->id,
                        'reason' => 'Aktivasi matriks administratif '.config('posyandu.permission_matrix_version'),
                        'created_at' => now(),
                    ]);
                }
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
