<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Catálogo de Clínicas / Sucursales (Incluye la sucursal virtual para remoto)
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ej: Sucursal Del Valle, Clínica Online / Remota
            $table->string('code', 20)->unique(); // Ej: DEL-VALLE, REMOTE
            $table->boolean('is_virtual')->default(false); // Define si es el canal para terapias por internet
            
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Usuarios y Control de Acceso (Con asignación de clínica)
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('clinic')->nullable(); // Campo de compatibilidad legacy
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

        // 3. Sistema de Notificaciones de Laravel / Filament[cite: 1]
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // 4. Caché del Sistema
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

        // 5. Sistema de Colas (Jobs & Queues)
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

        // 6. Tabla Principal de Leads (Atracción Digital)[cite: 1]
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            
            $table->string('session_token', 64)->nullable()->unique();
            $table->timestamp('whatsapp_matched_at')->nullable();
            
            $table->string('name')->nullable();
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('city')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('patient_type', 100)->nullable();
            $table->string('main_symptom')->nullable();
            
            $table->json('main_symptoms')->nullable();
            $table->text('symptom_description')->nullable();
            
            $table->string('status', 50)->default('abierto')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('contacted_at')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('agent_comments')->nullable();
            
            $table->string('source', 50)->default('web')->index();
            $table->string('landing_origin', 100)->nullable()->index();
            $table->string('landing_page', 500)->nullable();
            
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('gclid')->nullable()->index();
            $table->string('gbraid')->nullable()->index();
            $table->string('wbraid')->nullable()->index();
            
            $table->timestamp('client_timestamp')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['created_at', 'status']);
        });

        // 7. Tabla de Métricas de Campañas[cite: 1]
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

        // 8. Tablas de Filament Actions (Exportación e Importación)[cite: 1]
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

        // ==========================================
        // 9. CORE CLÍNICO Y OPERATIVO (NFC)
        // ==========================================

        Schema::create('clinic_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enforce_nom_004')->default(false);
            $table->boolean('require_vital_signs_on_session')->default(false);
            $table->boolean('require_cie10_code')->default(false);
            $table->json('custom_patient_schema')->nullable();
            $table->json('custom_session_schema')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Catálogo General de Servicios (Sin precio estático global)
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->boolean('requires_clinical_record')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tabla Pivote: Precios y Disponibilidad Diferenciados por Clínica
        Schema::create('clinic_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            
            $table->decimal('price', 10, 2); // Precio específico de la clínica
            $table->boolean('is_active')->default(true); // Controla si la clínica ofrece este servicio
            
            $table->timestamps();

            $table->unique(['clinic_id', 'service_id']); // Evita duplicados por sucursal
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete(); 
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete(); 
            
            $table->enum('registration_source', [
                'digital_lead',    
                'front_desk',      
                'legacy_migration' 
            ])->default('front_desk')->index();

            $table->string('full_name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable()->unique();
            $table->date('birthdate')->nullable();
            
            $table->char('curp', 18)->nullable()->unique();
            $table->enum('gender', ['M', 'F', 'O'])->nullable();
            $table->text('address')->nullable();
            $table->string('occupation')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            
            $table->json('custom_fields')->nullable();
            
            $table->enum('status', ['active', 'paused', 'discharged'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('patient_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users'); // Terapeuta
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            
            $table->unsignedInteger('session_number');
            
            // Modalidad y Canales de Atención
            $table->enum('modality', ['in_person', 'remote'])->default('in_person')->index();
            $table->string('remote_platform')->nullable(); // Ej: Zoom, Google Meet, Encefalev Remoto
            
            // Tiempos exactos de consulta
            $table->dateTime('started_at')->nullable()->index();
            $table->dateTime('ended_at')->nullable()->index();
            
            // Metadatos unificados (Protocolo, Asistencia, Observaciones)
            $table->json('session_metadata')->nullable();
            $table->json('impedance_readings')->nullable();
            
            // Control Administrativo y de Pago
            $table->enum('payment_status', ['pending', 'paid', 'covered_by_package'])->default('pending');
            $table->decimal('charged_amount', 10, 2)->default(0.00);

            // Control de Firma e Inmutabilidad
            $table->boolean('is_signed')->default(false);
            $table->timestamp('signed_at')->nullable();
            
            $table->timestamps();
        });

        // ==========================================
        // 10. MÓDULO DE FINANZAS Y EGRESOS
        // ==========================================

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_fixed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            
            $table->string('concept');
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'petty_cash'])->default('cash');
            $table->string('reference_number')->nullable();
            
            $table->boolean('has_invoice')->default(false);
            $table->string('invoice_number')->nullable();
            $table->string('receipt_file_path')->nullable();
            
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('patient_sessions');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('clinic_service'); // 👈 Se elimina la tabla pivote en rollback
        Schema::dropIfExists('services');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('clinic_settings');
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
        Schema::dropIfExists('clinics');
    }
};