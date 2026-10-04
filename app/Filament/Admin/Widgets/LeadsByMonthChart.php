<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class LeadsByMonthChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Ingreso de Prospectos (Tendencia Mensual)';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $startDate = !empty($this->filters['startDate']) 
            ? Carbon::parse($this->filters['startDate']) 
            : null;

        $endDate = !empty($this->filters['endDate']) 
            ? Carbon::parse($this->filters['endDate']) 
            : null;

        // Determinar el rango dinamico o tomar los últimos 6 meses por defecto
        $startMonth   = $startDate ? $startDate->copy()->startOfMonth() : Carbon::now()->subMonths(5)->startOfMonth();
        $endMonth     = $endDate ? $endDate->copy()->endOfMonth() : Carbon::now()->endOfMonth();
        $currentMonth = $startMonth->copy();

        $data   = [];
        $labels = [];

        while ($currentMonth->lessThanOrEqualTo($endMonth)) {
            $query = Lead::query()
                ->whereYear('created_at', $currentMonth->year)
                ->whereMonth('created_at', $currentMonth->month);

            // Filtrado por Rol
            if ($user?->isAgent()) {
                $query->where(function (Builder $q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhereNull('user_id');
                });
            }

            $labels[] = ucfirst($currentMonth->translatedFormat('M Y'));
            $data[]   = $query->count();

            $currentMonth->addMonth();
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Nuevos Prospectos',
                    'data'            => $data,
                    'borderColor'     => '#8759a5',
                    'backgroundColor' => 'rgba(135, 89, 165, 0.15)',
                    'fill'            => true,
                    'tension'         => 0.3,
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