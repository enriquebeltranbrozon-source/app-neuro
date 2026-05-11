<?php

namespace App\Filament\Ebeltran\Widgets;

use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class GeneralStatsWidget extends BaseWidget
{
    // Define el orden en el que aparece en el Dashboard (arriba del todo)
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // 1. Cálculo de Leads del mes actual
        $leadsEsteMes = Lead::whereMonth('created_at', Carbon::now()->month)
                            ->whereYear('created_at', Carbon::now()->year)
                            ->count();

        // 2. Cálculo de Tasa de Conversión General
        $totalLeads = Lead::count();
        $clientesNuevos = Lead::where('status', 'cliente_nuevo')->count();
        
        $tasaConversion = $totalLeads > 0 
            ? round(($clientesNuevos / $totalLeads) * 100, 1) 
            : 0;

        // 3. Radar de Fugas (Leads abiertos)
        $leadsSinAtender = Lead::where('status', 'abierto')->count();

        return [
            Stat::make('Nuevos Prospectos (Este Mes)', $leadsEsteMes)
                ->description('Pacientes ingresados en el mes actual')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Tasa de Conversión (Histórica)', $tasaConversion . '%')
                ->description($clientesNuevos . ' ventas cerradas de ' . $totalLeads . ' totales')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('primary'),

            Stat::make('Leads Pendientes / Abiertos', $leadsSinAtender)
                ->description('Requieren seguimiento urgente')
                ->descriptionIcon($leadsSinAtender > 10 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-clock')
                ->color($leadsSinAtender > 10 ? 'danger' : 'warning'),
        ];
    }
}