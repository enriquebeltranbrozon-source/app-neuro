<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Clinic;
use App\Models\ExpenseCategory;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Regiones y clínicas primero; luego usuarios (para vincular clinic_id y clinic_user)
        $this->call([
            RegionSeeder::class,
            ClinicSeeder::class,
            UserSeeder::class,
            // LeadsBackupSeeder::class,
        ]);

        // 2. Referencias clave de clínicas para precios y asignación
        $sucursalDelValle = Clinic::where('code', 'CDMX-VALLE')->first();
        $sucursalVirtual  = Clinic::where('code', 'REMOTE')->first();
        $monterreyValle   = Clinic::where('code', 'MTY-VALLE')->first();

        // 3. Crear o actualizar Usuario Master / Administrador Global
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@neurofeedback.mx'],
            [
                'name'      => 'Admin Master NFC',
                'password'  => Hash::make('password123'),
                'role'      => UserRole::Admin->value,
                'clinic_id' => $sucursalDelValle?->id,
            ]
        );

        // Vincular al Admin Global con todas las clínicas (sin marcar is_clinic_admin, ya que es global)
        $allClinicIds = Clinic::pluck('id')->all();
        if (! empty($allClinicIds)) {
            $syncData = [];
            foreach ($allClinicIds as $clinicId) {
                $syncData[(int) $clinicId] = [
                    'is_primary'       => $sucursalDelValle && (int) $clinicId === (int) $sucursalDelValle->id,
                    'is_clinic_admin'  => false,
                ];
            }
            $adminUser->clinics()->syncWithoutDetaching($syncData);
        }

        // 4. Categorías principales de servicios
        $catEvaluacion = Category::updateOrCreate(
            ['slug' => 'evaluaciones-diagnostico'],
            [
                'name'        => 'Evaluaciones y Diagnóstico',
                'description' => 'Estudios cuantitativos y entrevistas clínicas iniciales.',
            ]
        );

        $catSesiones = Category::updateOrCreate(
            ['slug' => 'programas-sesiones-entrenamiento'],
            [
                'name'        => 'Programas y Sesiones de Entrenamiento',
                'description' => 'Sesiones individuales y paquetes de neurofeedback.',
            ]
        );

        // 5. Servicios clave y precios por clínica en tabla pivote
        $services = [
            [
                'category_id'      => $catEvaluacion->id,
                'name'             => 'Mapeo QEEG Inicial',
                'slug'             => 'mapeo-qeeg-inicial',
                'description'      => 'Evaluación cuantitativa de actividad cerebral de 19 canales.',
                'price'            => 2500.00,
                'duration_minutes' => 90,
            ],
            [
                'category_id'      => $catEvaluacion->id,
                'name'             => 'Entrevista Clínica y Psicológica',
                'slug'             => 'entrevista-clinica-psicologica',
                'description'      => 'Historial clínico y anamnesis inicial.',
                'price'            => 1000.00,
                'duration_minutes' => 60,
            ],
            [
                'category_id'      => $catEvaluacion->id,
                'name'             => 'Reevaluación de Progreso (QEEG)',
                'slug'             => 'reevaluacion-progreso-qeeg',
                'description'      => 'Medición intermedia o final para comparar avances.',
                'price'            => 2000.00,
                'duration_minutes' => 60,
            ],
            [
                'category_id'      => $catSesiones->id,
                'name'             => 'Sesión Individual de Neurofeedback',
                'slug'             => 'sesion-individual-neurofeedback',
                'description'      => 'Entrenamiento cerebral personalizado por sesión.',
                'price'            => 850.00,
                'duration_minutes' => 50,
            ],
            [
                'category_id'      => $catSesiones->id,
                'name'             => 'Paquete de 10 Sesiones',
                'slug'             => 'paquete-10-sesiones',
                'description'      => 'Programa intensivo de 10 sesiones con descuento.',
                'price'            => 7500.00,
                'duration_minutes' => 50,
            ],
            [
                'category_id'      => $catSesiones->id,
                'name'             => 'Sesión Remota Asistida',
                'slug'             => 'sesion-remota-asistida',
                'description'      => 'Entrenamiento desde casa con equipo portátil rentado.',
                'price'            => 900.00,
                'duration_minutes' => 50,
                'is_remote_only'   => true,
            ],
        ];

        foreach ($services as $serviceData) {
            $price        = $serviceData['price'];
            $isRemoteOnly = $serviceData['is_remote_only'] ?? false;

            unset($serviceData['price'], $serviceData['is_remote_only']);

            $service = Service::updateOrCreate(
                ['slug' => $serviceData['slug']],
                $serviceData
            );

            if ($isRemoteOnly && $sucursalVirtual) {
                $service->clinics()->syncWithoutDetaching([
                    $sucursalVirtual->id => ['price' => $price, 'is_active' => true],
                ]);
            } elseif ($sucursalDelValle) {
                $service->clinics()->syncWithoutDetaching([
                    $sucursalDelValle->id => ['price' => $price, 'is_active' => true],
                ]);

                if ($monterreyValle) {
                    $service->clinics()->syncWithoutDetaching([
                        $monterreyValle->id => ['price' => $price, 'is_active' => true],
                    ]);
                }
            }
        }

        // 6. Categorías de egresos
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