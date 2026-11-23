<?php

namespace App\Filament\Admin\Pages;

use App\Models\User;
use Filament\Pages\Page;

class EegTechnicianDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Mi Agenda EEG';

    protected static ?string $title = 'Agenda y Estudios EEG';

    protected static ?string $slug = 'agenda-eeg';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.admin.pages.eeg-technician-dashboard';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->isEegTechnician() || $user->isAdmin() || $user->isZoneManager();
    }

    public static function shouldRegisterNavigation(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user instanceof User && $user->isEegTechnician();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Admin\Widgets\EegTechnicianStatsWidget::class,
            \App\Filament\Admin\Widgets\UpcomingEegStudiesWidget::class,
        ];
    }
}