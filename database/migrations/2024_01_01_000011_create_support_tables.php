<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notifikasi in-app
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');                           // class notifikasi
            $table->morphs('notifiable');                     // user yang menerima
            $table->text('data');                             // payload JSON
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_id', 'notifiable_type', 'read_at']);
        });

        // Log WhatsApp / SMS / Email yang dikirim
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('channel', ['whatsapp', 'sms', 'email', 'push']);
            $table->string('recipient');                      // nomor hp / email
            $table->string('subject')->nullable();
            $table->text('message');
            $table->enum('status', ['sent', 'failed', 'pending'])->default('pending');
            $table->string('provider')->nullable();           // Fonnte, Twilio, Mailgun
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->morphs('notifiable');                     // relasi ke order, visit, dll
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // Activity log (audit trail)
        // Schema::create('activity_logs', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        //     $table->string('action');                         // created, updated, deleted, login
        //     $table->string('model_type')->nullable();
        //     $table->unsignedBigInteger('model_id')->nullable();
        //     $table->json('old_values')->nullable();           // nilai sebelum
        //     $table->json('new_values')->nullable();           // nilai sesudah
        //     $table->string('ip_address', 45)->nullable();
        //     $table->text('user_agent')->nullable();
        //     $table->text('description')->nullable();
        //     $table->timestamps();

        //     $table->index(['model_type', 'model_id']);
        //     $table->index(['user_id', 'created_at']);
        // });

        // Pengaturan aplikasi (key-value)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 30)->default('string');   // string, boolean, integer, json
            $table->string('group')->default('general');      // general, notification, billing
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);    // bisa diakses frontend
            $table->timestamps();
        });

        // Zona layanan (area yang dilayani)
        Schema::create('service_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');                           // Zona Surabaya Selatan
            $table->text('description')->nullable();
            $table->decimal('extra_transport_fee', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Kota / kecamatan yang masuk zona layanan
        Schema::create('service_zone_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // FAQ / Artikel edukasi
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('thumbnail')->nullable();
            $table->enum('type', ['artikel', 'faq', 'pengumuman'])->default('artikel');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
        Schema::dropIfExists('service_zone_areas');
        Schema::dropIfExists('service_zones');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notifications');
    }
};
