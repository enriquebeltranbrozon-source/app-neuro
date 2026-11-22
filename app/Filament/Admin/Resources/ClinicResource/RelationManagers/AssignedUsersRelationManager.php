<?php

namespace App\Filament\Admin\Resources\ClinicResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Table;

class AssignedUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'assignedUsers';

    protected static ?string $title = 'Personal y Terapeutas Asignados';

    protected static ?string $modelLabel = 'Usuario';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('is_primary')
                    ->label('Es su clínica base / primaria')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge(),

                Tables\Columns\IconColumn::make('pivot.is_primary')
                    ->label('Clínica Primaria')
                    ->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Asignar Personal')
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label('Usuario / Terapeuta')
                            ->required(),
                        Forms\Components\Toggle::make('is_primary')
                            ->label('Es su clínica base / primaria')
                            ->default(false),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar Asignación'),
                Tables\Actions\DetachAction::make()
                    ->label('Desvincular'),
            ]);
    }
}