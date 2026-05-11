<?php

namespace App\Filament\Ebeltran\Resources;

use App\Filament\Ebeltran\Resources\UserResource\Pages;
use App\Filament\Ebeltran\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'Terapeuta';
    protected static ?string $pluralModelLabel = 'Terapeutas';
    protected static ?string $navigationLabel = 'Terapeutas';
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
{
    return $table
        ->columns([
            // Columna de Nombre
            Tables\Columns\TextColumn::make('name')
                ->label('Terapeuta')
                ->searchable()
                ->sortable(),
            
            // Columna de Email
            Tables\Columns\TextColumn::make('email')
                ->label('Correo Electrónico')
                ->searchable(),

            // Columna de Clínica (Con el estilo de Badge que definimos)
            Tables\Columns\TextColumn::make('clinic')
                ->label('Sede / Clínica')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'del_valle' => 'info',
                    'satelite' => 'success',
                    'metepec' => 'warning',
                    'cuernavaca' => 'primary',
                    'monterrey' => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'del_valle' => 'Del Valle',
                    'satelite' => 'Satélite',
                    'metepec' => 'Metepec',
                    'cuernavaca' => 'Cuernavaca',
                    'monterrey' => 'Monterrey',
                    default => $state,
                }),

            // Columna de Fecha de Creación
            Tables\Columns\TextColumn::make('created_at')
                ->label('Fecha de Alta')
                ->dateTime('d/M/Y')
                ->sortable(),
        ])
        ->filters([
            // Filtro rápido por sede
            Tables\Filters\SelectFilter::make('clinic')
                ->label('Filtrar por Sede')
                ->options([
                    'del_valle' => 'Del Valle',
                    'satelite' => 'Satélite',
                    'metepec' => 'Metepec',
                    'cuernavaca' => 'Cuernavaca',
                    'monterrey' => 'Monterrey',
                ]),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
}

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
