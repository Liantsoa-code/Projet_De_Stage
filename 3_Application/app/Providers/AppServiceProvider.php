<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

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
        Vite::prefetch(concurrency: 3);

        Gate::define('is-admin', function (User $user) {
            return $user->role === 'admin';
        });

        Gate::define('is-validateur', function (User $user) {
            return $user->role === 'validateur' || $user->role === 'admin';
        });

        Gate::define('is-demandeur', function (User $user) {
            return $user->role === 'demandeur';
        });

        // Création et modification des demandes (avec envoi de fichiers) : 20 envois par minute et par utilisateur.
        RateLimiter::for('demandes', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user()?->id ?: $request->ip())
            ->response(fn () => back()->with('error', "Trop d'envois en peu de temps : patientez une minute avant de réessayer.")));
    }
}
