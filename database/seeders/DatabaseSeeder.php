<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\Category;
use App\Models\Service;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear o actualizar Clínicas (Física y Virtual)
        $sucursalFisica = Clinic::updateOrCreate(
            ['code' => 'DEL-VALLE'],
            [
                'name' => 'Neurofeedback Center - Del Valle',
                'is_virtual' => false,
                'address' => 'Av. Coyoacán #1230, Col. Del Valle, Benito Juárez, CDMX',
                'phone' => '5512345678',
            ]
        );

        $sucursalVirtual = Clinic::updateOrCreate(
            ['code' => 'REMOTE'],
            [
                'name' => 'Neurofeedback Center - Online / Remota',
                'is_virtual' => true,
                'address' => 'Plataforma Digital Remota',
                'phone' => '5587654321',
            ]
        );

        // 2. Crear o actualizar Usuario Master / Administrador
        User::updateOrCreate(
            ['email' => 'admin@neurofeedback.mx'],
            [
                'name' => 'Admin Master NFC',
                'password' => Hash::make('password123'),
                'role' => UserRole::Admin->value, // Asegurando compatibilidad con el Enum si almacena string
                'clinic_id' => $sucursalFisica->id,
            ]
        );

        // 3. Crear las 2 Categorías Principales
        $catEvaluacion = Category::updateOrCreate(
            ['slug' => 'evaluaciones-diagnostico'],
            [
                'name' => 'Evaluaciones y Diagnóstico',
                'description' => 'Estudios cuantitativos y entrevistas clínicas iniciales.',
            ]
        );

        $catSesiones = Category::updateOrCreate(
            ['slug' => 'programas-sesiones-entrenamiento'],
            [
                'name' => 'Programas y Sesiones de Entrenamiento',
                'description' => 'Sesiones individuales y paquetes de neurofeedback.',
            ]
        );

        // 4. Crear los 6 Servicios Clave y asociar sus precios por clínica (Tabla Pivote)
        $services = [
            // Categoría 1: Evaluaciones
            [
                'category_id'      => $catEvaluacion->id, 
                'name'             => 'Mapeo QEEG Inicial', 
                'slug'             => 'mapeo-qeeg-inicial',
                'description'      => 'Evaluación cuantitativa de actividad cerebral de 19 canales.', 
                'price'            => 2500.00, 
                'duration_minutes' => 90
            ],
            [
                'category_id'      => $catEvaluacion->id, 
                'name'             => 'Entrevista Clínica y Psicológica', 
                'slug'             => 'entrevista-clinica-psicologica',
                'description'      => 'Historial clínico y anamnesis inicial.', 
                'price'            => 1000.00, 
                'duration_minutes' => 60
            ],
            [
                'category_id'      => $catEvaluacion->id, 
                'name'             => 'Reevaluación de Progreso (QEEG)', 
                'slug'             => 'reevaluacion-progreso-qeeg',
                'description'      => 'Medición intermedia o final para comparar avances.', 
                'price'            => 2000.00, 
                'duration_minutes' => 60
            ],
            
            // Categoría 2: Sesiones
            [
                'category_id'      => $catSesiones->id, 
                'name'             => 'Sesión Individual de Neurofeedback', 
                'slug'             => 'sesion-individual-neurofeedback',
                'description'      => 'Entrenamiento cerebral personalizado por sesión.', 
                'price'            => 850.00, 
                'duration_minutes' => 50
            ],
            [
                'category_id'      => $catSesiones->id, 
                'name'             => 'Paquete de 10 Sesiones', 
                'slug'             => 'paquete-10-sesiones',
                'description'      => 'Programa intensivo de 10 sesiones con descuento.', 
                'price'            => 7500.00, 
                'duration_minutes' => 50
            ],
            [
                'category_id'      => $catSesiones->id, 
                'name'             => 'Sesión Remota Asistida', 
                'slug'             => 'sesion-remota-asistida',
                'description'      => 'Entrenamiento desde casa con equipo portátil rentado.', 
                'price'            => 900.00, 
                'duration_minutes' => 50,
                'is_remote_only'   => true // Bandera lógica interna para asignar a la sucursal virtual
            ],
        ];

        foreach ($services as $serviceData) {
            // Extraer el precio y la regla de asignación antes de persistir en la tabla 'services'
            $price = $serviceData['price'];
            $isRemoteOnly = $serviceData['is_remote_only'] ?? false;
            
            unset($serviceData['price'], $serviceData['is_remote_only']);

            // Crear o actualizar el servicio base en el catálogo general
            $service = Service::updateOrCreate(
                ['slug' => $serviceData['slug']], 
                $serviceData
            );

            // Determinar a qué clínica(s) pertenece este precio
            if ($isRemoteOnly) {
                // La sesión remota va ligada principalmente a la clínica virtual
                $service->clinics()->syncWithoutDetaching([
                    $sucursalVirtual->id => ['price' => $price, 'is_active' => true]
                ]);
            } else {
                // Los servicios presenciales se ligan a la sucursal física Del Valle con su respectiva tarifa
                $service->clinics()->syncWithoutDetaching([
                    $sucursalFisica->id => ['price' => $price, 'is_active' => true]
                ]);
            }
        }

        // 5. Crear Categorías de Egresos
        $expenseCategories = [
            'Insumos y Consumibles Clínicos',
            'Renta y Servicios (Luz, Internet)',
            'Nómina y Honorarios Terapeutas',
            'Marketing y Publicidad (Ads)',
            'Caja Chica y Gastos Menores',
        ];

        foreach ($expenseCategories as $expCat) {
            $slug = Str::slug($expCat);
            ExpenseCategory::updateOrCreate(
                ['slug' => $slug],
                ['name' => $expCat]
            );
        }
    }
}