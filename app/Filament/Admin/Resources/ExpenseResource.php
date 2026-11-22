<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Egresos y Caja Chica';

    protected static ?string $modelLabel = 'Egreso';

    protected static ?string $pluralModelLabel = 'Egresos';

    protected static ?string $navigationGroup = 'Finanzas y Administración';

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

        if (! $user instanceof User || ! $record instanceof Expense) {
            return false;
        }

        if (! $user->canAccessClinicalOperations()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $clinicId = $record->clinic_id ?? $record->user?->clinic_id;

        if ($clinicId !== null && ! $user->canAccessClinic((int) $clinicId)) {
            return false;
        }

        if ($user->isZoneManager()) {
            return true;
        }

        return (int) $record->user_id === (int) $user->id;
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User || ! $record instanceof Expense) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isZoneManager()) {
            $clinicId = $record->clinic_id ?? $record->user?->clinic_id;

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

        if ($user->isZoneManager()) {
            return $query->whereIn('clinic_id', $clinicIds);
        }

        return $query
            ->whereIn('clinic_id', $clinicIds)
            ->where('user_id', $user->id);
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
                Forms\Components\Section::make('Detalles del Gasto')
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
                            ->label('Sucursal / Clínica'),

                        Forms\Components\Select::make('expense_category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Categoría de Egreso'),

                        Forms\Components\TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('$')
                            ->label('Monto'),

                        Forms\Components\DatePicker::make('expense_date')
                            ->required()
                            ->default(now())
                            ->label('Fecha del Gasto'),

                        Forms\Components\Select::make('user_id')
                            ->relationship(
                                name: 'user',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query) {
                                    /** @var User|null $actor */
                                    $actor = auth()->user();

                                    if ($actor?->isAdmin()) {
                                        return $query;
                                    }

                                    if ($actor instanceof User && $actor->isZoneManager()) {
                                        $clinicIds = $actor->accessibleClinicIds();

                                        return $query->where(function (Builder $q) use ($clinicIds, $actor) {
                                            $q->whereIn('clinic_id', $clinicIds)
                                                ->orWhere('id', $actor->id)
                                                ->orWhereHas('clinics', fn (Builder $cq) => $cq->whereIn('clinics.id', $clinicIds));
                                        });
                                    }

                                    return $query->where('id', auth()->id());
                                }
                            )
                            ->required()
                            ->default(fn () => auth()->id())
                            ->disabled(fn () => ! (auth()->user()?->isAdmin() || auth()->user()?->isZoneManager()))
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->label('Registrado por'),
                    ])->columns(2),

                Forms\Components\Section::make('Comprobante y Observaciones')
                    ->schema([
                        Forms\Components\TextInput::make('description')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->label('Concepto / Descripción breve'),

                        Forms\Components\Toggle::make('has_invoice')
                            ->required()
                            ->default(false)
                            ->label('¿Cuenta con Factura / Comprobante Fiscal?'),

                        Forms\Components\Textarea::make('notes')
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->label('Notas Adicionales'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('expense_date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->label('Fecha'),

                Tables\Columns\TextColumn::make('clinic.name')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->placeholder('Sin sucursal')
                    ->label('Sucursal'),

                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->sortable()
                    ->label('Concepto'),

                Tables\Columns\TextColumn::make('category.name')
                    ->badge()
                    ->color('warning')
                    ->sortable()
                    ->label('Categoría'),

                Tables\Columns\TextColumn::make('amount')
                    ->money('MXN')
                    ->sortable()
                    ->label('Monto'),

                Tables\Columns\IconColumn::make('has_invoice')
                    ->boolean()
                    ->label('Facturado'),

                Tables\Columns\TextColumn::make('user.name')
                    ->sortable()
                    ->toggleable()
                    ->label('Registró'),
            ])
            ->defaultSort('expense_date', 'desc')
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

                Tables\Filters\SelectFilter::make('expense_category_id')
                    ->relationship('category', 'name')
                    ->label('Categoría'),

                Tables\Filters\TernaryFilter::make('has_invoice')
                    ->label('Facturación')
                    ->trueLabel('Con Factura')
                    ->falseLabel('Sin Factura'),
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
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/edit-record/{record}'),
        ];
    }
}