<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();

            // --- Tracking Waktu ---
            $table->timestamp('scheduled_at');           // jadwal kunjungan
            $table->timestamp('departed_at')->nullable(); // waktu berangkat
            $table->timestamp('arrived_at')->nullable();  // waktu tiba (check-in)
            $table->timestamp('started_at')->nullable();  // waktu mulai tindakan
            $table->timestamp('completed_at')->nullable();// waktu selesai tindakan (check-out)

            // --- Lokasi Check-in / Check-out ---
            $table->decimal('checkin_latitude', 10, 7)->nullable();
            $table->decimal('checkin_longitude', 10, 7)->nullable();
            $table->decimal('checkout_latitude', 10, 7)->nullable();
            $table->decimal('checkout_longitude', 10, 7)->nullable();

            // --- Status Kunjungan ---
            /**
             * scheduled    : sudah dijadwalkan
             * on_the_way   : petugas dalam perjalanan
             * arrived      : petugas tiba di lokasi
             * in_progress  : tindakan sedang dilakukan
             * completed    : selesai
             * no_show      : pasien tidak ada / tidak bisa ditemui
             * cancelled    : dibatalkan
             */
            $table->enum('status', [
                'scheduled', 'on_the_way', 'arrived',
                'in_progress', 'completed', 'no_show', 'cancelled'
            ])->default('scheduled');

            // --- Catatan Tindakan (SOAP) ---
            // Subjective: keluhan yang disampaikan pasien
            $table->text('soap_subjective')->nullable();
            // Objective: hasil pemeriksaan fisik / observasi
            $table->text('soap_objective')->nullable();
            // Assessment: analisa / diagnosa petugas
            $table->text('soap_assessment')->nullable();
            // Plan: rencana tindak lanjut
            $table->text('soap_plan')->nullable();

            // --- Vital Signs ---
            $table->decimal('vital_temperature', 4, 1)->nullable();    // suhu tubuh (°C)
            $table->integer('vital_pulse')->nullable();                 // nadi (x/menit)
            $table->integer('vital_respiration')->nullable();           // pernapasan (x/menit)
            $table->string('vital_blood_pressure', 10)->nullable();     // tensi (120/80)
            $table->integer('vital_oxygen_saturation')->nullable();     // SpO2 (%)
            $table->decimal('vital_weight', 5, 2)->nullable();         // berat badan (kg)
            $table->decimal('vital_blood_sugar', 6, 2)->nullable();    // gula darah (mg/dL)
            $table->text('vital_notes')->nullable();

            // --- Tindakan & Dokumentasi ---
            $table->text('actions_performed')->nullable(); // tindakan yang dilakukan (free text)
            $table->text('medications_given')->nullable(); // obat yang diberikan
            $table->text('consumables_used')->nullable();  // BHP / alat habis pakai
            $table->text('next_visit_recommendation')->nullable(); // saran kunjungan berikutnya

            // --- Validasi Dokter ---
            $table->boolean('is_validated')->default(false);
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_notes')->nullable();

            // --- Tanda Tangan Digital ---
            $table->string('patient_signature')->nullable();  // path gambar tanda tangan
            $table->string('staff_signature')->nullable();

            // --- Rating Pasien ---
            $table->tinyInteger('rating')->nullable();        // 1-5
            $table->text('rating_comment')->nullable();
            $table->timestamp('rated_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['staff_id', 'scheduled_at']);
            $table->index(['order_id', 'status']);
        });

        // Dokumentasi foto kunjungan (kondisi luka, dll.)
        Schema::create('visit_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type', 50)->nullable();        // image, pdf
            $table->enum('document_type', [
                'foto_kondisi',
                'foto_tindakan',
                'foto_obat',
                'foto_alat',
                'laporan',
                'lainnya'
            ])->default('lainnya');
            $table->text('caption')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_documents');
        Schema::dropIfExists('visits');
    }
};
