<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Lead;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class CampaignPerformanceTable extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Rendimiento por Campaña (UTM Campaign)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Lead::query()
                    ->selectRaw('
                        MIN(id) as id,
                        COALESCE(utm_campaign, "Sin Campaña / Orgánico") as campaign_name,
                        COALESCE(utm_source, source) as source_channel,
                        COUNT(*) as total_leads,
                        SUM(CASE WHEN status = "contactado" THEN 1 ELSE 0 END) as contactados,
                        SUM(CASE WHEN status = "cita_programada" THEN 1 ELSE 0 END) as citas,
                        SUM(CASE WHEN status = "cliente_nuevo" THEN 1 ELSE 0 END) as clientes
                    ')
                    ->groupBy('campaign_name', 'source_channel')
                    ->orderByDesc('total_leads')
            )
            ->columns([
                Tables\Columns\TextColumn::make('campaign_name')
                    ->label('Campaña')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Sin Campaña / Orgánico' => 'gray',
                        default => 'primary',
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('utm_campaign', 'like', "%{$search}%");
                    }),

                Tables\Columns\TextColumn::make('source_channel')
                    ->label('Canal / Fuente')
                    ->formatStateUsing(fn (?string $state) => strtoupper($state ?? 'Desconocido')),

                Tables\Columns\TextColumn::make('total_leads')
                    ->label('Total Leads')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contactados')
                    ->label('Contactados')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('citas')
                    ->label('Citas Programadas')
                    ->alignCenter()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('clientes')
                    ->label('Clientes Convertidos')
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('conversion_rate')
                    ->label('% Efectividad')
                    ->alignRight()
                    ->state(function ($record): string {
                        if ($record->total_leads == 0) return '0%';
                        $rate = (($record->citas + $record->clientes) / $record->total_leads) * 100;
                        return number_format($rate, 1) . '%';
                    }),
            ]);
    }
}