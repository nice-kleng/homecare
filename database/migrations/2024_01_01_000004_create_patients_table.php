<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // --- Identitas ---
            $table->string('no_rekam_medis')->unique()->nullable(); // auto-generate
            $table->string('nik', 16)->unique()->nullable();
            $table->string('name');
            $table->enum('gender', ['laki-laki', 'perempuan']);
            $table->date('birth_date');
            $table->string('birth_place')->nullable();
            $table->enum('blood_type', ['A', 'B', 'AB', 'O', 'unknown'])->default('unknown');
            $table->enum('marital_status', ['belum_menikah', 'menikah', 'cerai', 'duda', 'janda'])->nullable();
            $table->string('religion')->nullable();
            $table->string('occupation')->nullable();
            $table->string('education')->nullable();
            $table->string('phone', 20)->nullable();

            // --- Alamat Tinggal ---
            $table->text('address');
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->string('postal_code', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // --- Kontak Darurat ---
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relation')->nullable(); // anak, suami, istri, dll
            $table->string('emergency_contact_phone', 20)->nullable();

            // --- Asuransi / Jaminan ---
            $table->enum('insurance_type', ['umum', 'bpjs', 'asuransi_swasta', 'perusahaan'])->default('umum');
            $table->string('insurance_number')->nullable();
            $table->string('insurance_name')->nullable(); // nama asuransi swasta jika ada

            // --- Rekam Medis Awal ---
            $table->text('allergies')->nullable();             // alergi obat/makanan
            $table->text('chronic_diseases')->nullable();      // penyakit kronis
            $table->text('current_medications')->nullable();   // obat rutin
            $table->text('medical_notes')->nullable();         // catatan tambahan dokter

            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name', 'nik']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
