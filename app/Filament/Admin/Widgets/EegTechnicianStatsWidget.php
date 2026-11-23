<?php

namespace App\Filament\Admin\Widgets;

use App\Models\EegStudy;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class EegTechnicianStatsWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return static::hasAllowedRole(auth()->user());
    }

    public function mount(): void
    {
        if (! static::hasAllowedRole(auth()->user())) {
            abort(403);
        }
    }

    protected static function hasAllowedRole(mixed $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return $user->isEegTechnician() || $user->isAdmin() || $user->isZoneManager();
    }

    /**
     * @return array<int, int>
     */
    protected function allowedClinicIds(User $user): array
    {
        $ids = $user->accessibleClinicIds();

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));
    }

    protected function scopedQuery(): Builder
    {
        $query = EegStudy::query();

        /** @var User|null $user */
        $user = auth()->user();

        if (! static::hasAllowedRole($user)) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        $clinicIds = $this->allowedClinicIds($user);

        if (empty($clinicIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($clinicIds, $user): void {
            $q->whereIn('clinic_id', $clinicIds)
                ->orWhere(function (Builder $pq) use ($clinicIds): void {
                    $pq->whereNull('clinic_id')
                        ->whereHas('patient', fn (Builder $patientQ) => $patientQ->whereIn('clinic_id', $clinicIds));
                })
                ->orWhere(function (Builder $uq) use ($user, $clinicIds): void {
                    $uq->where('uploaded_by', (int) $user->id)
                        ->whereIn('clinic_id', $clinicIds);
                });
        });
    }

    protected function getStats(): array
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! static::hasAllowedRole($user)) {
            return [];
        }

        $completedQuery = $this->scopedQuery()
            ->where('status', 'completed')
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '');

        if ($user->isEegTechnician()) {
            $completedQuery->where('uploaded_by', (int) $user->id);
        }

        $takenCount = (clone $completedQuery)->count();

        $upcomingCount = (clone $this->scopedQuery())
            ->where('status', 'scheduled')
            ->where(function (Builder $q): void {
                $q->whereDate('study_date', '>=', today())
                    ->orWhereNull('study_date');
            })
            ->count();

        $todayCount = (clone $this->scopedQuery())
            ->where('status', 'scheduled')
            ->whereDate('study_date', today())
            ->count();

        return [
            Stat::make('Estudios tomados', $takenCount)
                ->description('Completados y con archivo cargado')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Próximos estudios', $upcomingCount)
                ->description('Programados desde hoy o por asignar fecha')
                ->color('primary')
                ->icon('heroicon-o-calendar-days'),

            Stat::make('Programados hoy', $todayCount)
                ->description(today()->translatedFormat('d M Y'))
                ->color('warning')
                ->icon('heroicon-o-clock'),
        ];
    }
}