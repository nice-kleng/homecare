<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // ORD-20240101-0001

            // --- Relasi Utama ---
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_package_id')->nullable()->constrained()->nullOnDelete();

            // Siapa yang memesan (bisa pasien sendiri / keluarga)
            $table->foreignId('ordered_by')->constrained('users')->cascadeOnDelete();

            // --- Jadwal Kunjungan ---
            $table->date('visit_date');
            $table->time('visit_time_start');         // jam mulai yang diminta
            $table->time('visit_time_end')->nullable(); // jam selesai estimasi

            // --- Alamat Kunjungan (bisa beda dengan alamat pasien) ---
            $table->text('visit_address');
            $table->string('visit_rt', 5)->nullable();
            $table->string('visit_rw', 5)->nullable();
            $table->foreignId('visit_village_id')->nullable()->constrained('villages')->nullOnDelete();
            $table->foreignId('visit_district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->foreignId('visit_city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('visit_postal_code', 10)->nullable();
            $table->decimal('visit_latitude', 10, 7)->nullable();
            $table->decimal('visit_longitude', 10, 7)->nullable();
            $table->text('visit_address_notes')->nullable(); // patokan / catatan lokasi

            // --- Medis ---
            $table->text('chief_complaint')->nullable();    // keluhan utama
            $table->text('medical_notes')->nullable();      // catatan medis dari pemesan
            $table->string('referral_document')->nullable(); // path file surat rujukan

            // --- Status ---
            /**
             * pending      : order masuk, menunggu konfirmasi admin
             * confirmed    : dikonfirmasi admin, menunggu penugasan petugas
             * assigned     : petugas sudah ditugaskan
             * in_progress  : kunjungan sedang berlangsung
             * completed    : kunjungan selesai
             * cancelled    : dibatalkan
             * rescheduled  : dijadwalkan ulang
             */
            $table->enum('status', [
                'pending', 'confirmed', 'assigned',
                'in_progress', 'completed', 'cancelled', 'rescheduled'
            ])->default('pending');

            $table->enum('source', ['web', 'app', 'phone', 'walk_in'])->default('web');

            // --- Catatan Admin ---
            $table->text('admin_notes')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();

            // Jika order ini adalah rescheduled dari order sebelumnya
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
            $table->index(['visit_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
