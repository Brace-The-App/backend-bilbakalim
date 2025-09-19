<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions for tournaments (if not exists)
        Permission::firstOrCreate(['name' => 'view tournaments']);
        Permission::firstOrCreate(['name' => 'create tournaments']);
        Permission::firstOrCreate(['name' => 'edit tournaments']);
        Permission::firstOrCreate(['name' => 'delete tournaments']);

        // Create permissions for general settings (if not exists)
        Permission::firstOrCreate(['name' => 'view general settings']);
        Permission::firstOrCreate(['name' => 'create general settings']);
        Permission::firstOrCreate(['name' => 'edit general settings']);
        Permission::firstOrCreate(['name' => 'delete general settings']);
        
        // Permission management permissions
        Permission::firstOrCreate(['name' => 'view permissions']);
        Permission::firstOrCreate(['name' => 'create permissions']);
        Permission::firstOrCreate(['name' => 'edit permissions']);
        Permission::firstOrCreate(['name' => 'delete permissions']);
        
        // Notification management permissions
        Permission::firstOrCreate(['name' => 'view notifications']);
        Permission::firstOrCreate(['name' => 'create notifications']);
        Permission::firstOrCreate(['name' => 'edit notifications']);
        Permission::firstOrCreate(['name' => 'delete notifications']);

        // Assign permissions to roles
        $adminRole = Role::findByName('admin');
        $personelRole = Role::findByName('personel');

        // Admin gets all permissions
        $adminRole->givePermissionTo([
            'view tournaments', 'create tournaments', 'edit tournaments', 'delete tournaments',
            'view general settings', 'create general settings', 'edit general settings', 'delete general settings',
            'view permissions', 'create permissions', 'edit permissions', 'delete permissions',
            'view notifications', 'create notifications', 'edit notifications', 'delete notifications'
        ]);

        // Personel gets tournament and notification permissions
        $personelRole->givePermissionTo([
            'view tournaments', 'create tournaments', 'edit tournaments', 'delete tournaments',
            'view notifications', 'create notifications', 'edit notifications', 'delete notifications'
        ]);
    }
}
