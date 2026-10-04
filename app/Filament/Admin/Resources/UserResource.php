<?php

namespace App\Filament\Admin\Resources;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = 'Terapeuta';

    protected static ?string $pluralModelLabel = 'Terapeutas y Equipo';

    protected static ?string $navigationLabel = 'Terapeutas';

    protected static ?string $navigationGroup = 'Administración';

    /**
     * Restricción de acceso: Solo usuarios Administradores pueden acceder a este recurso.
     */
    public static function canViewAny(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la Cuenta')
                    ->description('Credenciales, sede asignada y rol del terapeuta o usuario.')
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
                            ->options([
                                UserRole::Admin->value     => 'Administrador',
                                UserRole::Agent->value     => 'Terapeuta / Agente Comercial',
                                UserRole::Marketing->value => 'Analista de Marketing',
                            ])
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('clinic')
                            ->label('Sede / Clínica Asignada')
                            ->options([
                                'del_valle'  => 'Del Valle',
                                'satelite'   => 'Satélite',
                                'metepec'    => 'Metepec',
                                'cuernavaca' => 'Cuernavaca',
                                'monterrey'  => 'Monterrey',
                            ])
                            ->searchable()
                            ->nullable(),

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
                    ->label('Terapeuta')
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
                    ->color(fn (UserRole|string|null $state): string => match ($state instanceof UserRole ? $state->value : $state) {
                        UserRole::Admin->value     => 'danger',
                        UserRole::Agent->value     => 'info',
                        UserRole::Marketing->value => 'warning',
                        default                    => 'gray',
                    })
                    ->formatStateUsing(fn (UserRole|string|null $state): string => match ($state instanceof UserRole ? $state->value : $state) {
                        UserRole::Admin->value     => 'Administrador',
                        UserRole::Agent->value     => 'Terapeuta',
                        UserRole::Marketing->value => 'Marketing',
                        default                    => (string) ($state ?? 'Sin rol'),
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('clinic')
                    ->label('Sede / Clínica')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'del_valle'  => 'info',
                        'satelite'   => 'success',
                        'metepec'    => 'warning',
                        'cuernavaca' => 'primary',
                        'monterrey'  => 'danger',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'del_valle'  => 'Del Valle',
                        'satelite'   => 'Satélite',
                        'metepec'    => 'Metepec',
                        'cuernavaca' => 'Cuernavaca',
                        'monterrey'  => 'Monterrey',
                        default      => $state ?? 'Sin sede',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Alta')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('clinic')
                    ->label('Filtrar por Sede')
                    ->options([
                        'del_valle'  => 'Del Valle',
                        'satelite'   => 'Satélite',
                        'metepec'    => 'Metepec',
                        'cuernavaca' => 'Cuernavaca',
                        'monterrey'  => 'Monterrey',
                    ]),

                Tables\Filters\SelectFilter::make('role')
                    ->label('Filtrar por Rol')
                    ->options([
                        UserRole::Admin->value     => 'Administrador',
                        UserRole::Agent->value     => 'Terapeuta',
                        UserRole::Marketing->value => 'Marketing',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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