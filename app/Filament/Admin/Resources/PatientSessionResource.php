<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PatientSessionResource\Pages;
use App\Models\PatientSession;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PatientSessionResource extends Resource
{
    protected static ?string $model = PatientSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    
    protected static ?string $navigationLabel = 'Sesiones y Protocolos';
    
    protected static ?string $modelLabel = 'Sesión Clínica';
    
    protected static ?string $pluralModelLabel = 'Sesiones Clínicas';
    
    protected static ?string $navigationGroup = 'Gestión Clínica';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Asignación de la Sesión')
                    ->schema([
                        Forms\Components\Select::make('patient_id')
                            ->relationship('patient', 'full_name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Paciente'),

                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
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
                    ])->columns(3),

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
                Tables\Filters\SelectFilter::make('service_id')
                    ->relationship('service', 'name')
                    ->label('Servicio'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
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
            // Relaciones adicionales si se requieren
        ];
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