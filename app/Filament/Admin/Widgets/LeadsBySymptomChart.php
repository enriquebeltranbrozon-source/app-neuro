<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class LeadsBySymptomChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Distribución por Padecimiento';
    protected static ?int $sort = 4;

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

        // 2. Filtrado por Fechas del Dashboard
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $leadsBySymptom = $query
            ->selectRaw("COALESCE(NULLIF(main_symptom, ''), 'Sin Especificar') as symptom_label, count(*) as total")
            ->groupBy('symptom_label')
            ->pluck('total', 'symptom_label')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Padecimientos',
                    'data'  => array_values($leadsBySymptom),
                    'backgroundColor' => [
                        '#8759a5', 
                        '#28a745', 
                        '#ffc107', 
                        '#dc3545', 
                        '#17a2b8', 
                        '#6f42c1', 
                        '#e83e8c', 
                        '#6c757d'
                    ],
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