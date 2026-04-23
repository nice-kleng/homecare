<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create initial permissions (examples)
        $permissions = [
            'view dashboard',
            'manage users',
            'manage patients',
            'manage staff',
            'manage orders',
            'view medical records',
            'manage billing',
            'perform visits',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // Create roles and assign permissions
        $admin = Role::findOrCreate('admin');
        $admin->givePermissionTo(Permission::all());

        $dokter = Role::findOrCreate('dokter');
        $dokter->givePermissionTo(['view dashboard', 'manage patients', 'view medical records', 'perform visits']);

        $petugas = Role::findOrCreate('petugas');
        $petugas->givePermissionTo(['view dashboard', 'manage patients', 'perform visits']);

        $pasien = Role::findOrCreate('pasien');
        $pasien->givePermissionTo(['view dashboard', 'manage orders']);

        // Create a default admin user
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@homecare.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $adminUser->assignRole($admin);

        $this->command->info('Roles, permissions, and admin user seeded successfully!');
    }
}
