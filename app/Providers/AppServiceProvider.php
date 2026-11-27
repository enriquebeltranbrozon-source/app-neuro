<?php

namespace App\Providers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('lead-submission', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        $this->registerPatientPanelRedirect();
    }

    protected function registerPatientPanelRedirect(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (! method_exists($handler, 'renderable')) {
            return;
        }

        $handler->renderable(function (AuthorizationException $exception, Request $request) {
            $user = $request->user();

            $isPatient = $user !== null
                && method_exists($user, 'isPatient')
                && $user->isPatient();

            if ($isPatient && $request->is('admin-nfc*')) {
                return redirect()->route('patient.portal');
            }

            return null;
        });
    }
}