<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\TherapistReadyStudiesWidget;
use App\Filament\Admin\Widgets\TherapistStatsWidget;
use App\Models\User;
use Filament\Pages\Page;

class TherapistDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Panel Clínico';

    protected static ?string $title = 'Panel Clínico y Protocolos';

    protected static ?string $slug = 'panel-clinico';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.admin.pages.therapist-dashboard';

    public static function canAccess(): bool
    {
        return static::hasAllowedRole(auth()->user());
    }

    public function mount(): void
    {
        if (! static::hasAllowedRole(auth()->user())) {
            abort(403);
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        // Visible para terapeutas; admins/zone managers pueden verlo también si se registra o acceden directo
        return $user->isTherapist() || $user->isAdmin() || $user->isZoneManager();
    }

    protected static function hasAllowedRole(mixed $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return $user->isTherapist() || $user->isAdmin() || $user->isZoneManager();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TherapistStatsWidget::class,
            TherapistReadyStudiesWidget::class,
        ];
    }
}