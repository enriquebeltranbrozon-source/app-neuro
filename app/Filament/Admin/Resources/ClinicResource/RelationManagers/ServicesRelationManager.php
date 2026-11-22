<?php

namespace App\Filament\Admin\Resources\ClinicResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Table;

class ServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    protected static ?string $title = 'Tarifario y Servicios de la Clínica';

    protected static ?string $modelLabel = 'Servicio';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('price')
                    ->label('Precio en esta Clínica')
                    ->numeric()
                    ->prefix('$')
                    ->required()
                    ->minValue(0),

                Forms\Components\Toggle::make('is_active')
                    ->label('Disponible en esta sucursal')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Servicio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Duración')
                    ->suffix(' min'),

                Tables\Columns\TextColumn::make('pivot.price')
                    ->label('Tarifa Sucursal')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\IconColumn::make('pivot.is_active')
                    ->label('Activo en Sede')
                    ->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Asignar Servicio')
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label('Servicio del Catálogo')
                            ->required(),
                        Forms\Components\TextInput::make('price')
                            ->label('Precio en esta Clínica')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->minValue(0),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Disponible en esta sucursal')
                            ->default(true),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Ajustar Tarifa'),
                Tables\Actions\DetachAction::make()
                    ->label('Quitar'),
            ]);
    }
}