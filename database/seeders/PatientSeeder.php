<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        $patientUser = User::create([
            'name' => 'Bpk. Ahmad Sujana',
            'email' => 'ahmad@gmail.com',
            'password' => Hash::make('password'),
            'status' => 'active'
        ]);
        $patientUser->assignRole('pasien');

        Patient::create([
            'user_id' => $patientUser->id,
            'no_rekam_medis' => 'RM-2026-0001',
            'nik' => '3171234567890003',
            'name' => 'Bpk. Ahmad Sujana',
            'gender' => 'laki-laki',
            'birth_date' => '1965-05-20',
            'phone' => '085566778899',
            'address' => 'Jl. Tebet Raya No. 12',
            'city_id' => 1,
            'status' => 'active'
        ]);
    }
}
