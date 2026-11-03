<?php

use App\Support\DonneesTest;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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
    $this->line('alice@exemple.com, rado@exemple.com, sarah@exemple.com, tiana@exemple.com');

    return 0;
})->purpose('Remplace toutes les données par le jeu de test complet (database/donnees_test.sql)');
