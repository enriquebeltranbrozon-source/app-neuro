<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Clinic;
use App\Models\EegStudy;
use App\Models\Expense;
use App\Models\Lead;
use App\Models\Patient;
use App\Models\User;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ZoneStatsOverviewWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user instanceof User && ($user->isZoneManager() || $user->isAdmin());
    }

    /**
     * @return array<int, int>
     */
    protected function activeClinicIds(): array
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $allowed = array_values(array_unique(array_filter(
            array_map('intval', $user->accessibleClinicIds()),
            static fn (int $id): bool => $id > 0
        )));

        $selectedClinicId = $this->filters['clinic_id'] ?? null;

        if ($selectedClinicId !== null && $selectedClinicId !== '') {
            $selectedInt = (int) $selectedClinicId;
            return in_array($selectedInt, $allowed, true) ? [$selectedInt] : [];
        }

        return $allowed;
    }

    protected function getStats(): array
    {
        $clinicIds = $this->activeClinicIds();

        if (empty($clinicIds)) {
            return [
                Stat::make('Clínicas en Alcance', 0)
                    ->description('Sin sucursales asignadas')
                    ->color('warning'),
            ];
        }

        $clinicsCount = Clinic::query()->whereIn('id', $clinicIds)->count();

        $patientsQuery = Patient::query()->whereIn('clinic_id', $clinicIds);
        $totalPatients = (clone $patientsQuery)->count();

        $leadsQuery = Lead::query();
        if (Schema::hasColumn('leads', 'clinic_id')) {
            $leadsQuery->whereIn('clinic_id', $clinicIds);
        } else {
            $leadsQuery->whereRaw('1 = 0');
        }
        $totalLeads = (clone $leadsQuery)->count();

        $studiesQuery = EegStudy::query()->where(function (Builder $q) use ($clinicIds): void {
            $q->whereIn('clinic_id', $clinicIds)
                ->orWhere(function (Builder $pq) use ($clinicIds): void {
                    $pq->whereNull('clinic_id')
                        ->whereHas('patient', fn (Builder $patientQ) => $patientQ->whereIn('clinic_id', $clinicIds));
                });
        });

        $readyStudies = (clone $studiesQuery)->whereIn('status', ['completed', 'external'])->count();
        $scheduledStudies = (clone $studiesQuery)->where('status', 'scheduled')->count();

        $expensesStat = Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'clinic_id')
            ? Stat::make(
                'Egresos Registrados',
                '$' . number_format((float) Expense::query()->whereIn('clinic_id', $clinicIds)->sum('amount'), 2)
            )
                ->description('Acumulado en clínicas del alcance')
                ->color('danger')
                ->icon('heroicon-o-arrow-trending-down')
            : Stat::make('Egresos Registrados', 'N/D')
                ->description('Columna clinic_id pendiente')
                ->color('gray');

        return [
            Stat::make('Clínicas en Alcance', $clinicsCount)
                ->description('Sucursales visibles en tu zona')
                ->color('primary')
                ->icon('heroicon-o-building-office-2'),

            Stat::make('Pacientes Registrados', $totalPatients)
                ->description('Expedientes en sucursales activas')
                ->color('success')
                ->icon('heroicon-o-user-group'),

            Stat::make('Estudios EEG (Listos / Agendados)', "{$readyStudies} / {$scheduledStudies}")
                ->description('Completados o externos vs programados')
                ->color('info')
                ->icon('heroicon-o-cpu-chip'),

            Stat::make('Leads Vinculados', $totalLeads)
                ->description('Prospectos asociados a clínica')
                ->color('warning')
                ->icon('heroicon-o-funnel'),

            $expensesStat,
        ];
    }
}