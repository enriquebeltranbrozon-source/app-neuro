<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Clinic;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ZoneClinicsComparisonWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Comparativa por Clínica de la Zona';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user instanceof User && ($user->isZoneManager() || $user->isAdmin());
    }

    public function table(Table $table): Table
    {
        /** @var User|null $user */
        $user = auth()->user();

        $allowedIds = $user instanceof User
            ? array_values(array_unique(array_filter(
                array_map('intval', $user->accessibleClinicIds()),
                static fn (int $id): bool => $id > 0
            )))
            : [];

        $selectedClinicId = $this->filters['clinic_id'] ?? null;

        if ($selectedClinicId !== null && $selectedClinicId !== '') {
            $selectedInt = (int) $selectedClinicId;
            $queryIds = in_array($selectedInt, $allowedIds, true) ? [$selectedInt] : [];
        } else {
            $queryIds = $allowedIds;
        }

        $query = Clinic::query()
            ->select('clinics.*')
            ->with(['region'])
            ->withCount([
                'patients',
                'assignedUsers',
            ])
            ->whereIn('clinics.id', empty($queryIds) ? [0] : $queryIds)
            ->orderBy('clinics.name');

        if (Schema::hasTable('patient_sessions')) {
            $query->selectSub(function (QueryBuilder $subQuery): void {
                $subQuery->from('patient_sessions')
                    ->selectRaw('COUNT(*)')
                    ->whereIn('patient_sessions.patient_id', function (QueryBuilder $patientSub): void {
                        $patientSub->from('patients')
                            ->select('patients.id')
                            ->whereColumn('patients.clinic_id', 'clinics.id')
                            ->whereNull('patients.deleted_at');
                    });
            }, 'sessions_count');
        } else {
            $query->selectRaw('0 as sessions_count');
        }

        if (Schema::hasTable('eeg_studies')) {
            $query->selectSub(function (QueryBuilder $subQuery): void {
                $subQuery->from('eeg_studies')
                    ->selectRaw('COUNT(*)')
                    ->where(function (QueryBuilder $scope): void {
                        $scope->whereColumn('eeg_studies.clinic_id', 'clinics.id')
                            ->orWhere(function (QueryBuilder $fallback): void {
                                $fallback->whereNull('eeg_studies.clinic_id')
                                    ->whereIn('eeg_studies.patient_id', function (QueryBuilder $patientSub): void {
                                        $patientSub->from('patients')
                                            ->select('patients.id')
                                            ->whereColumn('patients.clinic_id', 'clinics.id')
                                            ->whereNull('patients.deleted_at');
                                    });
                            });
                    });
            }, 'eeg_studies_count');
        } else {
            $query->selectRaw('0 as eeg_studies_count');
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Clínica')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('region.name')
                    ->label('Región')
                    ->placeholder('Sin región')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_virtual')
                    ->label('Modalidad')
                    ->boolean()
                    ->trueIcon('heroicon-o-video-camera')
                    ->falseIcon('heroicon-o-building-storefront')
                    ->trueColor('info')
                    ->falseColor('primary')
                    ->tooltip(fn (Clinic $record): string => $record->is_virtual ? 'Virtual' : 'Presencial'),

                Tables\Columns\TextColumn::make('patients_count')
                    ->label('Pacientes')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sessions_count')
                    ->label('Sesiones')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('eeg_studies_count')
                    ->label('Estudios EEG')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('assigned_users_count')
                    ->label('Personal')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
            ])
            ->paginated(false);
    }
}