<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProtocolResource\Pages;
use App\Models\EegStudy;
use App\Models\Patient;
use App\Models\Protocol;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProtocolResource extends Resource
{
    protected static ?string $model = Protocol::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $modelLabel = 'Protocolo';

    protected static ?string $pluralModelLabel = 'Protocolos de Neurofeedback';

    protected static ?string $navigationLabel = 'Protocolos';

    protected static ?string $navigationGroup = 'Clínica';

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->isAdmin() || $user->isZoneManager() || $user->isTherapist();
    }

    public static function canCreate(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User || ! $record instanceof Protocol) {
            return false;
        }

        if (! $user->isAdmin()) {
            return false;
        }

        return static::getEloquentQuery()->whereKey($record->getKey())->exists();
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return $query->forUser($user);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make('1. Paciente y Estudio EEG')
                        ->description('Asigna el protocolo a un único paciente y vincula su estudio EEG base.')
                        ->icon('heroicon-o-user')
                        ->schema([
                            Forms\Components\Select::make('patient_id')
                                ->label('Paciente (asignación única)')
                                ->relationship(
                                    name: 'patient',
                                    titleAttribute: 'full_name',
                                    modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('full_name')
                                )
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    $set('eeg_study_id', null);

                                    if (blank($state)) {
                                        return;
                                    }

                                    $patient = Patient::query()->find((int) $state);
                                    if ($patient?->clinic_id) {
                                        $set('clinic_id', (int) $patient->clinic_id);
                                    }
                                })
                                ->searchable()
                                ->preload()
                                ->required(),

                            Forms\Components\Select::make('eeg_study_id')
                                ->label('Estudio EEG de Referencia')
                                ->options(function (Get $get): array {
                                    $patientId = (int) $get('patient_id');
                                    if ($patientId <= 0) {
                                        return [];
                                    }

                                    return EegStudy::query()
                                        ->where('patient_id', $patientId)
                                        ->orderByDesc('study_date')
                                        ->orderByDesc('id')
                                        ->get()
                                        ->mapWithKeys(function (EegStudy $study): array {
                                            $dateLabel = $study->study_date?->format('d/m/Y') ?? 'Sin fecha';
                                            $statusLabel = match ($study->status) {
                                                'completed' => 'Completado',
                                                'external'  => 'Externo',
                                                default     => 'Programado',
                                            };

                                            return [$study->id => "#{$study->id} · {$study->title} ({$statusLabel} · {$dateLabel})"];
                                        })
                                        ->all();
                                })
                                ->searchable()
                                ->helperText('Solo se muestran estudios EEG pertenecientes al paciente seleccionado.'),

                            Forms\Components\Select::make('clinic_id')
                                ->label('Sucursal / Clínica')
                                ->relationship('clinic', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),

                            Forms\Components\Select::make('status')
                                ->label('Estado del Protocolo')
                                ->options([
                                    'draft'     => 'Borrador',
                                    'active'    => 'Activo',
                                    'completed' => 'Concluido',
                                ])
                                ->default('active')
                                ->native(false)
                                ->required(),
                        ])->columns(2),

                    Forms\Components\Wizard\Step::make('2. Hallazgos EEG y Parámetros')
                        ->description('Define el diseño técnico según los hallazgos del EEG y la carga de sesiones (~45).')
                        ->icon('heroicon-o-cpu-chip')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->label('Nombre del Protocolo')
                                ->placeholder('Ej. Protocolo Atención / Regulación Alfa-Theta')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('total_sessions')
                                ->label('Total de Sesiones Estimadas')
                                ->numeric()
                                ->default(45)
                                ->minValue(1)
                                ->maxValue(120)
                                ->required()
                                ->helperText('Un tratamiento completo de neurofeedback suele rondar las 45 sesiones.'),

                            Forms\Components\TextInput::make('recommended_frequency_per_week')
                                ->label('Frecuencia Sugerida (sesiones / semana)')
                                ->numeric()
                                ->default(2)
                                ->minValue(1)
                                ->maxValue(7)
                                ->required(),

                            Forms\Components\TextInput::make('electrode_sites')
                                ->label('Sitios / Electrodos')
                                ->placeholder('Ej. Cz, Fz, C3, C4')
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('eeg_findings')
                                ->label('Hallazgos Relevantes del EEG')
                                ->rows(3)
                                ->placeholder('Describe asimetrías, exceso de ondas lentas/rápidas, coherencia, etc.')
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('frequency_targets')
                                ->label('Parámetros / Frecuencias Objetivo')
                                ->rows(3)
                                ->placeholder('Indica bandas a inhibir o recompensar y criterios de entrenamiento.')
                                ->columnSpanFull(),
                        ])->columns(2),

                    Forms\Components\Wizard\Step::make('3. Objetivos y Notas Clínicas')
                        ->description('Resume metas terapéuticas y observaciones antes de generar el plan de sesiones.')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Forms\Components\Textarea::make('clinical_goals')
                                ->label('Objetivos Clínicos del Tratamiento')
                                ->rows(3)
                                ->required()
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('clinical_notes')
                                ->label('Observaciones Adicionales')
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),
                ])
                    ->skippable()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Protocolo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label('Paciente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('eegStudy.title')
                    ->label('Estudio EEG')
                    ->placeholder('Sin estudio vinculado')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_sessions')
                    ->label('Sesiones')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active'    => 'Activo',
                        'completed' => 'Concluido',
                        default     => 'Borrador',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'active'    => 'success',
                        'completed' => 'info',
                        default     => 'warning',
                    }),

                Tables\Columns\TextColumn::make('clinic.name')
                    ->label('Sucursal')
                    ->badge()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft'     => 'Borrador',
                        'active'    => 'Activo',
                        'completed' => 'Concluido',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Protocol $record): string => route('admin.protocols.pdf', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProtocols::route('/'),
            'create' => Pages\CreateProtocol::route('/create'),
            'edit'   => Pages\EditProtocol::route('/{record}/edit'),
        ];
    }
}