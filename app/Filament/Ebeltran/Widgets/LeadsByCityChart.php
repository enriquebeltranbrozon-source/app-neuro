<?php

namespace App\Filament\Ebeltran\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class LeadsByCityChart extends ChartWidget
{
    protected static ?string $heading = 'Pacientes por Ciudad / Sede';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $leadsByCity = Lead::select('city', DB::raw('count(*) as total'))
            ->groupBy('city')
            ->pluck('total', 'city')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Volumen de Leads',
                    'data' => array_values($leadsByCity),
                    'backgroundColor' => ['#5C2D91', '#28a745', '#ffc107', '#dc3545', '#17a2b8'],
                ],
            ],
            'labels' => array_keys($leadsByCity),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}