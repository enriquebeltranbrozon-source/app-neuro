<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ClinicResource\Pages;
use App\Models\Clinic;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClinicResource extends Resource
{
    protected static ?string $model = Clinic::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    
    protected static ?string $navigationLabel = 'Clínicas y Sucursales';
    
    protected static ?string $modelLabel = 'Clínica';
    
    protected static ?string $pluralModelLabel = 'Clínicas';
    
    protected static ?string $navigationGroup = 'Configuración Operativa';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información General')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nombre de la Sucursal'),

                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->label('Código Único (Ej: DEL-VALLE, REMOTE)')
                            ->helperText('Identificador rápido interno del sistema.'),

                        Forms\Components\Toggle::make('is_virtual')
                            ->required()
                            ->label('¿Es Clínica Virtual / Remota?')
                            ->helperText('Actívalo si esta sucursal gestiona terapias por internet y equipos prestados a domicilio.'),

                        Forms\Components\Toggle::make('is_active')
                            ->required()
                            ->default(true)
                            ->label('Sucursal Activa'),
                    ])->columns(2),

                Forms\Components\Section::Make('Contacto y Ubicación Física')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(30)
                            ->label('Teléfono de Contacto'),

                        Forms\Components\Textarea::make('address')
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->label('Dirección del Consultorio'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Sucursal'),

                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->label('Código'),

                Tables\Columns\IconColumn::make('is_virtual')
                    ->boolean()
                    ->label('Modalidad Remota'),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->label('Teléfono'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Activa'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Creado el'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_virtual')
                    ->label('Tipo de Clínica')
                    ->trueLabel('Solo Virtuales')
                    ->falseLabel('Solo Físicas'),
                
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estatus')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas'),
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
        return [
            // Aquí podremos agregar relaciones futuras (ej. Terapeutas asignados a la clínica)
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClinics::route('/'),
            'create' => Pages\CreateClinic::route('/create'),
            'edit' => Pages\EditClinic::route('/edit-record/{record}'),
        ];
    }
}