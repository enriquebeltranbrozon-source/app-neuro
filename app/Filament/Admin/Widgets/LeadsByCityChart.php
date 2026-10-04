<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class LeadsByCityChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Pacientes por Ciudad / Sede';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $startDate = $this->filters['startDate'] ?? null;
        $endDate   = $this->filters['endDate'] ?? null;

        $query = Lead::query();

        // 1. Filtrado por Rol de Usuario
        if ($user?->isAgent()) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereNull('user_id');
            });
        }

        // 2. Filtrado por Rango de Fechas del Dashboard
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $leadsByCity = $query
            ->selectRaw("COALESCE(NULLIF(city, ''), 'Sin Especificar') as city_label, count(*) as total")
            ->groupBy('city_label')
            ->pluck('total', 'city_label')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Volumen de Leads',
                    'data'  => array_values($leadsByCity),
                    'backgroundColor' => [
                        '#8759a5', // Morado NFC
                        '#28a745', 
                        '#ffc107', 
                        '#dc3545', 
                        '#17a2b8', 
                        '#6c757d'
                    ],
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