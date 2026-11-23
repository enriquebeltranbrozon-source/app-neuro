<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\CampaignPerformanceTable;
use App\Filament\Admin\Widgets\CampaignSourceChart;
use App\Filament\Admin\Widgets\CampaignStatsOverview;
use App\Filament\Admin\Widgets\GeneralStatsWidget;
use App\Filament\Admin\Widgets\LeadsByCityChart;
use App\Filament\Admin\Widgets\LeadsByLandingChart;
use App\Filament\Admin\Widgets\LeadsByMonthChart;
use App\Filament\Admin\Widgets\LeadsBySymptomChart;
use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->isAdmin() || $user->isZoneManager();
    }

    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            GeneralStatsWidget::class,
            LeadsByMonthChart::class,
            LeadsByCityChart::class,
            LeadsBySymptomChart::class,
            CampaignStatsOverview::class,
            CampaignSourceChart::class,
            CampaignPerformanceTable::class,
            LeadsByLandingChart::class,
        ];
    }
}