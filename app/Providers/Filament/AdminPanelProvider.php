<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Widgets\GeneralStatsWidget;
use App\Filament\Admin\Widgets\LeadsByCityChart;
use App\Filament\Admin\Widgets\LeadsByMonthChart;
use App\Filament\Admin\Widgets\LeadsBySymptomChart;
use App\Filament\Admin\Widgets\CampaignPerformanceTable;
use App\Filament\Admin\Widgets\CampaignSourceChart;
use App\Filament\Admin\Widgets\CampaignStatsOverview;
use App\Filament\Admin\Widgets\LeadsByLandingChart; // <-- 1. Importar el widget
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;


class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin-nfc')
            ->path('admin-nfc')
            ->login()
            
            // 1. IDENTIDAD DE MARCA
            ->brandName('Neurofeedback Center')
            ->brandLogo(asset('storage/images/logo.png'))
            ->brandLogoHeight('3rem')
            ->databaseNotifications() // 👈 OBLIGATORIO: Activa la campana 🔔 arriba a la derecha para ver los enlaces de descarga
            
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
                LeadsByLandingChart::class, // <-- 2. Registrar aquí para que Livewire lo reconozca
            ])
            
            // 5. MIDDLEWARE Y SEGURIDAD
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}