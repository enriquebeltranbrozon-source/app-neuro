<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class EbeltranPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin-nfc')
        ->path('admin-nfc')
        ->login()
        // 1. IDENTIDAD (Asegúrate de que el logo esté en public/images/logo.png)
        ->brandName('Neurofeedback Center')
        ->brandLogo(asset('images/logo.png')) 
        ->brandLogoHeight('3rem')
        // 2. COLORES (Cambiamos el naranja por el Morado NFC)
        ->colors([
            'primary' => '#8759a5', // Morado NFC
            'success' => Color::Green,
            'warning' => Color::Amber,
            'danger' => Color::Rose,
            'info' => Color::Blue,
        ])
        // 3. REGISTRO DE PÁGINAS (Aquí usamos nuestra versión del Dashboard)
        ->discoverResources(in: app_path('Filament/Ebeltran/Resources'), for: 'App\\Filament\\Ebeltran\\Resources')
        ->discoverPages(in: app_path('Filament/Ebeltran/Pages'), for: 'App\\Filament\\Ebeltran\\Pages')
        ->pages([
            \App\Filament\Ebeltran\Pages\Dashboard::class, // Nuestra página personalizada
        ])
        // QUITAMOS la sección de navigationItems que causaba el error y la duplicidad
        ->widgets([
            Widgets\AccountWidget::class,
            Widgets\FilamentInfoWidget::class,
            \App\Filament\Ebeltran\Widgets\GeneralStatsWidget::class, // <--- Obligamos al panel a cargarlo
            Widgets\AccountWidget::class,
            Widgets\FilamentInfoWidget::class,
            \App\Filament\Ebeltran\Widgets\GeneralStatsWidget::class,
            \App\Filament\Ebeltran\Widgets\LeadsByMonthChart::class,
            \App\Filament\Ebeltran\Widgets\LeadsByCityChart::class,
            \App\Filament\Ebeltran\Widgets\LeadsBySymptomChart::class,
        ])
        ->middleware([
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Filament\Http\Middleware\DisableBladeIconComponents::class,
            \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
        ])
        ->authMiddleware([
            \Filament\Http\Middleware\Authenticate::class,
        ]);
}
}
