<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PatientResource\Pages;
use App\Filament\Admin\Resources\PatientResource\Schemas\PatientCardInfolist;
use App\Models\Patient;
use App\Models\User;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Pacientes / Expedientes';

    protected static ?string $modelLabel = 'Paciente';

    protected static ?string $pluralModelLabel = 'Pacientes';

    protected static ?string $navigationGroup = 'Gestión Clínica';

    protected static ?int $navigationSort = 1;

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

    public static function canView(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User || ! $record instanceof Patient) {
            return false;
        }

        if (! $user->canAccessClinicalOperations()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $record->clinic_id !== null && $user->canAccessClinic((int) $record->clinic_id);
    }

    public static function canCreate(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User || ! $user->canAccessClinicalOperations()) {
            return false;
        }

        return $user->isAdmin() || ! empty($user->accessibleClinicIds());
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User || ! $record instanceof Patient) {
            return false;
        }

        if (! $user->canAccessClinicalOperations()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $record->clinic_id !== null && $user->canAccessClinic((int) $record->clinic_id);
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User || ! $record instanceof Patient) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isZoneManager()) {
            return $record->clinic_id !== null && $user->canAccessClinic((int) $record->clinic_id);
        }

        return false;
    }

    public static function canDeleteAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->isAdmin() || $user?->isZoneManager() || false;
    }

    public static function resolveDefaultClinicId(?User $actor): ?int
    {
        if (! $actor instanceof User) {
            return null;
        }

        $primaryId = $actor->primaryClinic()?->id;
        if ($primaryId) {
            return (int) $primaryId;
        }

        if ($actor->clinic_id) {
            return (int) $actor->clinic_id;
        }

        $accessible = $actor->accessibleClinicIds();

        return ! empty($accessible) ? (int) $accessible[0] : null;
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

        return $query->whereIn('clinic_id', $clinicIds);
    }

    /*
    |--------------------------------------------------------------------------
    | Tarjeta del Paciente (Infolist)
    |--------------------------------------------------------------------------
    */

    public static function infolist(Infolist $infolist): Infolist
    {
        return PatientCardInfolist::configure($infolist);
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
                Forms\Components\Section::make('Información General y Adscripción')
                    ->description('Datos personales básicos del paciente y sucursal de origen.')
                    ->schema([
                        Forms\Components\TextInput::make('full_name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nombre Completo'),

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
                            ->default(fn (): ?int => static::resolveDefaultClinicId(auth()->user()))
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
                            ->dehydrated()
                            ->required()
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                /** @var User|null $actor */
                                $actor = auth()->user();

                                if (! $actor instanceof User) {
                                    $fail('No tienes autorización para asignar una sucursal.');
                                    return;
                                }

                                if (! $actor->isAdmin() && ! $actor->canAccessClinic((int) $value)) {
                                    $fail('No tienes acceso a la sucursal seleccionada.');
                                }
                            })
                            ->searchable()
                            ->preload()
                            ->label('Sucursal de Adscripción (Física o Virtual)'),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(30)
                            ->label('Teléfono'),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->label('Correo Electrónico'),

                        Forms\Components\DatePicker::make('birthdate')
                            ->maxDate(now())
                            ->label('Fecha de Nacimiento'),
                    ])->columns(2),

                Forms\Components\Section::make('Expediente Clínico NOM-004')
                    ->description('Información legal requerida para expedientes clínicos normativos.')
                    ->schema([
                        Forms\Components\TextInput::make('curp')
                            ->maxLength(18)
                            ->regex('/^[A-Z]{4}\d{6}[HM][A-Z]{5}[0-9A-Z]\d$/i')
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper(trim($state)) : null)
                            ->unique(ignoreRecord: true)
                            ->label('CURP'),

                        Forms\Components\Select::make('gender')
                            ->options([
                                'M' => 'Masculino',
                                'F' => 'Femenino',
                                'O' => 'Otro',
                            ])
                            ->in(['M', 'F', 'O'])
                            ->label('Género'),

                        Forms\Components\Textarea::make('address')
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->label('Dirección Completa'),

                        Forms\Components\TextInput::make('emergency_contact_name')
                            ->maxLength(255)
                            ->label('Contacto de Emergencia (Nombre)'),

                        Forms\Components\TextInput::make('emergency_contact_phone')
                            ->tel()
                            ->maxLength(30)
                            ->label('Contacto de Emergencia (Teléfono)'),
                    ])->columns(2),

                Forms\Components\Section::make('Requisito de EEG Previo al Protocolo')
                    ->description('Indica si el paciente requiere programar un estudio EEG o si cuenta con un estudio externo previo aceptado por NFC.')
                    ->schema([
                        Forms\Components\Radio::make('eeg_origin')
                            ->label('Situación del Estudio EEG')
                            ->options([
                                'schedule_required' => 'Programar Estudio EEG en sucursal',
                                'external_prior'    => 'Cuenta con EEG previo (Estudio externo)',
                            ])
                            ->in(['schedule_required', 'external_prior'])
                            ->default('schedule_required')
                            ->live()
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\DateTimePicker::make('eeg_scheduled_at')
                            ->label('Fecha y Hora Programada del EEG')
                            ->visible(fn (Forms\Get $get): bool => $get('eeg_origin') === 'schedule_required')
                            ->required(fn (Forms\Get $get): bool => $get('eeg_origin') === 'schedule_required')
                            ->seconds(false)
                            ->native(false),

                        Forms\Components\Textarea::make('eeg_notes')
                            ->label('Indicaciones / Observaciones del Estudio')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->searchable()
                    ->sortable()
                    ->label('Paciente'),

                Tables\Columns\TextColumn::make('clinic.name')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->label('Sucursal'),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->label('Teléfono'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable()
                    ->label('Correo'),

                Tables\Columns\TextColumn::make('curp')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('CURP'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Registrado el'),
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
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Tarjeta'),
                Tables\Actions\EditAction::make(),
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
            'index'  => Pages\ListPatients::route('/'),
            'create' => Pages\CreatePatient::route('/create'),
            'view'   => Pages\ViewPatient::route('/{record}'),
            'edit'   => Pages\EditPatient::route('/record/{record}/edit'),
        ];
    }
}