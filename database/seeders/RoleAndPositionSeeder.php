<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            'manage-catalog',
            'manage-ops',
            'manage-team',
            'view-reports',
            'pos-access',
            'kitchen-access',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Create Roles
        $superadmin = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $customer = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        // Assign Permissions to Roles
        $superadmin->syncPermissions($permissions);
        $admin->syncPermissions($permissions);

        // Create Positions (Jabatan) and assign their permissions
        $positions = [
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Restaurant manager overseeing operations.',
                'starting_page' => '/reports',
            ],
            [
                'name' => 'Cashier',
                'slug' => 'cashier',
                'description' => 'Responsible for payments and transactions.',
                'starting_page' => '/pos',
            ],
            [
                'name' => 'Chef',
                'slug' => 'chef',
                'description' => 'Head of kitchen and food preparation.',
                'starting_page' => '/kitchen',
            ],
            [
                'name' => 'Waiter',
                'slug' => 'waiter',
                'description' => 'Serving customers and taking orders.',
                'starting_page' => '/pos',
            ],
        ];

        foreach ($positions as $pos) {
            $position = Position::updateOrCreate(['slug' => $pos['slug']], $pos);

            if ($pos['slug'] === 'manager') {
                $position->syncPermissions($permissions);
            } elseif ($pos['slug'] === 'cashier') {
                $position->syncPermissions(['pos-access']);
            } elseif ($pos['slug'] === 'chef') {
                $position->syncPermissions(['kitchen-access']);
            } elseif ($pos['slug'] === 'waiter') {
                $position->syncPermissions(['pos-access']);
            }
        }
    }
}
