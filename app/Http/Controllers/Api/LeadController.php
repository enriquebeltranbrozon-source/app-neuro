<?php

// 1. EL PARCHE CRÍTICO: Agregamos \Api al final del namespace
namespace App\Http\Controllers\Api; 

// 2. Importamos el controlador base de Laravel
use App\Http\Controllers\Controller; 
use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
            'patient_type' => 'required|string',
            'age' => 'required|numeric',
            'main_symptoms' => 'required|array', 
            'symptom_description' => 'nullable|string',
            'city' => 'required|string',
            'source' => 'nullable|string',
        ]);

        $symptomsText = implode(', ', $validated['main_symptoms']);

        $lead = Lead::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'patient_type' => $validated['patient_type'],
            'age' => $validated['age'],
            'main_symptom' => $symptomsText,  
            'symptom_description' => $validated['symptom_description'] ?? null,
            'city' => $validated['city'],
            'source' => $validated['source'] ?? 'nfc_center',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => '¡Lead guardado correctamente!'
        ], 200);
    }
}