<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LeadStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalLeads = Lead::count();
        $contacted = Lead::whereIn('status', ['contactado', 'cita_programada', 'cliente_nuevo'])->count();
        $appointments = Lead::where('status', 'cita_programada')->count();
        $closed = Lead::where('status', 'cliente_nuevo')->count();

        $conversionRate = $totalLeads > 0 ? round(($closed / $totalLeads) * 100, 1) : 0;

        return [
            Stat::make('Total Prospectos', $totalLeads)
                ->description('Capturados en sistema')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('gray'),

            Stat::make('Contactados', $contacted)
                ->description('En seguimiento activo')
                ->descriptionIcon('heroicon-m-phone')
                ->color('warning'),

            Stat::make('Citas Programadas', $appointments)
                ->description('Agenda activa')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),

            Stat::make('Clientes Nuevos', $closed)
                ->description("Tasa de conversión: {$conversionRate}%")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}