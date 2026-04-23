<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Specialization;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        // Specializations
        $spDokterUmum = Specialization::create(['name' => 'Dokter Umum', 'code' => 'DU', 'description' => 'Dokter Pelayanan Primer']);
        $spPerawat = Specialization::create(['name' => 'Perawat Medis', 'code' => 'PWT', 'description' => 'Perawat Berlisensi']);
        $spFisioterapis = Specialization::create(['name' => 'Fisioterapis', 'code' => 'FST', 'description' => 'Terapi Fisik']);

        // Categories
        $catVisit = ServiceCategory::create([
            'name' => 'Home Visit',
            'icon' => 'Home',
            'color' => 'teal',
            'description' => 'Kunjungan dokter atau tenaga medis ke rumah'
        ]);

        $catNursing = ServiceCategory::create([
            'name' => 'Nursing Care',
            'icon' => 'Activity',
            'color' => 'blue',
            'description' => 'Perawatan intensif oleh perawat'
        ]);

        // Services
        Service::create([
            'service_category_id' => $catVisit->id,
            'specialization_id' => $spDokterUmum->id,
            'code' => 'SVC-DU-01',
            'name' => 'Konsultasi Dokter Umum',
            'description' => 'Pemeriksaan kesehatan rutin oleh dokter umum',
            'duration_minutes' => 45,
            'base_price' => 150000,
            'transport_fee' => 50000,
            'is_active' => true
        ]);

        Service::create([
            'service_category_id' => $catNursing->id,
            'specialization_id' => $spPerawat->id,
            'code' => 'SVC-LUKA-01',
            'name' => 'Perawatan Luka Pasca Operasi',
            'description' => 'Ganti perban dan pembersihan luka steril',
            'duration_minutes' => 30,
            'base_price' => 100000,
            'transport_fee' => 30000,
            'is_active' => true
        ]);

        Service::create([
            'service_category_id' => $catVisit->id,
            'specialization_id' => $spFisioterapis->id,
            'code' => 'SVC-FISIO-01',
            'name' => 'Sesi Fisioterapi (Stroke)',
            'description' => 'Latihan gerak untuk pasien pasca stroke',
            'duration_minutes' => 60,
            'base_price' => 250000,
            'transport_fee' => 50000,
            'is_active' => true
        ]);
    }
}
