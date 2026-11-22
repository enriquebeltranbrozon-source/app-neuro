<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Clinic;
use App\Models\Region;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        $clinics = [
            // CDMX
            ['region' => 'CDMX', 'name' => 'Sucursal Del Valle', 'code' => 'CDMX-VALLE', 'is_virtual' => false],
            
            // Morelos
            ['region' => 'Morelos', 'name' => 'Sucursal Cuernavaca', 'code' => 'MOR-CUER', 'is_virtual' => false],
            
            // Monterrey (Clúster Regional)
            ['region' => 'Monterrey', 'name' => 'Monterrey Valle', 'code' => 'MTY-VALLE', 'is_virtual' => false],
            ['region' => 'Monterrey', 'name' => 'Monterrey Doctores', 'code' => 'MTY-DOCS', 'is_virtual' => false],
            ['region' => 'Monterrey', 'name' => 'Monterrey Rioja', 'code' => 'MTY-RIOJA', 'is_virtual' => false],
            
            // Estado de México
            ['region' => 'Estado de México', 'name' => 'Sucursal Metepec', 'code' => 'EDOMEX-MET', 'is_virtual' => false],
            
            // Europa / España
            ['region' => 'Europa', 'name' => 'Sucursal Barcelona', 'code' => 'EUR-BCN', 'is_virtual' => false],
            
            // Canal Virtual / Remoto Global
            ['region' => null, 'name' => 'Clínica Online / Remota', 'code' => 'REMOTE', 'is_virtual' => true],
        ];

        foreach ($clinics as $data) {
            $regionId = null;
            if (!empty($data['region'])) {
                $region = Region::where('name', $data['region'])->first();
                $regionId = $region?->id;
            }

            Clinic::updateOrCreate(
                ['code' => $data['code']],
                [
                    'region_id'  => $regionId,
                    'name'       => $data['name'],
                    'is_virtual' => $data['is_virtual'],
                    'is_active'  => true,
                ]
            );
        }
    }
}