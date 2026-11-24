<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\ZoneClinicsComparisonWidget;
use App\Filament\Admin\Widgets\ZoneStatsOverviewWidget;
use App\Models\Clinic;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class ZoneManagerDashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'panel-zona';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Panorámica de Zona';

    protected static ?string $title = 'Panorámica Multiclínica de Zona';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->isZoneManager() || $user->isAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->isZoneManager() || $user->isAdmin();
    }

    public function filtersForm(Form $form): Form
    {
        /** @var User|null $user */
        $user = auth()->user();

        $clinicIds = $user instanceof User ? $user->accessibleClinicIds() : [];

        $options = Clinic::query()
            ->whereIn('id', $clinicIds)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (Clinic $clinic): array {
                $suffix = $clinic->code ? " ({$clinic->code})" : '';
                return [(string) $clinic->id => $clinic->name . $suffix];
            })
            ->all();

        return $form->schema([
            Select::make('clinic_id')
                ->label('Clínica / Sucursal')
                ->placeholder('Todas mis clínicas asignadas')
                ->options($options)
                ->native(false),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            ZoneStatsOverviewWidget::class,
            ZoneClinicsComparisonWidget::class,
        ];
    }
}