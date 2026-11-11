<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    
    protected static ?string $navigationLabel = 'Egresos y Caja Chica';
    
    protected static ?string $modelLabel = 'Egreso';
    
    protected static ?string $pluralModelLabel = 'Egresos';
    
    protected static ?string $navigationGroup = 'Finanzas y Administración';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Gasto')
                    ->schema([
                        Forms\Components\Select::make('expense_category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Categoría de Egreso'),

                        Forms\Components\TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->label('Monto'),

                        Forms\Components\DatePicker::make('expense_date')
                            ->required()
                            ->default(now())
                            ->label('Fecha del Gasto'),

                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->required()
                            ->default(auth()->id())
                            ->searchable()
                            ->preload()
                            ->label('Registrado por'),
                    ])->columns(2),

                Forms\Components\Section::make('Comprobante y Observaciones')
                    ->schema([
                        Forms\Components\TextInput::make('description')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->label('Concepto / Descripción breve'),

                        Forms\Components\Toggle::make('has_invoice')
                            ->required()
                            ->default(false)
                            ->label('¿Cuenta con Factura / Comprobante Fiscal?'),

                        Forms\Components\Textarea::make('notes')
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->label('Notas Adicionales'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('expense_date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->label('Fecha'),

                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->sortable()
                    ->label('Concepto'),

                Tables\Columns\TextColumn::make('category.name')
                    ->badge()
                    ->color('warning')
                    ->sortable()
                    ->label('Categoría'),

                Tables\Columns\TextColumn::make('amount')
                    ->money('MXN')
                    ->sortable()
                    ->label('Monto'),

                Tables\Columns\IconColumn::make('has_invoice')
                    ->boolean()
                    ->label('Facturado'),

                Tables\Columns\TextColumn::make('user.name')
                    ->sortable()
                    ->toggleable()
                    ->label('Registró'),
            ])
            ->defaultSort('expense_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('expense_category_id')
                    ->relationship('category', 'name')
                    ->label('Categoría'),

                Tables\Filters\TernaryFilter::make('has_invoice')
                    ->label('Facturación')
                    ->trueLabel('Con Factura')
                    ->falseLabel('Sin Factura'),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/edit-record/{record}'),
        ];
    }
}