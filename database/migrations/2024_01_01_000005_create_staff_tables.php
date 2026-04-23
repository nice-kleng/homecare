<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Spesialisasi / profesi medis
        Schema::create('specializations', function (Blueprint $table) {
            $table->id();
            $table->string('name');           // Perawat, Bidan, Fisioterapis, Dokter Umum, dll
            $table->string('code')->unique(); // NRS, BDN, FTP, DKT
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Data petugas medis / dokter
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialization_id')->constrained()->cascadeOnDelete();

            // --- Identitas Profesional ---
            $table->string('employee_id')->unique();    // ID pegawai internal
            $table->string('str_number')->nullable();   // Surat Tanda Registrasi
            $table->date('str_expired_at')->nullable();
            $table->string('sip_number')->nullable();   // Surat Izin Praktik
            $table->date('sip_expired_at')->nullable();

            // --- Info Personal ---
            $table->string('nik', 16)->nullable()->unique();
            $table->enum('gender', ['laki-laki', 'perempuan']);
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20)->nullable();

            // --- Operasional ---
            $table->decimal('service_radius_km', 5, 2)->default(15.00); // radius layanan
            $table->decimal('latitude', 10, 7)->nullable();             // lokasi rumah/base
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('max_visits_per_day')->default(6);
            $table->enum('status', ['active', 'inactive', 'cuti', 'off_duty'])->default('active');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // Jadwal kerja petugas (shift mingguan)
        Schema::create('staff_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->enum('day_of_week', ['monday','tuesday','wednesday','thursday','friday','saturday','sunday']);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['staff_id', 'day_of_week']);
        });

        // Cuti / libur petugas
        Schema::create('staff_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->date('leave_date');
            $table->string('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['staff_id', 'leave_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leaves');
        Schema::dropIfExists('staff_schedules');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('specializations');
    }
};
