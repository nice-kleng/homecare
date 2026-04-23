<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daftar biaya tambahan / tindakan ekstra
        Schema::create('additional_charges', function (Blueprint $table) {
            $table->id();
            $table->string('name');            // Biaya Alkes Tambahan, Biaya Malam, dll
            $table->string('code')->unique();
            $table->decimal('amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Invoice
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique(); // INV-20240101-0001
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

            // --- Rincian Biaya ---
            $table->decimal('service_fee', 12, 2)->default(0);       // biaya layanan
            $table->decimal('transport_fee', 10, 2)->default(0);     // biaya transport
            $table->decimal('consumables_fee', 10, 2)->default(0);   // biaya BHP
            $table->decimal('additional_fee', 10, 2)->default(0);    // biaya tambahan lain
            $table->decimal('subtotal', 12, 2)->default(0);

            // --- Diskon ---
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('discount_notes')->nullable();

            // --- Pajak ---
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);

            $table->decimal('total_amount', 12, 2);

            // --- Asuransi / BPJS ---
            $table->decimal('insurance_coverage', 12, 2)->default(0); // ditanggung asuransi
            $table->decimal('patient_liability', 12, 2)->default(0);  // yang harus dibayar pasien

            // --- Status ---
            /**
             * draft     : invoice dibuat, belum dikirim
             * sent      : sudah dikirim ke pasien
             * partial   : dibayar sebagian
             * paid      : lunas
             * overdue   : melewati jatuh tempo
             * cancelled : dibatalkan
             * refunded  : dikembalikan
             */
            $table->enum('status', [
                'draft', 'sent', 'partial', 'paid', 'overdue', 'cancelled', 'refunded'
            ])->default('draft');

            $table->date('issued_date');
            $table->date('due_date');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
            $table->index(['due_date', 'status']);
        });

        // Detail baris invoice (itemisasi)
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->enum('item_type', ['service', 'transport', 'consumable', 'additional', 'discount'])->default('service');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->timestamps();
        });

        // Biaya tambahan di invoice
        Schema::create('invoice_additional_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('additional_charge_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Pembayaran (bisa partial/cicil)
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique(); // PAY-20240101-0001
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->date('payment_date');

            $table->enum('payment_method', [
                'cash', 'transfer_bank', 'bpjs', 'asuransi', 'qris', 'kartu_debit', 'kartu_kredit', 'lainnya'
            ])->default('cash');

            // Jika transfer
            $table->string('bank_name')->nullable();
            $table->string('account_number', 30)->nullable();
            $table->string('transfer_reference')->nullable(); // nomor referensi transfer

            $table->string('proof_file')->nullable();          // bukti pembayaran (path)

            // --- Verifikasi ---
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['invoice_id', 'status']);
        });

        // Riwayat refund
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->enum('status', ['pending', 'processed', 'rejected'])->default('pending');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_additional_charges');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('additional_charges');
    }
};
