<?php

use App\Models\User;
use App\Support\DonneesTest;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jeu de données de test complet : remplace toutes les données de la base (voir STRUCTURE.md, 8.4).
Artisan::command('donnees:test {--force : Ne pas demander de confirmation}', function () {
    if (app()->isProduction()) {
        $this->error('Refusé en production : le jeu de test remplace toutes les données.');

        return 1;
    }

    $base = DB::connection()->getDatabaseName();
    if (! $this->option('force') && ! $this->confirm("Toutes les données de la base « {$base} » seront remplacées par le jeu de test. Continuer ?")) {
        return 1;
    }

    $this->call('migrate', ['--force' => true]);
    $this->info(DonneesTest::charger() . ' instructions de ' . DonneesTest::FICHIER . ' exécutées.');
    $this->info(DonneesTest::piecesFactices() . ' pièces jointes de démonstration créées (les fichiers déjà présents sont gardés).');
    $this->info(DonneesTest::analyser() . ' demandes analysées avec les règles actuelles.');
    $this->newLine();
    $this->line('Comptes (mot de passe : password) : admin@fanamby.org, validateur@fanamby.org, hery@fanamby.org,');
    $this->line('observateur@fanamby.org, alice@exemple.com, rado@exemple.com, sarah@exemple.com, tiana@exemple.com');
    $this->line('et 8 demandeurs de l\'historique d\'activité (faly@, claire@, mamy@, lova@, thomas@, nantenaina@, sophie@, hasina@exemple.com)');

    return 0;
})->purpose('Remplace toutes les données par le jeu de test complet (database/donnees_test.sql)');

// Administrateur d'une plateforme déployée (voir DEPLOIEMENT.md) : crée le compte, ou nomme administrateur
// un compte existant. L'adresse est marquée comme vérifiée : l'admin se connecte sans attendre d'email.
// Le mot de passe est saisi sans s'afficher et suit la même règle que l'inscription (Password::defaults).
Artisan::command('plateforme:admin {email? : Adresse e-mail du compte}', function () {
    $email = Str::lower(trim((string) ($this->argument('email') ?: $this->ask("Adresse e-mail de l'administrateur"))));
    if (Validator::make(['email' => $email], ['email' => 'required|email|max:255'])->fails()) {
        $this->error('Adresse e-mail invalide.');

        return 1;
    }

    $demanderMotDePasse = function (): ?string {
        for ($essai = 1; $essai <= 3; $essai++) {
            $donnees = [
                'password' => (string) $this->secret('Mot de passe (10 caractères minimum, avec au moins une lettre et un chiffre)'),
                'password_confirmation' => (string) $this->secret('Confirmez le mot de passe'),
            ];
            $validation = Validator::make($donnees, ['password' => ['required', 'confirmed', Password::defaults()]]);
            if ($validation->passes()) {
                return $donnees['password'];
            }
            foreach ($validation->errors()->all() as $erreur) {
                $this->error($erreur);
            }
        }

        return null;
    };

    $user = User::where('email', $email)->first();

    if ($user) {
        $this->line("Le compte {$user->name} <{$email}> existe déjà (rôle : {$user->role}).");
        if (! $this->confirm('Le nommer administrateur ?', true)) {
            return 1;
        }
        $motDePasse = $this->confirm('Lui donner un nouveau mot de passe ?', false) ? $demanderMotDePasse() : '';
    } else {
        $nom = trim((string) $this->ask('Nom complet'));
        if ($nom === '' || mb_strlen($nom) > 255) {
            $this->error('Nom invalide.');

            return 1;
        }
        $user = new User(['name' => $nom, 'email' => $email]);
        $motDePasse = $demanderMotDePasse();
    }

    if ($motDePasse === null) {
        $this->error('Mot de passe refusé trois fois : rien n\'a été enregistré.');

        return 1;
    }

    DB::transaction(function () use ($user, $motDePasse) {
        $user->role = 'admin';
        if ($motDePasse !== '') {
            $user->password = $motDePasse; // haché par le cast « hashed » du modèle
        }
        $user->email_verified_at ??= now();
        $user->save();
        // Un admin voit toutes les aires protégées : pas d'affectation de site (comme Admin\UserController)
        $user->sites()->detach();
    });

    $this->info("{$user->name} <{$user->email}> est administrateur. Connexion : ".rtrim((string) config('app.url'), '/').'/login');

    return 0;
})->purpose('Crée l\'administrateur de la plateforme, ou nomme administrateur un compte existant');
