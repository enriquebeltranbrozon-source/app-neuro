<?php

namespace App\Filament\Ebeltran\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class LeadsBySymptomChart extends ChartWidget
{
    protected static ?string $heading = 'Distribución por Padecimiento';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $leadsBySymptom = Lead::select('main_symptom', DB::raw('count(*) as total'))
            ->groupBy('main_symptom')
            ->pluck('total', 'main_symptom')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Padecimientos',
                    'data' => array_values($leadsBySymptom),
                    'backgroundColor' => ['#5C2D91', '#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6f42c1', '#e83e8c'],
                ],
            ],
            'labels' => array_keys($leadsBySymptom),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}