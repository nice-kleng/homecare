<?php

namespace Database\Seeders;

use App\Models\Staff;
use App\Models\User;
use App\Models\Specialization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $spDokter = Specialization::where('code', 'DU')->first();
        $spPerawat = Specialization::where('code', 'PWT')->first();

        // Dokter
        $dokterUser = User::create([
            'name' => 'dr. Sarah Amelia',
            'email' => 'sarah@homecare.com',
            'password' => Hash::make('password'),
            'status' => 'active'
        ]);
        $dokterUser->assignRole('dokter');

        Staff::create([
            'user_id' => $dokterUser->id,
            'specialization_id' => $spDokter->id,
            'employee_id' => 'STF-DR-001',
            'nik' => '3171234567890001',
            'gender' => 'perempuan',
            'phone' => '081234567890',
            // 'city_id' => 1, // South Jakarta
            'status' => 'active'
        ]);

        // Perawat
        $perawatUser = User::create([
            'name' => 'Budi Santoso, S.Kep',
            'email' => 'budi@homecare.com',
            'password' => Hash::make('password'),
            'status' => 'active'
        ]);
        $perawatUser->assignRole('petugas');

        Staff::create([
            'user_id' => $perawatUser->id,
            'specialization_id' => $spPerawat->id,
            'employee_id' => 'STF-NS-001',
            'nik' => '3171234567890002',
            'gender' => 'laki-laki',
            'phone' => '089876543210',
            // 'city_id' => 1,
            'status' => 'active'
        ]);
    }
}
