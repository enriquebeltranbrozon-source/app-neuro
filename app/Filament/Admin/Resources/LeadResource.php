<?php

namespace App\Filament\Admin\Resources;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\LeadResource\Pages;
use App\Filament\Exports\LeadExporter;
use App\Models\Lead;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static ?string $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $modelLabel = 'Prospecto';

    protected static ?string $pluralModelLabel = 'Leads / Prospectos';

    protected static ?string $navigationLabel = 'Gestión de Prospectos';

    protected static ?string $navigationGroup = 'Gestión Comercial';

    /**
     * Aislamiento SQL por Rol:
     * El agente comercial solo consulta sus leads o prospectos sin asignar.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        /** @var \App\Models\User|null $currentUser */
        $currentUser = auth()->user();

        if ($currentUser?->isAgent()) {
            return $query->where(function (Builder $q) use ($currentUser) {
                $q->where('user_id', $currentUser->id)
                  ->orWhereNull('user_id');
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        /** @var \App\Models\User|null $currentUser */
        $currentUser = auth()->user();

        return $form
            ->schema([
                // SECCIÓN 1: DATOS PERSONALES
                Forms\Components\Section::make('Información del Paciente')
                    ->description('Datos básicos y de contacto del prospecto.')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre Completo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('age')
                            ->label('Edad')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(120)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono (WhatsApp)')
                            ->tel()
                            ->required()
                            ->maxLength(20),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\Select::make('city')
                            ->label('Ciudad / Sede')
                            ->options([
                                'CDMX - Del Valle' => 'CDMX - Del Valle',
                                'Naucalpan (Satélite)' => 'Naucalpan (Satélite)',
                                'Metepec' => 'Metepec',
                                'Cuernavaca' => 'Cuernavaca',
                                'Monterrey' => 'Monterrey',
                                'Huixquilucan (Interlomas)' => 'Huixquilucan (Interlomas)',
                            ])
                            ->searchable(),
                    ])->columns(3),

                // SECCIÓN 2: MOTIVO DE CONSULTA
                Forms\Components\Section::make('Detalles de la Consulta')
                    ->description('Información sobre el padecimiento y motivo de contacto.')
                    ->icon('heroicon-o-beaker')
                    ->schema([
                        Forms\Components\TextInput::make('patient_type')
                            ->label('El paciente es...')
                            ->placeholder('Ej. Para mi hijo/a'),

                        Forms\Components\TextInput::make('main_symptom')
                            ->label('Síntoma Principal')
                            ->placeholder('Ej. TDAH, Ansiedad'),

                        Forms\Components\Textarea::make('symptom_description')
                            ->label('Descripción detallada del caso')
                            ->placeholder('No se proporcionó descripción adicional.')
                            ->columnSpanFull()
                            ->rows(3),
                    ])->columns(2),

                // SECCIÓN 3: GESTIÓN INTERNA
                Forms\Components\Section::make('Seguimiento Comercial (Uso Interno)')
                    ->description('Control de estados, asignaciones y bitácora de llamadas.')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Estado de la Venta')
                            ->options([
                                'abierto'         => 'Abierto (Nuevo)',
                                'contactado'     => 'Contactado',
                                'cita_programada' => 'Cita Programada',
                                'cliente_nuevo'   => 'Cliente Nuevo (Cerrado)',
                                'no_concretado'  => 'No Concretado (Perdido)',
                            ])
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('user_id')
                            ->label('Agente Asignado')
                            ->relationship('user', 'name', fn (Builder $query) => $query->where('role', UserRole::Agent->value))
                            ->placeholder('Sin asignar (Buzón General)')
                            ->searchable()
                            ->preload()
                            ->disabled(!$currentUser?->isAdmin()),

                        Forms\Components\DateTimePicker::make('contacted_at')
                            ->label('Fecha del primer contacto')
                            ->native(false),

                        Forms\Components\DatePicker::make('follow_up_date')
                            ->label('Próximo contacto programado')
                            ->native(false),

                        Forms\Components\Textarea::make('agent_comments')
                            ->label('Comentarios y notas del Agente')
                            ->placeholder('Registra aquí acuerdos o notas importantes...')
                            ->columnSpanFull()
                            ->rows(4),
                    ])->columns(2),

                // SECCIÓN 4: ATRIBUCIÓN PUBLICITARIA (Solo Visible para Admin y Marketing)
                Forms\Components\Section::make('Atribución Publicitaria (Pauta y Origen)')
                    ->description('Métricas de canal, landing originaria, campañas de anuncios y Google Click ID.')
                    ->icon('heroicon-o-megaphone')
                    ->schema([
                        Forms\Components\TextInput::make('source')
                            ->label('Canal u Origen')
                            ->disabled(),

                        Forms\Components\TextInput::make('landing_origin')
                            ->label('Origen del Formulario')
                            ->placeholder('N/A')
                            ->disabled(),

                        Forms\Components\TextInput::make('landing_page')
                            ->label('Página Web (URL / Ruta)')
                            ->placeholder('neurofeedback.mx/')
                            ->disabled(),

                        Forms\Components\TextInput::make('utm_source')
                            ->label('UTM Source')
                            ->disabled(),

                        Forms\Components\TextInput::make('utm_medium')
                            ->label('UTM Medium')
                            ->disabled(),

                        Forms\Components\TextInput::make('utm_campaign')
                            ->label('Campaña')
                            ->disabled(),

                        Forms\Components\TextInput::make('gclid')
                            ->label('Google Click ID (GCLID)')
                            ->disabled()
                            ->columnSpan(2),

                        Forms\Components\DateTimePicker::make('whatsapp_matched_at')
                            ->label('Match de WhatsApp')
                            ->disabled(),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->visible(!$currentUser?->isAgent()),
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
                    ->sortable()
                    ->description(fn (Lead $record): string => $record->age ? "Edad: {$record->age} años" : 'Edad no especificada'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado al portapapeles'),

                // 1. ORIGEN / LANDING EN LA TABLA
                Tables\Columns\TextColumn::make('landing_origin')
                    ->label('Origen')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'main', 'main-form', 'web_form', 'home-page' => 'info',
                        'landing_deficit', 'deficit'                 => 'warning',
                        'landing_ansiedad', 'ansiedad'               => 'success',
                        'landing_depresion', 'depresion'             => 'danger',
                        default                                      => 'gray',
                    })
                    ->placeholder('Principal')
                    ->searchable()
                    ->toggleable(),

                // 2. ENLACE DIRECTO A LA PÁGINA ORIGINARIA
                Tables\Columns\TextColumn::make('landing_page')
                    ->label('Página de Origen')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->iconPosition('after')
                    ->url(function (Lead $record): ?string {
                        if (empty($record->landing_page)) return null;
                        return str_starts_with($record->landing_page, 'http')
                            ? $record->landing_page
                            : 'https://neurofeedback.mx' . (str_starts_with($record->landing_page, '/') ? '' : '/') . $record->landing_page;
                    }, shouldOpenInNewTab: true)
                    ->placeholder('neurofeedback.mx/')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('city')
                    ->label('Sede')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('main_symptom')
                    ->label('Padecimiento')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'abierto'         => 'danger',
                        'contactado'     => 'warning',
                        'cita_programada' => 'info',
                        'cliente_nuevo'   => 'success',
                        'no_concretado'  => 'gray',
                        default           => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'abierto'         => 'Abierto',
                        'contactado'     => 'Contactado',
                        'cita_programada' => 'Cita Programada',
                        'cliente_nuevo'   => 'Cliente Nuevo',
                        'no_concretado'  => 'No Concretado',
                        default           => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Agente')
                    ->placeholder('Sin asignar')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('follow_up_date')
                    ->label('Próx. Seguimiento')
                    ->date('d/m/Y')
                    ->color('primary')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'abierto'         => 'Abierto',
                        'contactado'     => 'Contactado',
                        'cita_programada' => 'Cita Programada',
                        'cliente_nuevo'   => 'Cliente Nuevo',
                        'no_concretado'  => 'No Concretado',
                    ]),

                Tables\Filters\SelectFilter::make('city')
                    ->label('Sede / Ciudad'),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Agente Asignado')
                    ->relationship('user', 'name'),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Exportar Excel / CSV')
                    ->exporter(LeadExporter::class)
                    ->color('success')
                    ->icon('heroicon-o-document-arrow-down'),
            ])
            ->actions([
                // 1. WhatsApp Directo
                Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(function (Lead $record): string {
                        $phone = preg_replace('/[^0-9]/', '', $record->phone);
                        if (strlen($phone) === 10) {
                            $phone = '52' . $phone;
                        }
                        $message = rawurlencode("Hola {$record->name}, te saludamos de Neurofeedback Center. Vemos que nos solicitaste información sobre atención para " . ($record->main_symptom ?? 'evaluación') . ". ¿En qué horario te gustaría platicar?");
                        return "https://wa.me/{$phone}?text={$message}";
                    })
                    ->openUrlInNewTab(),

                // 2. Tomar Prospecto Libre
                Tables\Actions\Action::make('claim')
                    ->label('Tomar')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->visible(fn (Lead $record): bool => is_null($record->user_id) && (auth()->user()?->isAgent() ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('Asignarte este prospecto')
                    ->modalDescription('¿Deseas tomar este prospecto e integrarlo a tu cartera de seguimiento?')
                    ->action(function (Lead $record): void {
                        $record->update([
                            'user_id'      => auth()->id(),
                            'status'       => $record->status === 'abierto' ? 'contactado' : $record->status,
                            'contacted_at' => $record->contacted_at ?? now(),
                        ]);

                        Notification::make()
                            ->title('Prospecto Asignado')
                            ->body("Has tomado a {$record->name} exitosamente.")
                            ->success()
                            ->send();
                    }),

                // 3. Modal Rápido de Bitácora
                Tables\Actions\Action::make('quick_notes')
                    ->label('Bitácora')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Estado Comercial')
                            ->options([
                                'abierto'         => 'Abierto (Nuevo)',
                                'contactado'     => 'Contactado',
                                'cita_programada' => 'Cita Programada',
                                'cliente_nuevo'   => 'Cliente Nuevo (Cerrado)',
                                'no_concretado'  => 'No Concretado (Perdido)',
                            ])
                            ->required(),
                        Forms\Components\DatePicker::make('follow_up_date')
                            ->label('Próximo Seguimiento')
                            ->native(false),
                        Forms\Components\Textarea::make('agent_comments')
                            ->label('Comentarios del Agente')
                            ->rows(3),
                    ])
                    ->fillForm(fn (Lead $record): array => [
                        'status'         => $record->status,
                        'follow_up_date' => $record->follow_up_date,
                        'agent_comments' => $record->agent_comments,
                    ])
                    ->action(function (Lead $record, array $data): void {
                        $record->update([
                            'status'         => $data['status'],
                            'follow_up_date' => $data['follow_up_date'],
                            'agent_comments' => $data['agent_comments'],
                            'contacted_at'   => $record->contacted_at ?? ($data['status'] !== 'abierto' ? now() : null),
                        ]);

                        Notification::make()
                            ->title('Seguimiento actualizado')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(LeadExporter::class),
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index'  => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'edit'   => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}