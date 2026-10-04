<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CampaignStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalLeads = Lead::count();
        $paidLeads = Lead::whereIn('source', ['google_ads', 'meta_ads'])->count();
        $citasCount = Lead::where('status', 'cita_programada')->count();
        $clientesNuevos = Lead::where('status', 'cliente_nuevo')->count();

        $conversionRate = $totalLeads > 0 
            ? round((($citasCount + $clientesNuevos) / $totalLeads) * 100, 1) 
            : 0;

        $topCampaign = Lead::whereNotNull('utm_campaign')
            ->selectRaw('utm_campaign, COUNT(*) as count')
            ->groupBy('utm_campaign')
            ->orderByDesc('count')
            ->first();

        return [
            Stat::make('Leads de Pauta (Paid)', $paidLeads)
                ->description($totalLeads > 0 ? round(($paidLeads / $totalLeads) * 100) . '% del tráfico total' : '0%')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('info'),

            Stat::make('Tasa de Conversión a Cita/Cliente', $conversionRate . '%')
                ->description($citasCount + $clientesNuevos . ' prospectos calificados')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Campaña Top', $topCampaign?->utm_campaign ?? 'N/A')
                ->description(($topCampaign?->count ?? 0) . ' prospectos generados')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('warning'),
        ];
    }
}