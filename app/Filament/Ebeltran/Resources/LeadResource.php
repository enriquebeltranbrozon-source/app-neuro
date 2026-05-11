<?php

namespace App\Filament\Ebeltran\Resources;

use App\Filament\Ebeltran\Resources\LeadResource\Pages;
use App\Filament\Ebeltran\Resources\LeadResource\RelationManagers;
use App\Models\Lead;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    
    // --- TRADUCCIONES UNIFICADAS ---
    protected static ?string $modelLabel = 'Prospecto';
    protected static ?string $pluralModelLabel = 'Leads';
    protected static ?string $navigationLabel = 'Gestión de Prospectos'; // Solo una vez

    public static function getModelLabel(): string
    {
        return 'Prospecto';
    }
    // -------------------------------

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // SECCIÓN 1: DATOS PERSONALES
                Forms\Components\Section::make('Información del Paciente')
                    ->description('Datos básicos y de contacto recogidos por el formulario.')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre Completo')
                            ->required()
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('age')
                            ->label('Edad')
                            ->numeric()
                            ->required()
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->required(),
                        Forms\Components\TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->required(),
                        Forms\Components\TextInput::make('city')
                            ->label('Ciudad')
                            ->required(),
                    ])->columns(3),

                // SECCIÓN 2: MOTIVO DE CONSULTA
                Forms\Components\Section::make('Detalles de la Consulta')
                    ->description('Información técnica y descripción enviada por el prospecto.')
                    ->icon('heroicon-o-beaker')
                    ->schema([
                        Forms\Components\TextInput::make('patient_type')
                            ->label('El paciente es...')
                            ->disabled(),
                        Forms\Components\TextInput::make('main_symptom')
                            ->label('Síntomas principales')
                            ->disabled(),
                        Forms\Components\Textarea::make('symptom_description')
                            ->label('Descripción detallada del caso')
                            ->placeholder('No se proporcionó descripción adicional.')
                            ->columnSpanFull()
                            ->rows(4)
                            ->disabled(),
                    ])->columns(2),

                // SECCIÓN 3: GESTIÓN INTERNA
                Forms\Components\Section::make('Seguimiento y Cierre (Uso Interno)')
                    ->description('Control de estados y asignación de agentes.')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Estado de la Venta')
                            ->options([
                                'abierto' => 'Abierto (Nuevo)',
                                'no_concretado' => 'No Concretado (Perdido)',
                                'cliente_nuevo' => 'Cliente Nuevo (Cerrado)',
                            ])
                            ->required()
                            ->selectablePlaceholder(false),
                        Forms\Components\Select::make('user_id')
                            ->label('Agente Asignado')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\DateTimePicker::make('contacted_at')
                            ->label('Fecha del primer contacto')
                            ->native(false),
                        Forms\Components\DatePicker::make('follow_up_date')
                            ->label('Próximo contacto programado')
                            ->native(false),
                        Forms\Components\Textarea::make('agent_comments')
                            ->label('Comentarios y notas del Agente')
                            ->columnSpanFull()
                            ->rows(4),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Paciente')
                    ->searchable()
                    ->description(fn (Lead $record): string => "Edad: {$record->age} años"),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'abierto' => 'warning',
                        'cliente_nuevo' => 'success',
                        'no_concretado' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Agente')
                    ->placeholder('Sin asignar'),
                Tables\Columns\TextColumn::make('follow_up_date')
                    ->label('Seguimiento')
                    ->date('d/m/y')
                    ->color('primary'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'abierto' => 'Abierto',
                        'no_concretado' => 'No Concretado',
                        'cliente_nuevo' => 'Cliente Nuevo',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(), 
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}