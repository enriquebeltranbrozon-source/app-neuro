<?php

namespace App\Filament\Ebeltran\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class LeadsByMonthChart extends ChartWidget
{
    protected static ?string $heading = 'Ingreso de Prospectos (Últimos 6 Meses)';
    protected static ?int $sort = 2; // Aparece debajo de las tarjetas numéricas

    protected function getData(): array
    {
        $data = [];
        $labels = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $labels[] = ucfirst($month->translatedFormat('F')); // Mes en español
            $data[] = Lead::whereMonth('created_at', $month->month)
                          ->whereYear('created_at', $month->year)
                          ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Nuevos Prospectos',
                    'data' => $data,
                    'borderColor' => '#5C2D91', // Morado NFC
                    'backgroundColor' => 'rgba(92, 45, 145, 0.2)', // Morado con transparencia
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}