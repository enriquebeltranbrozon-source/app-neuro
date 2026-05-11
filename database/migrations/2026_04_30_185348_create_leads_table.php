<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('phone');
    $table->string('email');
    $table->string('city');
    $table->integer('age');
    $table->string('patient_type');
    $table->string('main_symptom');
    
    // Nuevos campos de negocio
    $table->json('main_symptoms')->nullable(); 
    $table->text('symptom_description')->nullable();
    $table->string('status')->default('abierto');
    
    // Asignación y seguimiento
    $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // El Agente asignado
    $table->timestamp('contacted_at')->nullable(); // Cuándo llamó el agente
    $table->date('follow_up_date')->nullable(); // "Contactar en:"
    $table->text('agent_comments')->nullable(); // Notas del agente
    
    $table->string('source')->default('web'); // web, whatsapp, telefono
    $table->timestamps(); // created_at será nuestro timestamp de recepción
});
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};