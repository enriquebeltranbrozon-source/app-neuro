<?php

namespace App\Filament\Admin\Resources;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\PatientSessionResource\Pages;
use App\Models\PatientSession;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PatientSessionResource extends Resource
{
    protected static ?string $model = PatientSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Sesiones y Protocolos';

    protected static ?string $modelLabel = 'Sesión Clínica';

    protected static ?string $pluralModelLabel = 'Sesiones Clínicas';

    protected static ?string $navigationGroup = 'Gestión Clínica';

    protected static ?int $navigationSort = 2;

    /*
    |--------------------------------------------------------------------------
    | Autorización y Aislamiento Multiclínica
    |--------------------------------------------------------------------------
    */

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->canAccessClinicalOperations() ?? false;
    }

    public static function canCreate(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->canAccessClinicalOperations() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User || ! $record instanceof PatientSession) {
            return false;
        }

        if (! $user->canAccessClinicalOperations()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ((bool) $record->is_signed) {
            return false;
        }

        $clinicId = $record->clinic_id ?? $record->patient?->clinic_id;

        if ($clinicId !== null && ! $user->canAccessClinic((int) $clinicId)) {
            return false;
        }

        if ($user->isTherapist()) {
            return (int) $record->user_id === (int) $user->id
                || ($clinicId !== null && $user->canAccessClinic((int) $clinicId));
        }

        return $clinicId !== null && $user->canAccessClinic((int) $clinicId);
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User || ! $record instanceof PatientSession) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ((bool) $record->is_signed) {
            return false;
        }

        if ($user->isZoneManager()) {
            $clinicId = $record->clinic_id ?? $record->patient?->clinic_id;

            return $clinicId !== null && $user->canAccessClinic((int) $clinicId);
        }

        return false;
    }

    public static function canDeleteAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->isAdmin() || $user?->isZoneManager() || false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User || ! $user->canAccessClinicalOperations()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        $clinicIds = $user->accessibleClinicIds();

        if (empty($clinicIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $subQuery) use ($clinicIds) {
            $subQuery
                ->whereIn('clinic_id', $clinicIds)
                ->orWhereHas('patient', fn (Builder $q) => $q->whereIn('clinic_id', $clinicIds));
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Formulario y Tabla
    |--------------------------------------------------------------------------
    */

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Asignación de la Sesión')
                    ->schema([
                        Forms\Components\Select::make('clinic_id')
                            ->relationship(
                                name: 'clinic',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query) {
                                    /** @var User|null $actor */
                                    $actor = auth()->user();

                                    if ($actor?->isAdmin()) {
                                        return $query;
                                    }

                                    if ($actor instanceof User) {
                                        $ids = $actor->accessibleClinicIds();

                                        return empty($ids)
                                            ? $query->whereRaw('1 = 0')
                                            : $query->whereIn('id', $ids);
                                    }

                                    return $query->whereRaw('1 = 0');
                                }
                            )
                            ->default(function () {
                                /** @var User|null $actor */
                                $actor = auth()->user();

                                return $actor?->primaryClinic()?->id
                                    ?? ($actor?->clinic_id ? (int) $actor->clinic_id : null);
                            })
                            ->visible(function () {
                                /** @var User|null $actor */
                                $actor = auth()->user();

                                if (! $actor instanceof User) {
                                    return false;
                                }

                                if ($actor->isAdmin() || $actor->isZoneManager()) {
                                    return true;
                                }

                                return count($actor->accessibleClinicIds()) > 1;
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->label('Sucursal de Atención'),

                        Forms\Components\Select::make('patient_id')
                            ->relationship(
                                name: 'patient',
                                titleAttribute: 'full_name',
                                modifyQueryUsing: function (Builder $query, Get $get) {
                                    /** @var User|null $actor */
                                    $actor = auth()->user();
                                    $selectedClinicId = $get('clinic_id')
                                        ?? ($actor?->primaryClinic()?->id ?? ($actor?->clinic_id ? (int) $actor->clinic_id : null));

                                    if (! $actor instanceof User || ! $actor->canAccessClinicalOperations()) {
                                        return $query->whereRaw('1 = 0');
                                    }

                                    if (! $actor->isAdmin()) {
                                        $query->whereIn('clinic_id', $actor->accessibleClinicIds());
                                    }

                                    if (filled($selectedClinicId)) {
                                        $query->where('clinic_id', (int) $selectedClinicId);
                                    }

                                    return $query;
                                }
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Paciente'),

                        Forms\Components\Select::make('user_id')
                            ->relationship(
                                name: 'user',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query, Get $get) {
                                    /** @var User|null $actor */
                                    $actor = auth()->user();
                                    $selectedClinicId = $get('clinic_id')
                                        ?? ($actor?->primaryClinic()?->id ?? ($actor?->clinic_id ? (int) $actor->clinic_id : null));

                                    $query->whereIn('role', [
                                        UserRole::Therapist->value,
                                        UserRole::Admin->value,
                                    ]);

                                    if (filled($selectedClinicId)) {
                                        $clinicId = (int) $selectedClinicId;
                                        $query->where(function (Builder $q) use ($clinicId) {
                                            $q->where('clinic_id', $clinicId)
                                                ->orWhereHas('clinics', fn (Builder $cq) => $cq->where('clinics.id', $clinicId));
                                        });
                                    } elseif ($actor instanceof User && ! $actor->isAdmin()) {
                                        $allowed = $actor->accessibleClinicIds();
                                        $query->where(function (Builder $q) use ($allowed) {
                                            $q->whereIn('clinic_id', $allowed)
                                                ->orWhereHas('clinics', fn (Builder $cq) => $cq->whereIn('clinics.id', $allowed));
                                        });
                                    }

                                    return $query;
                                }
                            )
                            ->default(function () {
                                /** @var User|null $actor */
                                $actor = auth()->user();

                                return $actor?->isTherapist() ? $actor->id : null;
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Terapeuta Asignado'),

                        Forms\Components\Select::make('service_id')
                            ->relationship('service', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Servicio / Tipo de Sesión'),
                    ])->columns(2),

                Forms\Components\Section::make('Control de Tiempo y Auditoría')
                    ->description('Fechas y marcas de tiempo exactas para control operativo y cálculo de nómina de terapeutas.')
                    ->schema([
                        Forms\Components\DateTimePicker::make('started_at')
                            ->required()
                            ->label('Inicio de Sesión'),

                        Forms\Components\DateTimePicker::make('ended_at')
                            ->label('Fin de Sesión'),
                    ])->columns(2),

                Forms\Components\Section::make('Metadatos Clínicos y Protocolos (NOM-004)')
                    ->description('Registro estructurado en formato JSON para protocolos de neurofeedback, frecuencias y observaciones.')
                    ->schema([
                        Forms\Components\KeyValue::make('session_metadata')
                            ->keyLabel('Parámetro / Protocolo')
                            ->valueLabel('Detalle / Observación')
                            ->columnSpanFull()
                            ->label('Metadatos de la Sesión'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clinic.name')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->placeholder(fn (PatientSession $record): string => $record->patient?->clinic?->name ?? 'Sin sucursal')
                    ->label('Sucursal'),

                Tables\Columns\TextColumn::make('patient.full_name')
                    ->searchable()
                    ->sortable()
                    ->label('Paciente'),

                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->label('Terapeuta'),

                Tables\Columns\TextColumn::make('service.name')
                    ->badge()
                    ->color('success')
                    ->label('Servicio'),

                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Inició'),

                Tables\Columns\TextColumn::make('ended_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Finalizó'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('clinic_id')
                    ->relationship('clinic', 'name', modifyQueryUsing: function (Builder $query) {
                        /** @var User|null $actor */
                        $actor = auth()->user();

                        if ($actor?->isAdmin()) {
                            return $query;
                        }

                        if ($actor instanceof User) {
                            $ids = $actor->accessibleClinicIds();

                            return empty($ids)
                                ? $query->whereRaw('1 = 0')
                                : $query->whereIn('id', $ids);
                        }

                        return $query->whereRaw('1 = 0');
                    })
                    ->label('Sucursal'),

                Tables\Filters\SelectFilter::make('service_id')
                    ->relationship('service', 'name')
                    ->label('Servicio'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => static::canDeleteAny()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPatientSessions::route('/'),
            'create' => Pages\CreatePatientSession::route('/create'),
            'edit' => Pages\EditPatientSession::route('/edit-record/{record}'),
        ];
    }
}