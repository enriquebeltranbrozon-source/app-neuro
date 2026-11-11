<?php
namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Lead;
use App\Http\Requests\StorePatientRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    /**
     * Alta directa desde Mostrador (Front Desk)
     */
    public function store(StorePatientRequest $request): JsonResponse
    {
        try {
            $patient = Patient::create(array_merge($request->validated(), [
                'registration_source' => 'front_desk',
                'status' => 'active'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Paciente registrado exitosamente desde mostrador.',
                'data' => $patient
            ], 201);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el paciente.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * El Puente de Colonización: Convierte un Lead digital en Paciente oficial
     */
    public function convertFromLead(Lead $lead): JsonResponse
    {
        // Validar si el lead ya fue convertido previamente
        $existingPatient = Patient::where('lead_id', $lead->id)->first();
        if ($existingPatient) {
            return response()->json([
                'success' => false,
                'message' => 'Este lead ya ha sido convertido a paciente anteriormente.',
                'data' => $existingPatient
            ], 422);
        }

        try {
            $patient = DB::transaction(function () use ($lead) {
                // 1. Crear el paciente heredando los datos capturados en el embudo digital
                $newPatient = Patient::create([
                    'lead_id' => $lead->id,
                    'registration_source' => 'digital_lead',
                    'full_name' => $lead->name ?? 'Prospecto sin nombre',
                    'phone' => $lead->phone,
                    'email' => $lead->email,
                    'status' => 'active',
                    // Los campos NOM-004 o custom se llenarán en la valoración presencial
                ]);

                // 2. Actualizar el estatus comercial del lead a "convertido" o "paciente"
                $lead->update([
                    'status' => 'convertido'
                ]);

                return $newPatient;
            });

            return response()->json([
                'success' => true,
                'message' => 'Lead colonizado y convertido a paciente con éxito.',
                'data' => $patient
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error crítico durante la colonización del lead.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}