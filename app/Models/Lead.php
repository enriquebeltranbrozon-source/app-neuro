<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    // AÑADE ESTA LÍNEA PARA PERMITIR GUARDAR LOS DATOS
   protected $casts = [
    'main_symptom' => 'array',
    'contacted_at' => 'datetime',
    'follow_up_date' => 'date',
];

protected $fillable = [
    'name', 
    'email', 
    'phone', 
    'patient_type', 
    'age', 
    'main_symptom', // <- Debe ser SINGULAR aquí también
    'symptom_description', 
    'city', 
    'source'
];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
}