<?php

namespace App\Filament\Ebeltran\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Ebeltran\Widgets\GeneralStatsWidget;
use App\Filament\Ebeltran\Widgets\LeadsByMonthChart;
use App\Filament\Ebeltran\Widgets\LeadsByCityChart;
use App\Filament\Ebeltran\Widgets\LeadsBySymptomChart;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Panel de Control';

    public static function getNavigationLabel(): string
    {
        return 'Panel de Control';
    }

    public function getWidgets(): array
    {
        return [
            GeneralStatsWidget::class,
            LeadsByMonthChart::class,
            LeadsByCityChart::class,
            LeadsBySymptomChart::class,
        ];
    }
}