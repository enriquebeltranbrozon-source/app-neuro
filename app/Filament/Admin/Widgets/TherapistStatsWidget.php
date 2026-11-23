<?php

namespace App\Filament\Admin\Widgets;

use App\Models\EegStudy;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class TherapistStatsWidget extends BaseWidget
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

        return $user->isTherapist() || $user->isAdmin() || $user->isZoneManager();
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

    protected function scopedStudiesQuery(): Builder
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

        return $query->where(function (Builder $q) use ($clinicIds): void {
            $q->whereIn('clinic_id', $clinicIds)
                ->orWhere(function (Builder $pq) use ($clinicIds): void {
                    $pq->whereNull('clinic_id')
                        ->whereHas('patient', fn (Builder $patientQ) => $patientQ->whereIn('clinic_id', $clinicIds));
                });
        });
    }

    /**
     * Prepara la lectura de ingresos y egresos sin romper si aún no existe tabla financiera.
     *
     * @return array{available: bool, income: float, expense: float, net: float}
     */
    protected function financialSummary(): array
    {
        // Si en el futuro creas una tabla `financial_transactions` o `cash_movements` con `type` ('income'|'expense') y `amount`,
        // aquí se conectará automáticamente respetando el alcance de clínicas.
        if (! Schema::hasTable('financial_transactions')) {
            return [
                'available' => false,
                'income'    => 0.0,
                'expense'   => 0.0,
                'net'       => 0.0,
            ];
        }

        /** @var User|null $user */
        $user = auth()->user();
        $query = \Illuminate\Support\Facades\DB::table('financial_transactions');

        if ($user instanceof User && ! $user->isAdmin()) {
            $clinicIds = $this->allowedClinicIds($user);
            if (empty($clinicIds)) {
                return ['available' => true, 'income' => 0.0, 'expense' => 0.0, 'net' => 0.0];
            }
            $query->whereIn('clinic_id', $clinicIds);
        }

        $income = (float) (clone $query)->where('type', 'income')->sum('amount');
        $expense = (float) (clone $query)->where('type', 'expense')->sum('amount');

        return [
            'available' => true,
            'income'    => $income,
            'expense'   => $expense,
            'net'       => $income - $expense,
        ];
    }

    protected function getStats(): array
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! static::hasAllowedRole($user)) {
            return [];
        }

        $readyForProtocolCount = (clone $this->scopedStudiesQuery())
            ->whereIn('status', ['completed', 'external'])
            ->count();

        $pendingStudiesCount = (clone $this->scopedStudiesQuery())
            ->where('status', 'scheduled')
            ->count();

        $finance = $this->financialSummary();

        $financeStat = $finance['available']
            ? Stat::make('Balance (Ingresos / Egresos)', '$' . number_format($finance['net'], 2))
                ->description('Ingresos: $' . number_format($finance['income'], 2) . ' · Egresos: $' . number_format($finance['expense'], 2))
                ->color($finance['net'] >= 0 ? 'success' : 'danger')
                ->icon('heroicon-o-banknotes')
            : Stat::make('Ingresos y Egresos', 'Pendiente')
                ->description('Módulo financiero por configurar')
                ->color('gray')
                ->icon('heroicon-o-banknotes');

        return [
            Stat::make('Estudios listos para protocolo', $readyForProtocolCount)
                ->description('Completados o externos en tu alcance')
                ->color('success')
                ->icon('heroicon-o-document-check'),

            Stat::make('Estudios en proceso / agendados', $pendingStudiesCount)
                ->description('Aún en estado programado')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            $financeStat,
        ];
    }
}