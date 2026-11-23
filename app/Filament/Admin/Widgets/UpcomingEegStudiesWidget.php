<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\EegStudyResource;
use App\Models\EegStudy;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingEegStudiesWidget extends BaseWidget
{
    protected static ?string $heading = 'Próximos Estudios Programados';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return static::hasAllowedRole(auth()->user());
    }

    public function mount(): void
    {
        if (! static::hasAllowedRole(auth()->user())) {
            abort(403);
        }
    }

    protected static function hasAllowedRole(mixed $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return $user->isEegTechnician() || $user->isAdmin() || $user->isZoneManager();
    }

    protected function buildScopedUpcomingQuery(): Builder
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! static::hasAllowedRole($user)) {
            return EegStudy::query()->whereRaw('1 = 0');
        }

        $query = EegStudy::query()
            ->where('status', 'scheduled')
            ->where(function (Builder $q): void {
                $q->whereDate('study_date', '>=', today())
                    ->orWhereNull('study_date');
            })
            ->orderByRaw('study_date IS NULL ASC')
            ->orderBy('study_date', 'asc')
            ->orderBy('id', 'desc');

        if ($user->isAdmin()) {
            return $query;
        }

        $rawIds = $user->accessibleClinicIds();
        $clinicIds = is_array($rawIds)
            ? array_values(array_unique(array_filter(
                array_map(static fn (mixed $id): int => (int) $id, $rawIds),
                static fn (int $id): bool => $id > 0
            )))
            : [];

        if (empty($clinicIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($clinicIds, $user): void {
            $q->whereIn('clinic_id', $clinicIds)
                ->orWhere(function (Builder $pq) use ($clinicIds): void {
                    $pq->whereNull('clinic_id')
                        ->whereHas('patient', fn (Builder $patientQ) => $patientQ->whereIn('clinic_id', $clinicIds));
                })
                ->orWhere(function (Builder $uq) use ($user, $clinicIds): void {
                    $uq->where('uploaded_by', (int) $user->id)
                        ->whereIn('clinic_id', $clinicIds);
                });
        });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->buildScopedUpcomingQuery())
            ->columns([
                Tables\Columns\TextColumn::make('study_date')
                    ->label('Fecha')
                    ->date('D d/m/Y')
                    ->placeholder('Por asignar')
                    ->sortable()
                    ->badge()
                    ->color(function (EegStudy $record): string {
                        if (! $record->study_date) {
                            return 'gray';
                        }

                        return $record->study_date->isToday() ? 'warning' : 'primary';
                    }),

                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Paciente')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('title')
                    ->label('Tipo de Estudio')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('clinic.name')
                    ->label('Sucursal')
                    ->badge()
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('open_study')
                    ->label('Gestionar')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->visible(fn (EegStudy $record): bool => EegStudyResource::canEdit($record))
                    ->url(fn (EegStudy $record): string => EegStudyResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('Sin próximos estudios programados')
            ->emptyStateDescription('No hay estudios pendientes agendados de hoy en adelante.');
    }
}