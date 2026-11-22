<?php

namespace App\Filament\Admin\Resources;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\Clinic;
use App\Models\User;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = 'Personal';

    protected static ?string $pluralModelLabel = 'Terapeutas y Equipo';

    protected static ?string $navigationLabel = 'Equipo Clínico';

    protected static ?string $navigationGroup = 'Administración';

    /*
    |--------------------------------------------------------------------------
    | Roles gestionables por ZoneManager
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, string>
     */
    public static function zoneManageableRoles(): array
    {
        return [
            UserRole::Therapist->value,
            UserRole::Agent->value,
            UserRole::EegTechnician->value,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Autorización y Alcance (RBAC + Multiclínica)
    |--------------------------------------------------------------------------
    */

    public static function canViewAny(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->isAdmin() || $user?->isZoneManager() || false;
    }

    public static function canCreate(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->isAdmin() || $user?->isZoneManager() || false;
    }

    public static function canEdit(Model $record): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if (! $user instanceof User || ! $record instanceof User) {
            return false;
        }

        return $user->canManageStaffUser($record);
    }

    public static function canDelete(Model $record): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if (! $user instanceof User || ! $record instanceof User) {
            return false;
        }

        if ($user->id === $record->id) {
            return false;
        }

        return $user->canManageStaffUser($record);
    }

    public static function canDeleteAny(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->isAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isZoneManager()) {
            $clinicIds = $user->accessibleClinicIds();

            if (empty($clinicIds)) {
                return $query->whereRaw('1 = 0');
            }

            return $query
                ->whereIn('role', self::zoneManageableRoles())
                ->where(function (Builder $subQuery) use ($clinicIds) {
                    $subQuery
                        ->whereIn('clinic_id', $clinicIds)
                        ->orWhereHas('clinics', fn (Builder $q) => $q->whereIn('clinics.id', $clinicIds));
                });
        }

        return $query->whereRaw('1 = 0');
    }

    /*
    |--------------------------------------------------------------------------
    | Sincronización de Pivote (is_primary / is_clinic_admin)
    |--------------------------------------------------------------------------
    */

    public static function syncClinicPivot(User $record, ?int $managedClinicId, ?int $primaryClinicId = null): void
    {
        DB::transaction(function () use ($record, $managedClinicId, $primaryClinicId): void {
            $roleEnum = $record->role instanceof UserRole
                ? $record->role
                : UserRole::tryFromFlexible((string) $record->role);

            $isGlobalAdmin      = $roleEnum === UserRole::Admin;
            $effectiveManagedId = $isGlobalAdmin ? null : $managedClinicId;

            if ($effectiveManagedId !== null) {
                DB::table('clinic_user')
                    ->where('clinic_id', $effectiveManagedId)
                    ->lockForUpdate()
                    ->get();

                $otherClinicAdminExists = DB::table('clinic_user')
                    ->where('clinic_id', $effectiveManagedId)
                    ->where('is_clinic_admin', true)
                    ->where('user_id', '!=', $record->id)
                    ->exists();

                if ($otherClinicAdminExists) {
                    throw new \RuntimeException('Esta clínica ya cuenta con un administrador local asignado.');
                }

                $record->clinics()->syncWithoutDetaching([
                    $effectiveManagedId => ['is_primary' => false, 'is_clinic_admin' => false],
                ]);
            }

            DB::table('clinic_user')
                ->where('user_id', $record->id)
                ->lockForUpdate()
                ->get();

            $effectivePrimaryId = $primaryClinicId
                ?? ($record->clinic_id ? (int) $record->clinic_id : null)
                ?? $effectiveManagedId
                ?? (int) ($record->clinics()->pluck('clinics.id')->first() ?? 0);

            if ($effectivePrimaryId > 0 && (int) $record->clinic_id !== $effectivePrimaryId) {
                $record->forceFill(['clinic_id' => $effectivePrimaryId])->saveQuietly();
            }

            DB::table('clinic_user')
                ->where('user_id', $record->id)
                ->update(['is_clinic_admin' => false]);

            $clinicIds = $record->clinics()->pluck('clinics.id')->map(fn ($id) => (int) $id)->all();

            foreach ($clinicIds as $clinicId) {
                $record->clinics()->updateExistingPivot($clinicId, [
                    'is_primary'      => $effectivePrimaryId > 0 && $clinicId === $effectivePrimaryId,
                    'is_clinic_admin' => $effectiveManagedId !== null && $clinicId === $effectiveManagedId,
                ]);
            }
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
                Forms\Components\Section::make('Datos de la Cuenta')
                    ->description('Credenciales, sucursales asignadas, rol y administración local única.')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre Completo')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\Select::make('role')
                            ->label('Rol del Sistema')
                            ->options(function () {
                                /** @var \App\Models\User|null $actor */
                                $actor = auth()->user();

                                if ($actor?->isAdmin()) {
                                    return collect(UserRole::cases())
                                        ->mapWithKeys(fn (UserRole $role) => [$role->value => $role->getLabel()])
                                        ->all();
                                }

                                return [
                                    UserRole::Therapist->value     => UserRole::Therapist->getLabel(),
                                    UserRole::Agent->value         => UserRole::Agent->getLabel(),
                                    UserRole::EegTechnician->value => UserRole::EegTechnician->getLabel(),
                                ];
                            })
                            ->default(UserRole::Therapist->value)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if ($state === UserRole::Admin->value) {
                                    $set('managed_clinic_id', null);
                                }
                            })
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('clinics')
                            ->label('Sucursales Asignadas')
                            ->relationship(
                                name: 'clinics',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query) {
                                    /** @var \App\Models\User|null $actor */
                                    $actor = auth()->user();

                                    if ($actor?->isAdmin()) {
                                        return $query->where('clinics.is_active', true);
                                    }

                                    if ($actor?->isZoneManager()) {
                                        return $query
                                            ->where('clinics.is_active', true)
                                            ->whereIn('clinics.id', $actor->accessibleClinicIds());
                                    }

                                    return $query->whereRaw('1 = 0');
                                }
                            )
                            ->multiple()
                            ->live()
                            ->preload()
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('clinic_id')
                            ->label('Sucursal Principal / Base')
                            ->relationship(
                                name: 'clinic',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query, Get $get) {
                                    /** @var \App\Models\User|null $actor */
                                    $actor = auth()->user();
                                    $selectedClinics = collect($get('clinics') ?? [])
                                        ->map(fn ($id) => (int) $id)
                                        ->filter(fn (int $id) => $id > 0)
                                        ->values()
                                        ->all();

                                    if ($actor?->isAdmin()) {
                                        if (! empty($selectedClinics)) {
                                            $query->whereIn('clinics.id', $selectedClinics);
                                        }

                                        return $query->where('clinics.is_active', true);
                                    }

                                    if ($actor?->isZoneManager()) {
                                        $allowed = $actor->accessibleClinicIds();
                                        if (! empty($selectedClinics)) {
                                            $allowed = array_values(array_intersect($allowed, $selectedClinics));
                                        }

                                        return $query
                                            ->where('clinics.is_active', true)
                                            ->whereIn('clinics.id', $allowed);
                                    }

                                    return $query->whereRaw('1 = 0');
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Select::make('managed_clinic_id')
                            ->label('Administra Localmente esta Clínica (Máx. 1)')
                            ->helperText('Opcional. Solo se permite 1 administrador local por clínica y 1 clínica administrada por usuario.')
                            ->dehydrated(true)
                            ->afterStateHydrated(function (Forms\Components\Select $component, ?User $record) {
                                if (! $record) {
                                    $component->state(null);

                                    return;
                                }

                                $component->state($record->managedClinicId());
                            })
                            ->options(function (Get $get): array {
                                /** @var \App\Models\User|null $actor */
                                $actor = auth()->user();
                                $selectedClinics = collect($get('clinics') ?? [])
                                    ->map(fn ($id) => (int) $id)
                                    ->filter(fn (int $id) => $id > 0)
                                    ->values()
                                    ->all();

                                $query = Clinic::query()->where('clinics.is_active', true);

                                if (! empty($selectedClinics)) {
                                    $query->whereIn('clinics.id', $selectedClinics);
                                } elseif ($actor?->isZoneManager()) {
                                    $query->whereIn('clinics.id', $actor->accessibleClinicIds());
                                } elseif (! $actor?->isAdmin()) {
                                    return [];
                                }

                                return $query->orderBy('name')->pluck('name', 'id')->all();
                            })
                            ->visible(fn (Get $get): bool => $get('role') !== UserRole::Admin->value)
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->rules([
                                fn (?User $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    if (blank($value)) {
                                        return;
                                    }

                                    $clinicId = (int) $value;
                                    $query = DB::table('clinic_user')
                                        ->where('clinic_id', $clinicId)
                                        ->where('is_clinic_admin', true);

                                    if ($record instanceof User) {
                                        $query->where('user_id', '!=', $record->id);
                                    }

                                    if ($query->exists()) {
                                        $fail('Esta clínica ya cuenta con un administrador local asignado.');
                                    }
                                },
                            ]),

                        Forms\Components\TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Correo copiado'),

                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('clinics.name')
                    ->label('Sucursales Asignadas')
                    ->badge()
                    ->separator(', ')
                    ->placeholder('Sin sucursales'),

                Tables\Columns\TextColumn::make('managed_clinic_label')
                    ->label('Administra Clínica')
                    ->badge()
                    ->color('warning')
                    ->state(function (User $record): ?string {
                        if ($record->isAdmin()) {
                            return 'Global';
                        }

                        return $record->managedClinic()?->name;
                    })
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('clinic.name')
                    ->label('Sede Base')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Alta')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('clinic_id')
                    ->label('Filtrar por Sede Base')
                    ->relationship('clinic', 'name', modifyQueryUsing: function (Builder $query) {
                        /** @var \App\Models\User|null $actor */
                        $actor = auth()->user();

                        if ($actor?->isAdmin()) {
                            return $query;
                        }

                        if ($actor?->isZoneManager()) {
                            return $query->whereIn('clinics.id', $actor->accessibleClinicIds());
                        }

                        return $query->whereRaw('1 = 0');
                    }),

                Tables\Filters\SelectFilter::make('role')
                    ->label('Filtrar por Rol')
                    ->options(function () {
                        /** @var \App\Models\User|null $actor */
                        $actor = auth()->user();

                        if ($actor?->isAdmin()) {
                            return collect(UserRole::cases())
                                ->mapWithKeys(fn (UserRole $role) => [$role->value => $role->getLabel()])
                                ->all();
                        }

                        return [
                            UserRole::Therapist->value     => UserRole::Therapist->getLabel(),
                            UserRole::Agent->value         => UserRole::Agent->getLabel(),
                            UserRole::EegTechnician->value => UserRole::EegTechnician->getLabel(),
                        ];
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
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
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}