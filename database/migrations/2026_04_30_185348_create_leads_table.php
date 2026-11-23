<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ==========================================
        // 1. ESTRUCTURA ORGANIZACIONAL Y MULTICLÍNICA
        // ==========================================

        if (! Schema::hasTable('regions')) {
            Schema::create('regions', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // Ej: Región Norte (Monterrey), Región Centro (CDMX)
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('clinics')) {
            Schema::create('clinics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
                $table->string('name'); // Ej: Sucursal Del Valle, Clínica Online / Remota
                $table->string('code', 20)->unique(); // Ej: DEL-VALLE, REMOTE
                $table->boolean('is_virtual')->default(false)->index();

                $table->text('address')->nullable();
                $table->string('phone', 30)->nullable();

                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                // Sucursal activa/por defecto para UX rápida
                $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('role', 50)->default('terapeuta')->index();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // Tabla pivote de acceso multiclínica para usuarios:
        // - is_primary: clínica principal para UX / referencia rápida
        // - is_clinic_admin: administra esta clínica específica (máx. 1 admin por clínica y máx. 1 clínica por usuario)
        if (! Schema::hasTable('clinic_user')) {
            Schema::create('clinic_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->boolean('is_primary')->default(false);
                $table->boolean('is_clinic_admin')->default(false);
                $table->timestamps();

                $table->unique(['user_id', 'clinic_id']);
                $table->index(['user_id', 'is_primary']);
                $table->index(['clinic_id', 'is_clinic_admin']);
                $table->index(['user_id', 'is_clinic_admin']);
            });
        }

        // ==========================================
        // 2. INFRAESTRUCTURA LARAVEL / FILAMENT
        // ==========================================

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('job_batches')) {
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
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        // ==========================================
        // 3. ATRACCIÓN DIGITAL Y LEADS
        // ==========================================

        if (! Schema::hasTable('leads')) {
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
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
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
        }

        if (! Schema::hasTable('campaign_metrics')) {
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
        }

        if (! Schema::hasTable('exports')) {
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
        }

        if (! Schema::hasTable('imports')) {
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
        }

        if (! Schema::hasTable('failed_import_rows')) {
            Schema::create('failed_import_rows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
                $table->json('data');
                $table->text('validation_error')->nullable();
                $table->timestamps();
            });
        }

        // ==========================================
        // 4. CORE CLÍNICO Y OPERATIVO (NFC)
        // ==========================================

        if (! Schema::hasTable('clinic_settings')) {
            Schema::create('clinic_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('enforce_nom_004')->default(false);
                $table->boolean('require_vital_signs_on_session')->default(false);
                $table->boolean('require_cie10_code')->default(false);
                $table->json('custom_patient_schema')->nullable();
                $table->json('custom_session_schema')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        // Catálogo General de Servicios (Sin precio estático global)
        if (! Schema::hasTable('services')) {
            Schema::create('services', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('duration_minutes')->default(60);
                $table->boolean('requires_clinical_record')->default(true);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        // Tabla Pivote: Precios y Disponibilidad Diferenciados por Clínica
        if (! Schema::hasTable('clinic_service')) {
            Schema::create('clinic_service', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();

                $table->decimal('price', 10, 2); // Precio específico de la clínica
                $table->boolean('is_active')->default(true); // Controla si la clínica ofrece este servicio

                $table->timestamps();

                $table->unique(['clinic_id', 'service_id']);
                $table->index(['clinic_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('patients')) {
            Schema::create('patients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();

                $table->enum('registration_source', [
                    'digital_lead',
                    'front_desk',
                    'legacy_migration',
                ])->default('front_desk')->index();

                $table->string('full_name');
                $table->string('phone', 30)->nullable()->index();
                $table->string('email')->nullable()->unique();
                $table->date('birthdate')->nullable();

                $table->char('curp', 18)->nullable()->unique();
                $table->enum('gender', ['M', 'F', 'O'])->nullable();
                $table->text('address')->nullable();
                $table->string('occupation')->nullable();
                $table->string('emergency_contact_name')->nullable();
                $table->string('emergency_contact_phone', 30)->nullable();

                $table->string('eeg_origin', 30)->default('schedule_required'); // 'schedule_required' | 'external_prior'
                $table->dateTime('eeg_scheduled_at')->nullable();
                $table->text('eeg_notes')->nullable();

                $table->json('custom_fields')->nullable();

                $table->enum('status', ['active', 'paused', 'discharged'])->default('active')->index();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['clinic_id', 'status']);
            });
        }

        if (! Schema::hasTable('patient_sessions')) {
            Schema::create('patient_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // Terapeuta
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
                $table->enum('payment_status', ['pending', 'paid', 'covered_by_package'])->default('pending')->index();
                $table->decimal('charged_amount', 10, 2)->default(0.00);

                // Control de Firma e Inmutabilidad
                $table->boolean('is_signed')->default(false);
                $table->timestamp('signed_at')->nullable();

                $table->timestamps();

                $table->index(['patient_id', 'session_number']);
                $table->index(['clinic_id', 'started_at']);
                $table->index(['clinic_id', 'payment_status', 'started_at'], 'ps_clinic_payment_started_idx');
            });
        }

        // ==========================================
        // 5. MÓDULO DE EEG
        // ==========================================

        if (! Schema::hasTable('eeg_studies')) {
            Schema::create('eeg_studies', function (Blueprint $table) {
                $table->id();

                // Relaciones principales y trazabilidad de actores
                $table->foreignId('patient_id')
                    ->constrained('patients')
                    ->cascadeOnDelete();

                $table->foreignId('clinic_id')
                    ->nullable()
                    ->constrained('clinics')
                    ->nullOnDelete();

                $table->foreignId('requested_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('assigned_technician_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('uploaded_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                // Flujo de agenda y estado del estudio
                $table->string('status', 30)->default('scheduled'); // 'scheduled' | 'completed' | 'external'
                $table->boolean('is_external')->default(false);
                $table->dateTime('scheduled_at')->nullable();
                $table->dateTime('completed_at')->nullable();

                // Metadatos clínicos y archivo en repositorio
                $table->string('title', 255); // Ej. "EEG Basal Inicial"
                $table->date('study_date')->nullable();
                $table->string('file_path', 500)->nullable();
                $table->string('original_filename', 255)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->nullable(); // bytes
                $table->text('notes')->nullable(); // Indicaciones previas u observaciones técnicas

                $table->timestamps();
                $table->softDeletes();

                // Índices de rendimiento para agenda del técnico y repositorio por clínica/paciente
                $table->index(['patient_id', 'status', 'created_at'], 'eeg_patient_status_created_idx');
                $table->index(['clinic_id', 'status', 'scheduled_at'], 'eeg_clinic_status_scheduled_idx');
                $table->index(['assigned_technician_id', 'status', 'scheduled_at'], 'eeg_tech_status_scheduled_idx');
                $table->index(['clinic_id', 'study_date'], 'eeg_clinic_study_date_idx');
            });
        }

        // ==========================================
        // 6. MÓDULO DE PROTOCOLOS
        // ==========================================

        if (! Schema::hasTable('protocols')) {
            Schema::create('protocols', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->foreignId('eeg_study_id')->nullable()->constrained('eeg_studies')->nullOnDelete();
                $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->string('title');
                $table->string('status', 30)->default('draft'); // 'draft' | 'active' | 'completed'
                $table->unsignedSmallInteger('total_sessions')->default(45);
                $table->unsignedSmallInteger('recommended_frequency_per_week')->default(2);

                $table->text('eeg_findings')->nullable();
                $table->text('clinical_goals')->nullable();
                $table->string('electrode_sites')->nullable(); // Ej. Cz, Fz, Pz, C3/C4
                $table->text('frequency_targets')->nullable(); // Ej. Inhibir 4-8 Hz, recompensar 12-15 Hz (SMR)
                $table->text('clinical_notes')->nullable();

                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->index(['patient_id', 'status']);
                $table->index(['clinic_id', 'status']);
            });
        }

        if (! Schema::hasTable('protocol_sessions')) {
            Schema::create('protocol_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('protocol_id')->constrained('protocols')->cascadeOnDelete();
                $table->foreignId('patient_session_id')
                    ->nullable()
                    ->constrained('patient_sessions')
                    ->nullOnDelete();
                $table->unsignedSmallInteger('session_number');
                $table->string('phase')->nullable(); // Ej. Fase 1: Adaptación y línea base
                $table->date('scheduled_date')->nullable();
                $table->string('status', 30)->default('pending'); // 'pending' | 'completed' | 'missed'
                $table->text('session_notes')->nullable();
                $table->timestamps();

                $table->unique('patient_session_id');
                $table->unique(['protocol_id', 'session_number']);
                $table->index(['protocol_id', 'status']);
            });
        }

        // ==========================================
        // 7. MÓDULO DE FINANZAS Y EGRESOS
        // ==========================================

        if (! Schema::hasTable('expense_categories')) {
            Schema::create('expense_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_fixed')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
                $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

                $table->string('concept');
                $table->decimal('amount', 10, 2);
                $table->date('expense_date')->index();

                $table->enum('payment_method', ['cash', 'card', 'transfer', 'petty_cash'])->default('cash');
                $table->string('reference_number')->nullable();

                $table->boolean('has_invoice')->default(false);
                $table->string('invoice_number')->nullable();
                $table->string('receipt_file_path')->nullable();

                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['clinic_id', 'expense_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('protocol_sessions');
        Schema::dropIfExists('protocols');
        Schema::dropIfExists('eeg_studies');
        Schema::dropIfExists('patient_sessions');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('clinic_service');
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
        Schema::dropIfExists('clinic_user');
        Schema::dropIfExists('users');
        Schema::dropIfExists('clinics');
        Schema::dropIfExists('regions');

        Schema::enableForeignKeyConstraints();
    }
};