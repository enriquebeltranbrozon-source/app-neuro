<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class LeadsByClinicChart extends ChartWidget
{
    protected static ?string $heading = 'Distribución de Prospectos por Sede';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = Lead::select('city', DB::raw('count(*) as total'))
            ->groupBy('city')
            ->pluck('total', 'city')
            ->toArray();

        $labels = [
            'del_valle'  => 'Del Valle',
            'satelite'   => 'Satélite',
            'metepec'    => 'Metepec',
            'cuernavaca' => 'Cuernavaca',
            'monterrey'  => 'Monterrey',
        ];

        $chartLabels = [];
        $chartValues = [];

        foreach ($labels as $key => $label) {
            $chartLabels[] = $label;
            $chartValues[] = $data[$key] ?? $data[$label] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Prospectos',
                    'data'  => $chartValues,
                    'backgroundColor' => [
                        '#3b82f6', // Del Valle (Info)
                        '#22c55e', // Satélite (Success)
                        '#eab308', // Metepec (Warning)
                        '#6366f1', // Cuernavaca (Primary)
                        '#ef4444', // Monterrey (Danger)
                    ],
                ],
            ],
            'labels' => $chartLabels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}