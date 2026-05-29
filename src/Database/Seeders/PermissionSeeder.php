<?php

namespace Nawasara\Registry\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'registry.opd.view',
            'registry.opd.manage',
            'registry.pic.view',
            'registry.pic.manage',
            'registry.asset.view',
            'registry.asset.manage',
            'registry.export.use',
            'registry.membership.manage', // link users to their OPD (cross-package)
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $role = Role::where('name', 'developer')->first();

        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
}
