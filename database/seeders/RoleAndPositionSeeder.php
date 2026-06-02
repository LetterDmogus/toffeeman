<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;
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

        // Create Roles
        Role::firstOrCreate(['name' => 'superadmin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'user']);
        Role::firstOrCreate(['name' => 'customer']);

        // Create Positions (Jabatan)
        $positions = [
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Restaurant manager overseeing operations.',
            ],
            [
                'name' => 'Cashier',
                'slug' => 'cashier',
                'description' => 'Responsible for payments and transactions.',
            ],
            [
                'name' => 'Chef',
                'slug' => 'chef',
                'description' => 'Head of kitchen and food preparation.',
            ],
            [
                'name' => 'Waiter',
                'slug' => 'waiter',
                'description' => 'Serving customers and taking orders.',
            ],
        ];

        foreach ($positions as $pos) {
            Position::firstOrCreate(['slug' => $pos['slug']], $pos);
        }
    }
}
