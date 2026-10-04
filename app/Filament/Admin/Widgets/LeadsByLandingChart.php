<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class LeadsByLandingChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected static ?string $heading = 'Volumen de Leads por Landing Page';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        // Aplicar filtros globales de fecha del Dashboard
        $landings = Lead::query()
            ->when($startDate, fn (Builder $q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $q) => $q->whereDate('created_at', '<=', $endDate))
            ->selectRaw('landing_origin, COUNT(*) as total')
            ->groupBy('landing_origin')
            ->pluck('total', 'landing_origin')
            ->toArray();

        // Mapeo de identificadores a etiquetas legibles
        $labelsMap = [
            'index_principal'   => 'Home ( / )',
            'landing_depresion'  => 'Depresión ( /depresion )',
            'landing_ansiedad'   => 'Ansiedad ( /ansiedad )',
            'landing_aprendizaje'=> 'Aprendizaje ( /problemas-aprendizaje )',
            'landing_deficit'    => 'Déficit de Atención ( /deficit )',
        ];

        $labels = [];
        $data = [];

        foreach ($labelsMap as $key => $label) {
            $labels[] = $label;
            $data[] = $landings[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Prospectos',
                    'data'  => $data,
                    'backgroundColor' => [
                        '#8759a5', // Morado corporativo NFC (Index)
                        '#3B82F6', // Azul (Depresión)
                        '#10B981', // Esmeralda (Ansiedad)
                        '#F59E0B', // Ámbar (Aprendizaje)
                        '#EC4899', // Rosa/Magenta (Déficit)
                    ],
                    'borderRadius' => 6,
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