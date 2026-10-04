<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Usuarios y Control de Acceso
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('clinic')->nullable();
            $table->string('password');
            $table->string('role', 50)->default('agent')->index();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // 2. Sistema de Notificaciones de Laravel / Filament
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // 3. Caché del Sistema
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        // 4. Sistema de Colas (Jobs & Queues)
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // 5. Tabla Principal de Leads
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            
            // Atribución de WhatsApp y Sesión
            $table->string('session_token', 64)->nullable()->unique();
            $table->timestamp('whatsapp_matched_at')->nullable();
            
            // Datos del Prospecto
            $table->string('name')->nullable();
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('city')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('patient_type', 100)->nullable();
            $table->string('main_symptom')->nullable();
            
            // Detalles de Síntomas
            $table->json('main_symptoms')->nullable();
            $table->text('symptom_description')->nullable();
            
            // Gestión Comercial y Estado
            $table->string('status', 50)->default('abierto')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('contacted_at')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('agent_comments')->nullable();
            
            // Atribución de Canal y Landing Page Originaria
            $table->string('source', 50)->default('web')->index();
            $table->string('landing_origin', 100)->nullable()->index();
            $table->string('landing_page', 500)->nullable();
            
            // Parámetros de Publicidad
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('gclid')->nullable()->index();
            $table->string('gbraid')->nullable()->index();
            $table->string('wbraid')->nullable()->index();
            
            // Auditoría y Metadatos de Red
            $table->timestamp('client_timestamp')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['created_at', 'status']);
        });

        // 6. Tabla de Métricas de Campañas
        Schema::create('campaign_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id')->index();
            $table->string('campaign_name');
            $table->date('date')->index();
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->decimal('cost', 10, 2)->default(0.00);
            $table->integer('conversions')->default(0);
            $table->decimal('ctr', 5, 2)->default(0.00);
            $table->decimal('conversion_rate', 5, 2)->default(0.00);
            $table->timestamps();
        });

        // 7. Tablas de Filament Actions (Exportación e Importación)
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('exporter');
            $table->unsignedInteger('total_rows');
            $table->string('file_disk');
            $table->string('file_name')->nullable();
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->timestamps();
        });

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('importer');
            $table->unsignedInteger('total_rows');
            $table->string('file_disk');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->timestamps();
        });

        Schema::create('failed_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->json('data');
            $table->text('validation_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_import_rows');
        Schema::dropIfExists('imports');
        Schema::dropIfExists('exports');
        Schema::dropIfExists('campaign_metrics');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};