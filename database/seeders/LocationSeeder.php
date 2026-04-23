<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $provinceId = DB::table('provinces')->insertGetId([
            'code' => '31',
            'name' => 'DKI JAKARTA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'code' => '3171',
            'name' => 'JAKARTA SELATAN',
            'type' => 'kota',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $districtId = DB::table('districts')->insertGetId([
            'city_id' => $cityId,
            'code' => '3171010',
            'name' => 'TEBET',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('villages')->insert([
            'district_id' => $districtId,
            'code' => '3171010001',
            'name' => 'TEBET BARAT',
            'postal_code' => '12810',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
