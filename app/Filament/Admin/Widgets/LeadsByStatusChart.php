<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;

class LeadsByStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Estado del Embudo Comercial';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $statuses = [
            'abierto'         => 'Abierto',
            'contactado'     => 'Contactado',
            'cita_programada' => 'Cita Programada',
            'cliente_nuevo'   => 'Cliente Nuevo',
            'no_concretado'  => 'No Concretado',
        ];

        $counts = Lead::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $labels = [];
        $values = [];

        foreach ($statuses as $key => $label) {
            $labels[] = $label;
            $values[] = $counts[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Prospectos',
                    'data'  => $values,
                    'backgroundColor' => [
                        '#ef4444', // Abierto
                        '#f59e0b', // Contactado
                        '#06b6d4', // Cita Programada
                        '#10b981', // Cliente Nuevo
                        '#6b7280', // No Concretado
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}