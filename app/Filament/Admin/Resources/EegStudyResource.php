<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EegStudyResource\Pages;
use App\Models\EegStudy;
use App\Models\Patient;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EegStudyResource extends Resource
{
    protected static ?string $model = EegStudy::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $modelLabel = 'Estudio EEG';

    protected static ?string $pluralModelLabel = 'Repositorio de Estudios EEG';

    protected static ?string $navigationLabel = 'Estudios EEG';

    protected static ?string $navigationGroup = 'Clínica';

    /*
    |--------------------------------------------------------------------------
    | Autorización y Alcance
    |--------------------------------------------------------------------------
    */

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->isAdmin()
            || $user->isZoneManager()
            || $user->isEegTechnician()
            || $user->isTherapist();
    }

    public static function canCreate(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->isAdmin()
            || $user->isZoneManager()
            || $user->isEegTechnician();
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User || ! $record instanceof EegStudy) {
            return false;
        }

        if (! ($user->isAdmin() || $user->isZoneManager() || $user->isEegTechnician())) {
            return false;
        }

        return static::getEloquentQuery()->whereKey($record->getKey())->exists();
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User || ! $record instanceof EegStudy) {
            return false;
        }

        if (! ($user->isAdmin() || $user->isZoneManager())) {
            return false;
        }

        return static::getEloquentQuery()->whereKey($record->getKey())->exists();
    }

    public static function canDeleteAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->isAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if (method_exists($query->getModel(), 'scopeForUser')) {
            return $query->forUser($user);
        }

        if ($user->isAdmin()) {
            return $query;
        }

        $clinicIds = $user->accessibleClinicIds();

        if (empty($clinicIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($clinicIds, $user): void {
            $q->whereIn('clinic_id', $clinicIds)
                ->orWhereHas('patient', fn (Builder $pq) => $pq->whereIn('clinic_id', $clinicIds))
                ->orWhere('uploaded_by', $user->id);
        });
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
                Forms\Components\Section::make('Datos del Estudio y Paciente')
                    ->description('Todo estudio de EEG debe quedar asociado a un paciente para su posterior protocolo.')
                    ->icon('heroicon-o-user-plus')
                    ->schema([
                        Forms\Components\Select::make('patient_id')
                            ->label('Paciente')
                            ->relationship(
                                name: 'patient',
                                titleAttribute: 'full_name',
                                modifyQueryUsing: function (Builder $query) {
                                    /** @var User|null $actor */
                                    $actor = auth()->user();

                                    if (! $actor instanceof User) {
                                        return $query->whereRaw('1 = 0');
                                    }

                                    if ($actor->isAdmin()) {
                                        return $query;
                                    }

                                    $clinicIds = $actor->accessibleClinicIds();

                                    if (empty($clinicIds)) {
                                        return $query->whereRaw('1 = 0');
                                    }

                                    return $query->whereIn('patients.clinic_id', $clinicIds);
                                }
                            )
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
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

                        Forms\Components\Select::make('clinic_id')
                            ->label('Clínica / Sucursal')
                            ->relationship(
                                name: 'clinic',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query) {
                                    /** @var User|null $actor */
                                    $actor = auth()->user();

                                    if (! $actor instanceof User) {
                                        return $query->whereRaw('1 = 0');
                                    }

                                    if ($actor->isAdmin()) {
                                        return $query->where('clinics.is_active', true);
                                    }

                                    $clinicIds = $actor->accessibleClinicIds();

                                    if (empty($clinicIds)) {
                                        return $query->whereRaw('1 = 0');
                                    }

                                    return $query
                                        ->where('clinics.is_active', true)
                                        ->whereIn('clinics.id', $clinicIds);
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Estado del Estudio')
                            ->options([
                                'scheduled' => 'Programado',
                                'completed' => 'Completado',
                                'external'  => 'Externo',
                            ])
                            ->default('scheduled')
                            ->native(false)
                            ->required(),
                        
                        Forms\Components\TextInput::make('title')
                            ->label('Título o Tipo de Estudio')
                            ->placeholder('Ej. EEG Basal Inicial / QEEG Pre-Protocolo')
                            ->required()
                            ->maxLength(255),

                         Forms\Components\Select::make('status')
                            ->label('Estado del Estudio')
                            ->options([
                                'scheduled' => 'Programado',
                                'completed' => 'Completado',
                                'external'  => 'Externo',
                            ])
                            ->default('scheduled')
                            ->native(false)
                            ->required(),

                        Forms\Components\DatePicker::make('study_date')
                            ->label('Fecha del Estudio')
                            ->default(now())
                            ->native(false),
                    ])->columns(2),

                Forms\Components\Section::make('Archivo del Repositorio')
                    ->description('Puedes agendar o registrar el estudio ahora y adjuntar el archivo cuando esté listo.')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        Forms\Components\FileUpload::make('file_path')
                            ->label('Archivo del Estudio EEG')
                            ->disk('local')
                            ->directory('eeg-studies')
                            ->visibility('private')
                            ->preserveFilenames()
                            ->maxSize(102400) // 100 MB
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/png',
                                'image/jpeg',
                                'application/zip',
                                'application/x-zip-compressed',
                                'text/plain',
                                'application/octet-stream',
                            ])
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                if ($state instanceof TemporaryUploadedFile) {
                                    $set('original_filename', $state->getClientOriginalName());
                                    $set('file_size', $state->getSize());
                                    $set('mime_type', $state->getMimeType());
                                }
                            })
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('original_filename'),
                        Forms\Components\Hidden::make('file_size'),
                        Forms\Components\Hidden::make('mime_type'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas Técnicas para el Protocolo')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('study_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('study_date')
                    ->label('Fecha Estudio')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('Por programar'),

                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Paciente')
                    ->searchable()
                    ->sortable(),
             
                Tables\Columns\TextColumn::make('title')
                    ->label('Estudio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('clinic.name')
                    ->label('Sucursal')
                    ->badge()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'completed' => 'Completado',
                        'external'  => 'Externo',
                        default     => 'Programado',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'completed' => 'success',
                        'external'  => 'info',
                        default     => 'warning',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('file_path')
                    ->label('Estado Archivo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Cargado' : 'Pendiente')
                    ->color(fn (?string $state): string => filled($state) ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('original_filename')
                    ->label('Archivo')
                    ->searchable()
                    ->limit(32)
                    ->tooltip(fn (EegStudy $record): ?string => $record->original_filename)
                    ->placeholder('Sin archivo'),

                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Subido por')
                    ->toggleable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('clinic_id')
                    ->label('Sucursal')
                    ->relationship('clinic', 'name', modifyQueryUsing: function (Builder $query) {
                        /** @var User|null $actor */
                        $actor = auth()->user();

                        if (! $actor instanceof User) {
                            return $query->whereRaw('1 = 0');
                        }

                        if ($actor->isAdmin()) {
                            return $query;
                        }

                        return $query->whereIn('clinics.id', $actor->accessibleClinicIds());
                    }),

                Tables\Filters\TernaryFilter::make('has_file')
                    ->label('Archivo del estudio')
                    ->placeholder('Todos los estudios')
                    ->trueLabel('Con archivo cargado')
                    ->falseLabel('Pendientes de archivo')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('file_path')->where('file_path', '!=', ''),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $q): void {
                            $q->whereNull('file_path')->orWhere('file_path', '');
                        }),
                        blank: fn (Builder $query): Builder => $query,
                    ),

                Tables\Filters\Filter::make('today')
                    ->label('Programados hoy')
                    ->query(fn (Builder $query): Builder => $query->whereDate('study_date', today())),
            ])
            ->actions([
                Tables\Actions\Action::make('quick_upload')
                    ->label(fn (EegStudy $record): string => filled($record->file_path) ? 'Reemplazar archivo' : 'Subir archivo')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color(fn (EegStudy $record): string => filled($record->file_path) ? 'gray' : 'primary')
                    ->visible(fn (EegStudy $record): bool => static::canEdit($record))
                    ->modalHeading(fn (EegStudy $record): string => 'Cargar archivo EEG — ' . ($record->patient?->name ?? $record->title))
                    ->form([
                        Forms\Components\FileUpload::make('file_path')
                            ->label('Archivo del Estudio EEG')
                            ->disk('local')
                            ->directory('eeg-studies')
                            ->visibility('private')
                            ->preserveFilenames()
                            ->maxSize(102400)
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/png',
                                'image/jpeg',
                                'application/zip',
                                'application/x-zip-compressed',
                                'text/plain',
                                'application/octet-stream',
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas Técnicas')
                            ->default(fn (EegStudy $record): ?string => $record->notes)
                            ->rows(3)
                            ->maxLength(2000),
                    ])
                    ->action(function (EegStudy $record, array $data): void {
                        /** @var User|null $actor */
                        $actor = auth()->user();
                        $disk = Storage::disk('local');
                        $path = $data['file_path'] ?? null;

                        $updates = [
                            'file_path' => $path,
                            'notes' => $data['notes'] ?? $record->notes,
                        ];

                        if ($actor instanceof User) {
                            $updates['uploaded_by'] = $actor->id;
                        }

                        if (filled($path) && $disk->exists($path)) {
                            $updates['original_filename'] = basename($path);
                            $updates['file_size'] = $disk->size($path);
                            $updates['mime_type'] = $disk->mimeType($path);
                        }

                        $record->update($updates);

                        Notification::make()
                            ->title('Archivo EEG guardado')
                            ->body('El estudio quedó vinculado al paciente y disponible para el equipo clínico.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('download')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (EegStudy $record): bool => filled($record->file_path))
                    ->action(function (EegStudy $record): ?StreamedResponse {
                        $disk = Storage::disk('local');

                        if (! $record->file_path || ! $disk->exists($record->file_path)) {
                            return null;
                        }

                        $downloadName = $record->original_filename
                            ?: Str::slug($record->title) . '.' . pathinfo($record->file_path, PATHINFO_EXTENSION);

                        return $disk->download($record->file_path, $downloadName);
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
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
            'index'  => Pages\ListEegStudies::route('/'),
            'create' => Pages\CreateEegStudy::route('/create'),
            'edit'   => Pages\EditEegStudy::route('/{record}/edit'),
        ];
    }
}