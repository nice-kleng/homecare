<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kategori layanan
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');             // Perawatan Luka, Terapi, Kebidanan, Lab, dll
            $table->string('icon')->nullable();  // nama ikon / path gambar
            $table->string('color', 10)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Layanan individual
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialization_id')->nullable()->constrained()->nullOnDelete(); // butuh spesialisasi apa

            $table->string('code')->unique();   // SVC-001
            $table->string('name');             // Perawatan Luka Operasi
            $table->text('description')->nullable();
            $table->text('procedure_notes')->nullable(); // SOP / catatan prosedur
            $table->integer('duration_minutes')->default(60); // estimasi durasi
            $table->decimal('base_price', 12, 2)->default(0);
            $table->decimal('transport_fee', 10, 2)->default(0); // biaya transport default

            // Apakah memerlukan referral dokter?
            $table->boolean('requires_referral')->default(false);
            // Apakah termasuk obat/alat habis pakai?
            $table->boolean('includes_consumables')->default(false);

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Paket layanan (bundling beberapa kunjungan)
        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');                         // Paket Rawat Luka 5x
            $table->text('description')->nullable();
            $table->integer('total_visits');                // jumlah kunjungan dalam paket
            $table->integer('validity_days')->default(30); // berlaku berapa hari
            $table->decimal('price', 12, 2);               // harga paket
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Layanan di dalam paket
        Schema::create('service_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_package_items');
        Schema::dropIfExists('service_packages');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
    }
};
