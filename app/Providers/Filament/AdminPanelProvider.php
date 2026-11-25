<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Widgets\CampaignPerformanceTable;
use App\Filament\Admin\Widgets\CampaignSourceChart;
use App\Filament\Admin\Widgets\CampaignStatsOverview;
use App\Filament\Admin\Widgets\EegTechnicianStatsWidget;
use App\Filament\Admin\Widgets\GeneralStatsWidget;
use App\Filament\Admin\Widgets\LeadsByCityChart;
use App\Filament\Admin\Widgets\LeadsByLandingChart;
use App\Filament\Admin\Widgets\LeadsByMonthChart;
use App\Filament\Admin\Widgets\LeadsBySymptomChart;
use App\Filament\Admin\Widgets\TherapistReadyStudiesWidget;
use App\Filament\Admin\Widgets\TherapistStatsWidget;
use App\Filament\Admin\Widgets\UpcomingEegStudiesWidget;
use App\Filament\Admin\Widgets\ZoneClinicsComparisonWidget;
use App\Filament\Admin\Widgets\ZoneStatsOverviewWidget;
use App\Http\Middleware\RedirectByRole;
use App\Models\User;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $logoUrl = Storage::disk('public')->url('images/logo.png');

        return $panel
            ->default()
            ->id('admin-nfc')
            ->path('admin-nfc')
            ->login()

            // 1. IDENTIDAD DE MARCA (Login + Barra de navegación)
            ->brandName('Neurofeedback Center')
            ->brandLogo(fn () => new HtmlString(
                '<div class="flex items-center gap-2.5">'
                . '<img src="' . e($logoUrl) . '" alt="Neurofeedback Center" class="h-10 w-auto object-contain" />'
                //. '<span class="text-sm font-semibold tracking-tight text-gray-900 dark:text-white">Neurofeedback Center</span>'
                . '</div>'
            ))
            ->brandLogoHeight('2.25rem')
            ->databaseNotifications()

            // 2. PALETA DE COLORES (Morado NFC + Estados)
            ->colors([
                'primary' => '#8759a5', // Morado corporativo NFC
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger'  => Color::Rose,
                'info'    => Color::Blue,
            ])

            // 3. DESCUBRIMIENTO AUTOMÁTICO DE RECURSOS Y PÁGINAS (Namespace Admin)
            ->discoverResources(
                in: app_path('Filament/Admin/Resources'),
                for: 'App\\Filament\\Admin\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Admin/Pages'),
                for: 'App\\Filament\\Admin\\Pages'
            )
            ->pages([
                Dashboard::class,
            ])

            // 4. REGISTRO UNIFICADO Y LIMPIO DE WIDGETS
            ->widgets([
                AccountWidget::class,
                GeneralStatsWidget::class,
                LeadsByMonthChart::class,
                LeadsByCityChart::class,
                LeadsBySymptomChart::class,
                CampaignStatsOverview::class,
                CampaignSourceChart::class,
                CampaignPerformanceTable::class,
                LeadsByLandingChart::class,
                EegTechnicianStatsWidget::class,
                UpcomingEegStudiesWidget::class,
                TherapistStatsWidget::class,
                TherapistReadyStudiesWidget::class,
                ZoneStatsOverviewWidget::class,
                ZoneClinicsComparisonWidget::class,
            ])

            // 5. INDICADOR DE SUCURSAL / CONTEXTO EN BARRA SUPERIOR
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                function (): string {
                    /** @var User|null $user */
                    $user = auth()->user();

                    if (! $user instanceof User) {
                        return '';
                    }

                    [$label, $badgeClasses] = match (true) {
                        $user->isAdmin() => [
                            'Neurofeedback Center',
                            'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30',
                        ],
                        $user->isZoneManager() => [
                            'Zona Monterrey',
                            'bg-purple-50 text-purple-700 ring-purple-600/20 dark:bg-purple-500/10 dark:text-purple-300 dark:ring-purple-500/30',
                        ],
                        $user->isTherapist() => [
                            $user->primaryClinic()?->name ?? 'Neurofeedback Center',
                            'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
                        ],
                        default => [
                            'Neurofeedback Center',
                            'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-300 dark:ring-gray-500/30',
                        ],
                    };

                    return '<div class="ms-3 hidden sm:flex items-center gap-2">'
                        . '<span class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ' . $badgeClasses . '">'
                        . '<span class="h-1.5 w-1.5 rounded-full bg-current"></span>'
                        . e($label)
                        . '</span>'
                        . '</div>';
                }
            )

            // 6. MIDDLEWARE Y SEGURIDAD
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                RedirectByRole::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}