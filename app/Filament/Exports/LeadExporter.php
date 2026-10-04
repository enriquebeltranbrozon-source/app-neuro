<?php

namespace App\Filament\Exports;

use App\Models\Lead;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class LeadExporter extends Exporter
{
    protected static ?string $model = Lead::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),

            ExportColumn::make('created_at')
                ->label('Fecha de Registro'),

            ExportColumn::make('name')
                ->label('Paciente'),

            ExportColumn::make('phone')
                ->label('Teléfono'),

            ExportColumn::make('email')
                ->label('Email'),

            ExportColumn::make('city')
                ->label('Sede'),

            ExportColumn::make('main_symptom')
                ->label('Padecimiento'),

            ExportColumn::make('status')
                ->label('Estado Comercial'),

            ExportColumn::make('user.name')
                ->label('Agente Asignado'),

            ExportColumn::make('source')
                ->label('Canal / Origen'),

            ExportColumn::make('utm_source')
                ->label('UTM Source'),

            ExportColumn::make('utm_campaign')
                ->label('Campaña'),

            ExportColumn::make('gclid')
                ->label('GCLID'),

            ExportColumn::make('follow_up_date')
                ->label('Próximo Seguimiento'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'La exportación de prospectos ha finalizado y se procesaron ' . number_format($export->successful_rows) . ' registros.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' filas fallaron al exportar.';
        }

        return $body;
    }
}