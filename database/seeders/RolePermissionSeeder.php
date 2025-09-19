<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User management
            'view users',
            'create users',
            'edit users',
            'delete users',
            
            // Question management
            'view questions',
            'create questions',
            'edit questions',
            'delete questions',
            
            // Category management
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',
            
            // Tournament management
            'view tournaments',
            'create tournaments',
            'edit tournaments',
            'delete tournaments',
            'participate tournaments',
            
            // Package management
            'view packages',
            'create packages',
            'edit packages',
            'delete packages',
            
            // Award management
            'view awards',
            'create awards',
            'edit awards',
            'delete awards',
            'claim awards',
            
            // Reports and analytics
            'view reports',
            'view analytics',
            
            // System settings
            'manage settings',
            'manage translations',
            
            // Quiz playing
            'play quiz',
            'view leaderboard',
            'use jokers',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        
        // Admin Role - Full access
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions(Permission::all());

        // Staff Role - Management access but limited system settings
        $staffRole = Role::firstOrCreate(['name' => 'personel']);
        $staffRole->syncPermissions([
            'view users',
            'edit users',
            'view questions',
            'create questions',
            'edit questions',
            'delete questions',
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',
            'view tournaments',
            'create tournaments',
            'edit tournaments',
            'delete tournaments',
            'view packages',
            'view awards',
            'create awards',
            'edit awards',
            'view reports',
            'view analytics',
            'manage translations',
        ]);

        // Member Role - Basic user permissions
        $memberRole = Role::firstOrCreate(['name' => 'uye']);
        $memberRole->syncPermissions([
            'play quiz',
            'view leaderboard',
            'use jokers',
            'participate tournaments',
            'view awards',
            'claim awards',
        ]);

        // Create default admin user if doesn't exist
        $adminUser = User::where('email', 'admin@bilbakalim.com')->first();
        if (!$adminUser) {
            $adminUser = new User();
            $adminUser->name = 'Admin';
            $adminUser->email = 'admin@bilbakalim.com';
            $adminUser->password = bcrypt('password');
            $adminUser->role_id = 1;
            $adminUser->account_status = 'active';
            $adminUser->total_coins = 1000;
            $adminUser->save();
        }
        $adminUser->assignRole('admin');

        echo "Roles and permissions created successfully!\n";
        echo "Default admin user: admin@bilbakalim.com / password\n";
    }
}
