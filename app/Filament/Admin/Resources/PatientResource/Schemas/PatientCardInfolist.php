<?php

namespace App\Filament\Admin\Resources\PatientResource\Schemas;

use App\Models\Patient;
use App\Models\PatientSession;
use App\Models\Protocol;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\Schema;

class PatientCardInfolist
{
    public static function configure(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Tarjeta del Paciente')
                    ->description('Resumen clínico operativo: terapia, sesiones, terapeuta, observaciones, pagos y próxima cita.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('full_name')
                                    ->label('Paciente')
                                    ->weight('bold'),

                                TextEntry::make('clinic.name')
                                    ->label('Sucursal de Adscripción')
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('Sin sucursal asignada'),

                                TextEntry::make('therapy_status')
                                    ->label('Estado de la Terapia')
                                    ->badge()
                                    ->state(fn (Patient $record): string => static::resolveTherapyStatus($record)['label'])
                                    ->color(fn (Patient $record): string => static::resolveTherapyStatus($record)['color']),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('sessions_summary')
                                    ->label('Sesiones Realizadas')
                                    ->weight('bold')
                                    ->state(function (Patient $record): string {
                                        $count = PatientSession::query()
                                            ->where('patient_id', $record->id)
                                            ->count();

                                        return "{$count} " . ($count === 1 ? 'sesión' : 'sesiones');
                                    })
                                    ->helperText(function (Patient $record): string {
                                        $last = static::latestSession($record);
                                        if (! $last) {
                                            return 'Fecha: Sin sesiones registradas';
                                        }

                                        $date = $last->started_at ?? $last->created_at;
                                        $formatted = $date
                                            ? $date->timezone(config('app.timezone'))->format('d/m/Y H:i')
                                            : 'No disponible';

                                        return "Última sesión: {$formatted}";
                                    }),

                                TextEntry::make('last_therapist')
                                    ->label('Terapeuta que Otorgó la Sesión')
                                    ->weight('semibold')
                                    ->state(function (Patient $record): string {
                                        $last = static::latestSession($record);
                                        if (! $last) {
                                            return 'Sin asignar';
                                        }

                                        return $last->therapist?->name
                                            ?? $last->user?->name
                                            ?? 'No especificado';
                                    })
                                    ->helperText('Responsable de la última sesión registrada'),

                                TextEntry::make('payments_summary')
                                    ->label('Pagos Registrados')
                                    ->weight('bold')
                                    ->color('success')
                                    ->state(function (Patient $record): string {
                                        $paidQuery = PatientSession::query()
                                            ->where('patient_id', $record->id)
                                            ->whereIn('payment_status', ['paid', 'covered_by_package']);

                                        $count = (clone $paidQuery)->count();
                                        $total = (float) (clone $paidQuery)->sum('charged_amount');

                                        $label = "{$count} " . ($count === 1 ? 'pago' : 'pagos');

                                        return $total > 0
                                            ? "{$label} ($" . number_format($total, 2) . ')'
                                            : $label;
                                    })
                                    ->helperText('Sesiones pagadas o cubiertas por paquete'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('session_comments')
                                    ->label('Comentarios / Observaciones')
                                    ->columnSpan(2)
                                    ->state(function (Patient $record): string {
                                        $last = static::latestSession($record);
                                        if ($last && is_array($last->session_metadata)) {
                                            $comment = $last->session_metadata['notes']
                                                ?? $last->session_metadata['observations']
                                                ?? $last->session_metadata['comments']
                                                ?? null;

                                            if (is_string($comment) && trim($comment) !== '') {
                                                return trim($comment);
                                            }
                                        }

                                        return $record->eeg_notes ?: 'Sin comentarios registrados.';
                                    }),

                                TextEntry::make('next_appointment')
                                    ->label('Próxima Cita')
                                    ->badge()
                                    ->color('warning')
                                    ->state('Por habilitar (módulo en desarrollo)')
                                    ->helperText('Agenda clínica pendiente de implementación'),
                            ]),
                    ]),
            ]);
    }

    protected static function latestSession(Patient $patient): ?PatientSession
    {
        return PatientSession::query()
            ->with(['therapist', 'user'])
            ->where('patient_id', $patient->id)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{label: string, color: string}
     */
    protected static function resolveTherapyStatus(Patient $patient): array
    {
        if (Schema::hasTable('protocols')) {
            /** @var Protocol|null $protocol */
            $protocol = Protocol::query()
                ->where('patient_id', $patient->id)
                ->latest('id')
                ->first();

            if ($protocol) {
                return match ($protocol->status) {
                    'active'    => ['label' => 'En tratamiento (Protocolo activo)', 'color' => 'success'],
                    'completed' => ['label' => 'Protocolo concluido', 'color' => 'info'],
                    default     => ['label' => 'Protocolo en borrador', 'color' => 'warning'],
                };
            }
        }

        return match ($patient->status) {
            'active'     => ['label' => 'Activo — Sin protocolo', 'color' => 'primary'],
            'paused'     => ['label' => 'Pausado', 'color' => 'warning'],
            'discharged' => ['label' => 'Alta / Egreso', 'color' => 'gray'],
            default      => ['label' => (string) ($patient->status ?: 'Sin estado'), 'color' => 'gray'],
        };
    }
}