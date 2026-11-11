<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PatientResource\Pages;
use App\Models\Patient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    
    protected static ?string $navigationLabel = 'Pacientes / Expedientes';
    
    protected static ?string $modelLabel = 'Paciente';
    
    protected static ?string $pluralModelLabel = 'Pacientes';
    
    protected static ?string $navigationGroup = 'Gestión Clínica';
    
    protected static ?int $navigationSort = 1;

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
                            ->relationship('clinic', 'name')
                            ->required()
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
                            ->label('Fecha de Nacimiento'),
                    ])->columns(2),

                Forms\Components\Section::make('Expediente Clínico NOM-004')
                    ->description('Información legal requerida para expedientes clínicos normativos.')
                    ->schema([
                        Forms\Components\TextInput::make('curp')
                            ->maxLength(18)
                            ->unique(ignoreRecord: true)
                            ->label('CURP'),

                        Forms\Components\Select::make('gender')
                            ->options([
                                'M' => 'Masculino',
                                'F' => 'Femenino',
                                'O' => 'Otro',
                            ])
                            ->label('Género'),

                        Forms\Components\Textarea::make('address')
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->label('Dirección Completa'),

                        Forms\Components\TextInput::make('emergency_contact_name')
                            ->maxLength(255)
                            ->label('Contacto de Emergencia (Nombre)'),

                        Forms\Components\TextInput::make('emergency_contact_phone')
                            ->maxLength(30)
                            ->label('Contacto de Emergencia (Teléfono)'),
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
                    ->relationship('clinic', 'name')
                    ->label('Sucursal'),
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
            // Aquí agregaremos las relaciones de sesiones clínicas y notas de evolución próximamente
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPatients::route('/'),
            'create' => Pages\CreatePatient::route('/create'),
            'edit' => Pages\EditPatient::route('/record/{record}/edit'),
        ];
    }
}