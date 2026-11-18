<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
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

        // En production, tous les liens envoyés par email (vérification de l'adresse, réinitialisation du
        // mot de passe, notifications) partent de APP_URL et jamais de l'en-tête Host de la requête :
        // un en-tête falsifié ne peut pas détourner un lien de réinitialisation vers un autre site.
        if ($this->app->isProduction()) {
            URL::forceRootUrl(config('app.url'));
            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        // Mot de passe choisi à l'inscription, à la réinitialisation ou dans le profil :
        // 10 caractères minimum, avec au moins une lettre et un chiffre (rappelé sous le champ de Register.vue).
        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        Gate::define('is-admin', function (User $user) {
            return $user->role === 'admin';
        });

        Gate::define('is-validateur', function (User $user) {
            return $user->role === 'validateur' || $user->role === 'admin';
        });

        // Pages de l'instruction en lecture (liste, fiche, export) : l'équipe, observateurs compris
        Gate::define('peut-suivre', function (User $user) {
            return in_array($user->role, ['observateur','validateur', 'admin'], true);
        });

        // Rapport d'activité de toute la plateforme : admin et observateur (qui voient toutes les aires protégées)
        Gate::define('peut-piloter', function (User $user) {
            return in_array($user->role, ['observateur', 'admin'], true);
        });

        Gate::define('is-demandeur', function (User $user) {
            return $user->role === 'demandeur';
        });
        // Gate::define('is-observateur', function (User $user){
        //     return $user->role === 'observateur';
        // });

        // Création et modification des demandes (avec envoi de fichiers) : 20 envois par minute et par utilisateur.
        RateLimiter::for('demandes', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user()?->id ?: $request->ip())
            ->response(fn () => back()->with('error', "Trop d'envois en peu de temps : patientez une minute avant de réessayer.")));

        // Inscription et mot de passe oublié : chaque envoi déclenche un email. Limité par adresse IP
        // (5 par minute, 20 par heure) pour qu'un robot ne puisse pas inonder des adresses inconnues
        // et faire classer l'adresse d'envoi de la plateforme comme spam.
        RateLimiter::for('comptes', fn (Request $request) => collect([
            Limit::perMinute(5)->by('minute|'.$request->ip()),
            Limit::perHour(20)->by('heure|'.$request->ip()),
        ])->map(fn (Limit $limit) => $limit->response(fn () => back()->withErrors([
            'email' => 'Trop de tentatives depuis votre connexion : patientez quelques minutes avant de réessayer.',
        ])))->all());
    }
}
