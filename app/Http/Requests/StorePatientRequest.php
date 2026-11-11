<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\ClinicSetting;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ajustar según las policies de usuario si es necesario
    }

    public function rules(): array
    {
        // Consultamos la configuración global de la clínica
        $settings = ClinicSetting::first();

        // Reglas base (obligatorias sin importar la norma)
        $rules = [
            'full_name' => 'required|string|max:255',
            'phone'     => 'nullable|string|max:30',
            'email'     => 'nullable|email|unique:patients,email',
            'birthdate' => 'nullable|date',
            'clinic_id' => 'required|exists:clinics,id', // 👈 Asegura que se asigne a una clínica existente
        ];

        // Si la clínica activó el modo estricto NOM-004, exigimos la ficha legal completa
        if ($settings?->enforce_nom_004) {
            $rules['curp'] = 'required|string|size:18|unique:patients,curp';
            $rules['gender'] = 'required|in:M,F,O';
            $rules['address'] = 'required|string';
            $rules['emergency_contact_name'] = 'required|string|max:255';
            $rules['emergency_contact_phone'] = 'required|string|max:30';
        }

        return $rules;
    }
}