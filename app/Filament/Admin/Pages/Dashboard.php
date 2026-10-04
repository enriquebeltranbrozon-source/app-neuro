<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\CampaignPerformanceTable;
use App\Filament\Admin\Widgets\CampaignSourceChart;
use App\Filament\Admin\Widgets\CampaignStatsOverview;
use App\Filament\Admin\Widgets\GeneralStatsWidget;
use App\Filament\Admin\Widgets\LeadsByCityChart;
use App\Filament\Admin\Widgets\LeadsByLandingChart; // <-- Importar
use App\Filament\Admin\Widgets\LeadsByMonthChart;
use App\Filament\Admin\Widgets\LeadsBySymptomChart;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Panel de Control';

    public static function getNavigationLabel(): string
    {
        return 'Inicio';
    }

    public function getColumns(): int | string | array
    {
        return 2;
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filtros de Período')
                    ->description('Ajusta el rango de fechas para actualizar en tiempo real las métricas y gráficos.')
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('Fecha Inicial')
                            ->placeholder('Inicio del período')
                            ->native(false)
                            ->maxDate(now()),

                        DatePicker::make('endDate')
                            ->label('Fecha Final')
                            ->placeholder('Fin del período')
                            ->native(false)
                            ->afterOrEqual('startDate')
                            ->maxDate(now()),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            GeneralStatsWidget::class,
            CampaignStatsOverview::class,
            LeadsByLandingChart::class,       // <-- Nuevo gráfico de Landings
            CampaignSourceChart::class,
            LeadsByMonthChart::class,
            LeadsByCityChart::class,
            LeadsBySymptomChart::class,
            CampaignPerformanceTable::class,
        ];
    }
}