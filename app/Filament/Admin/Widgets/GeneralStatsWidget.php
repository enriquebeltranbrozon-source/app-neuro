<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Lead;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GeneralStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $startDate = $this->filters['startDate'] ?? null;
        $endDate   = $this->filters['endDate'] ?? null;

        // Consulta base con filtro dinámico de fechas
        $baseQuery = Lead::query();

        if ($startDate) {
            $baseQuery->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $baseQuery->whereDate('created_at', '<=', $endDate);
        }

        // Vista condicional para Agente Comercial
        if ($user?->isAgent()) {
            $myLeadsCount = (clone $baseQuery)
                ->where('user_id', $user->id)
                ->count();

            $pendingContact = (clone $baseQuery)
                ->where('user_id', $user->id)
                ->where('status', 'abierto')
                ->count();

            $unassignedPool = (clone $baseQuery)
                ->whereNull('user_id')
                ->count();

            return [
                Stat::make('Mis Leads Asignados', $myLeadsCount)
                    ->description('Prospectos en tu cartera')
                    ->descriptionIcon('heroicon-m-user-group')
                    ->color('info'),

                Stat::make('Pendientes de Contacto', $pendingContact)
                    ->description('Estado "Abierto" sin atender')
                    ->descriptionIcon('heroicon-m-clock')
                    ->color($pendingContact > 0 ? 'warning' : 'success'),

                Stat::make('Buzón General (Sin Asignar)', $unassignedPool)
                    ->description('Disponibles para tomar')
                    ->descriptionIcon('heroicon-m-inbox-arrow-down')
                    ->color('primary'),
            ];
        }

        // Vista Global para Administrador y Marketing
        $totalLeads = (clone $baseQuery)->count();

        $pautaLeads = (clone $baseQuery)
            ->where(function ($q) {
                $q->whereNotNull('gclid')
                  ->orWhereNotNull('utm_source');
            })
            ->count();

        $pautaPercentage = $totalLeads > 0 
            ? round(($pautaLeads / $totalLeads) * 100, 1) 
            : 0;

        $whatsappMatched = (clone $baseQuery)
            ->whereNotNull('whatsapp_matched_at')
            ->count();

        $unassignedCount = (clone $baseQuery)
            ->whereNull('user_id')
            ->count();

        return [
            Stat::make('Total de Prospectos', $totalLeads)
                ->description('Capturados en plataforma')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Atribución Pauta / Ads', "{$pautaPercentage}%")
                ->description("{$pautaLeads} leads vía Google/Meta Ads")
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('success'),

            Stat::make('Match WhatsApp', $whatsappMatched)
                ->description('Conversiones de clic a chat')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info'),

            Stat::make('Sin Asignar', $unassignedCount)
                ->description('Requieren asignación de agente')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($unassignedCount > 0 ? 'danger' : 'success'),
        ];
    }
}