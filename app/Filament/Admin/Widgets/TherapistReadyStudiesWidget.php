<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\EegStudyResource;
use App\Models\EegStudy;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TherapistReadyStudiesWidget extends BaseWidget
{
    protected static ?string $heading = 'Estudios EEG Listos para Revisión / Protocolo';

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

        return $user->isTherapist() || $user->isAdmin() || $user->isZoneManager();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                /** @var User|null $user */
                $user = auth()->user();

                $query = EegStudy::query()
                    ->whereIn('status', ['completed', 'external'])
                    ->latest('study_date')
                    ->latest('id');

                if (! static::hasAllowedRole($user)) {
                    return $query->whereRaw('1 = 0');
                }

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

                return $query->where(function (Builder $q) use ($clinicIds): void {
                    $q->whereIn('clinic_id', $clinicIds)
                        ->orWhere(function (Builder $pq) use ($clinicIds): void {
                            $pq->whereNull('clinic_id')
                                ->whereHas('patient', fn (Builder $patientQ) => $patientQ->whereIn('clinic_id', $clinicIds));
                        });
                });
            })
            ->columns([
                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Paciente')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('title')
                    ->label('Estudio')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Origen / Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'external' ? 'Externo' : 'Completado')
                    ->color(fn (?string $state): string => $state === 'external' ? 'info' : 'success'),

                Tables\Columns\TextColumn::make('clinic.name')
                    ->label('Sucursal')
                    ->badge()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('study_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (EegStudy $record): bool => filled($record->file_path))
                    ->action(function (EegStudy $record): ?StreamedResponse {
                        $disk = Storage::disk('local');

                        if (! $record->file_path || ! $disk->exists($record->file_path)) {
                            return null;
                        }

                        return $disk->download($record->file_path, $record->original_filename ?: basename($record->file_path));
                    }),

                Tables\Actions\Action::make('view_study')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->visible(fn (EegStudy $record): bool => EegStudyResource::canEdit($record))
                    ->url(fn (EegStudy $record): string => EegStudyResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('Sin estudios listos para protocolo')
            ->emptyStateDescription('Aún no hay estudios completados o externos en tu alcance clínico.');
    }
}