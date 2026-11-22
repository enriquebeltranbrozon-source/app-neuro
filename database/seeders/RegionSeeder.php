<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Region;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            ['name' => 'CDMX', 'slug' => 'cdmx'],
            ['name' => 'Morelos', 'slug' => 'morelos'],
            ['name' => 'Monterrey', 'slug' => 'monterrey'],
            ['name' => 'Estado de México', 'slug' => 'estado-de-mexico'],
            ['name' => 'Europa', 'slug' => 'europa'],
        ];

        foreach ($regions as $region) {
            Region::updateOrCreate(
                ['slug' => $region['slug']],
                ['name' => $region['name']]
            );
        }
    }
}