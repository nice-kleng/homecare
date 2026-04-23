<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rekam medis per episode perawatan
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_number')->unique(); // RM-20240101-0001
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            // Dokter penanggung jawab
            $table->foreignId('doctor_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->string('diagnosis_primary')->nullable();     // diagnosis utama
            $table->text('diagnosis_secondary')->nullable();     // diagnosis penyerta
            $table->string('icd10_code', 20)->nullable();       // kode ICD-10

            $table->date('episode_start_date');
            $table->date('episode_end_date')->nullable();        // null = masih aktif

            $table->text('treatment_plan')->nullable();          // rencana pengobatan
            $table->text('doctor_instructions')->nullable();     // instruksi dokter ke petugas
            $table->text('diet_instruction')->nullable();        // instruksi diet
            $table->text('activity_restriction')->nullable();    // batasan aktivitas

            $table->enum('status', ['active', 'closed', 'referred'])->default('active');
            $table->text('closure_notes')->nullable();           // catatan penutupan episode

            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
        });

        // History perubahan kondisi pasien dari waktu ke waktu
        Schema::create('patient_progress_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();

            $table->text('progress_note');
            $table->enum('condition_trend', ['improving', 'stable', 'worsening'])->default('stable');
            $table->timestamp('noted_at');
            $table->timestamps();
        });

        // Resep / daftar obat yang diberikan dokter
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescribed_by')->constrained('staff')->cascadeOnDelete(); // dokter

            $table->string('drug_name');
            $table->string('dosage');           // dosis, misal: 500mg
            $table->string('frequency');        // frekuensi, misal: 3x sehari
            $table->string('route')->nullable(); // cara pemberian: oral, infus, topikal
            $table->integer('duration_days')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('patient_progress_notes');
        Schema::dropIfExists('medical_records');
    }
};
